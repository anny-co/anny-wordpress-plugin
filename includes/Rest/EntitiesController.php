<?php

declare(strict_types=1);

namespace Anny\Rest;

\defined('ABSPATH') || exit;

use Anny\Api\ApiClient;
use Anny\Oauth\OauthClient;
use Anny\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Nonce'd REST proxy for the editor and the settings screen: connection
 * status, entity pickers, and the brand import.
 *
 * The four public entity listings (resources/services/plans/categories) are
 * proxied WITHOUT a Bearer token on purpose: they are public relationship
 * routes and a narrowly scoped token would 403 on the upstream scope check.
 */
final class EntitiesController
{
    public const REST_NAMESPACE = 'anny/v1';

    private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

    public function __construct(
        private readonly Settings $settings,
        private readonly OauthClient $oauth,
        private readonly ApiClient $api,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            '/status',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'status'],
                'permission_callback' => static fn(): bool => current_user_can('edit_posts'),
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/entities/organizations',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'organizations'],
                'permission_callback' => static fn(): bool => current_user_can('manage_options'),
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/entities/(?P<type>resources|services|plans|categories)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'entities'],
                'permission_callback' => static fn(): bool => current_user_can('edit_posts'),
                'args'                => [
                    'search' => [
                        'type'              => 'string',
                        'default'           => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/brand',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'brand'],
                'permission_callback' => static fn(): bool => current_user_can('manage_options'),
            ]
        );
    }

    /**
     * GET /status
     */
    public function status(): WP_REST_Response
    {
        $region = $this->settings->regionConfig();

        return new WP_REST_Response(
            [
                'connected'   => $this->oauth->isConnected(),
                'region'      => $this->settings->region(),
                'widgetBase'  => (string) ($region['widget_base'] ?? ''),
                'org'         => $this->settings->org(),
                'settingsUrl' => $this->oauth->settingsUrl(),
            ]
        );
    }

    /**
     * GET /entities/organizations — organizations of the connected user.
     */
    public function organizations(): WP_REST_Response|WP_Error
    {
        $cacheKey = $this->cacheKey('organizations', '');
        $cached   = get_transient($cacheKey);

        if (is_array($cached)) {
            return new WP_REST_Response($cached);
        }

        $organizations = $this->api->organizations();

        if (is_wp_error($organizations)) {
            return $organizations;
        }

        set_transient($cacheKey, $organizations, self::CACHE_TTL);

        return new WP_REST_Response($organizations);
    }

    /**
     * GET /entities/{resources|services|plans|categories}?search= — public,
     * unauthenticated upstream listings for the block entity pickers.
     */
    public function entities(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $type   = (string) $request->get_param('type');
        $search = (string) $request->get_param('search');

        $org     = $this->settings->org();
        $orgSlug = (string) ($org['slug'] ?? '');

        if ($orgSlug === '') {
            return new WP_Error(
                'anny_no_org',
                __('Select an anny organization on the settings page first.', 'anny-bookings'),
                ['status' => 400]
            );
        }

        $cacheKey = $this->cacheKey($type, $search);
        $cached   = get_transient($cacheKey);

        if (is_array($cached)) {
            return new WP_REST_Response($cached);
        }

        $orgPath = '/organizations/' . rawurlencode($orgSlug);

        // Primary relationship route with a fallback on 4xx responses.
        $paths = match ($type) {
            'resources'  => [$orgPath . '/all-resources', $orgPath . '/resources'],
            'services'   => [$orgPath . '/all-services', $orgPath . '/services'],
            'plans'      => [$orgPath . '/offered-plans'],
            'categories' => [$orgPath . '/used-categories'],
            default      => [],
        };

        if ($paths === []) {
            return new WP_Error('anny_bad_type', __('Unknown entity type.', 'anny-bookings'), ['status' => 400]);
        }

        $doc   = null;
        $error = null;

        foreach ($paths as $path) {
            // Deliberately unauthenticated — see class docblock.
            $result = $this->api->get($path, ['page' => ['size' => 100]], false);

            if (!is_wp_error($result)) {
                $doc = $result;
                break;
            }

            $error  = $result;
            $status = (int) (((array) $result->get_error_data())['status'] ?? 0);

            if ($status < 400 || $status >= 500) {
                return $result; // Only 4xx warrants trying the fallback route.
            }
        }

        if ($doc === null) {
            return $error ?? new WP_Error('anny_upstream', __('The anny API request failed.', 'anny-bookings'), ['status' => 502]);
        }

        $items = [];

        foreach (ApiClient::listFrom($doc) as $item) {
            $slug = (string) $item['slug'];
            $name = (string) $item['name'];

            // Client-side search filter (upstream filtering unsupported).
            if (
                $search !== ''
                && stripos($name, $search) === false
                && stripos($slug, $search) === false
            ) {
                continue;
            }

            $items[] = [
                'slug' => $slug,
                'name' => $name,
            ];
        }

        set_transient($cacheKey, $items, self::CACHE_TTL);

        return new WP_REST_Response($items);
    }

    /**
     * GET /brand — the org's custom styles as canonical design tokens
     * (auth-gated upstream, requires b.organizations:read).
     *
     * The organization is addressed by ID, not by slug: the upstream adapter
     * looks organizations up by slug only for unauthenticated requests and
     * switches to the numeric ID as soon as a Bearer token resolves a user, so
     * a slug would 404 here even though it works for the public entity
     * listings above.
     */
    public function brand(): WP_REST_Response|WP_Error
    {
        $org   = $this->settings->org();
        $orgId = (string) ($org['id'] ?? '');

        if ($orgId === '') {
            return new WP_Error(
                'anny_no_org',
                __('Select an anny organization on the settings page first.', 'anny-bookings'),
                ['status' => 400]
            );
        }

        // Cache key is versioned by payload shape: a cached response from an
        // older plugin version must never reach the current import script.
        $cacheKey = $this->cacheKey('brand-tokens', '');
        $cached   = get_transient($cacheKey);

        if (is_array($cached)) {
            return new WP_REST_Response($cached);
        }

        $doc = $this->api->get('/organizations/' . rawurlencode($orgId), [], true);

        if (is_wp_error($doc)) {
            return $doc;
        }

        $styles = $doc['data']['attributes']['custom_styles'] ?? null;

        $brand = ['tokens' => self::tokensFromCustomStyles(is_array($styles) ? $styles : [])];

        set_transient($cacheKey, $brand, self::CACHE_TTL);

        return new WP_REST_Response($brand);
    }

    /**
     * anny's `custom_styles` map → canonical design tokens.
     *
     * Upstream keys are camelCase (`primaryColorHover`) and cover both color
     * and layout tokens; only canonical ones survive, in canonical order. The
     * derived `*Rgb` siblings are dropped on purpose — Blocks recomputes them
     * from their hex source at render time.
     *
     * @param array<string, mixed> $styles
     *
     * @return array<string, string>
     */
    private static function tokensFromCustomStyles(array $styles): array
    {
        $normalized = [];

        foreach ($styles as $key => $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $normalized[self::kebabize((string) $key)] = trim($value);
        }

        $tokens = [];

        foreach ([...Settings::COLOR_TOKENS, ...Settings::TEXT_TOKENS] as $token) {
            if (isset($normalized[$token])) {
                $tokens[$token] = $normalized[$token];
            }
        }

        return $tokens;
    }

    /**
     * camelCase / snake_case → kebab-case, so upstream style keys can be
     * matched against canonical token names.
     */
    private static function kebabize(string $key): string
    {
        $key = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $key);

        return strtolower((string) preg_replace('/[_\s]+/', '-', $key));
    }

    /**
     * Transient cache key per contract:
     * anny_ + md5(cache_version|region|orgSlug|type|search).
     */
    private function cacheKey(string $type, string $search): string
    {
        $org = $this->settings->org();

        return 'anny_' . md5(
            implode(
                '|',
                [
                    (string) $this->settings->cacheVersion(),
                    $this->settings->region(),
                    (string) ($org['slug'] ?? ''),
                    $type,
                    $search,
                ]
            )
        );
    }
}
