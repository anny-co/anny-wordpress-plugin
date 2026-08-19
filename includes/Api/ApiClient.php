<?php

declare(strict_types=1);

namespace Anny\Api;

\defined('ABSPATH') || exit;

use Anny\Oauth\OauthClient;
use Anny\Settings;
use WP_Error;

/**
 * Thin JSON:API client for the anny bookings API of the selected region.
 *
 * Authenticated requests carry the Bearer token and the `?o={org id}`
 * context parameter; the public entity listings are deliberately called
 * WITHOUT a Bearer (a narrowly scoped token would 403 on the relationship
 * routes' scope check, while unauthenticated requests skip it).
 */
final class ApiClient
{
    public function __construct(
        private readonly Settings $settings,
        private readonly OauthClient $oauth,
    ) {
    }

    /**
     * GET {api}{path} and decode the JSON body.
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>|WP_Error
     */
    public function get(string $path, array $query = [], bool $withAuth = false): array|WP_Error
    {
        $region = $this->settings->regionConfig();

        if ($region === null) {
            return new WP_Error('anny_no_region', __('No anny region is configured.', 'anny-bookings'), ['status' => 500]);
        }

        $headers = [
            'Accept'          => 'application/vnd.api+json',
            'Accept-Language' => get_locale(),
        ];

        if ($withAuth) {
            $token = $this->oauth->getValidAccessToken();

            if ($token === null) {
                return new WP_Error('anny_not_connected', __('This site is not connected to anny.', 'anny-bookings'), ['status' => 401]);
            }

            $headers['Authorization'] = 'Bearer ' . $token;

            // Organization context is only meaningful on authenticated calls.
            $org = $this->settings->org();

            if ($org !== null && ($org['id'] ?? '') !== '') {
                $query['o'] = $org['id'];
            }
        }

        $url = $region['api'] . $path;

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 10,
                'headers' => $headers,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'anny_upstream',
                sprintf(
                    /* translators: %d: HTTP status code. */
                    __('The anny API request failed (HTTP %d).', 'anny-bookings'),
                    $code
                ),
                ['status' => $code]
            );
        }

        return is_array($body) ? $body : [];
    }

    /**
     * Organizations of the connected user (Bearer, includes org relationship).
     *
     * @return list<array{id: string, uuid: string, slug: string, name: string}>|WP_Error
     */
    public function organizations(): array|WP_Error
    {
        $doc = $this->get('/user', ['include' => 'organization_users.organization'], true);

        if (is_wp_error($doc)) {
            return $doc;
        }

        return self::organizationsFromUserDoc($doc);
    }

    /**
     * Extract the included organizations from a `/user?include=…` document.
     *
     * @param array<string, mixed> $doc
     *
     * @return list<array{id: string, uuid: string, slug: string, name: string}>
     */
    public static function organizationsFromUserDoc(array $doc): array
    {
        $organizations = [];
        $included      = is_array($doc['included'] ?? null) ? $doc['included'] : [];

        foreach ($included as $item) {
            if (!is_array($item) || ($item['type'] ?? '') !== 'organizations') {
                continue;
            }

            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];

            $organizations[] = [
                'id'   => (string) ($item['id'] ?? ''),
                // The Cognitor tenant UUID is exposed as `external_id`.
                'uuid' => (string) ($attributes['uuid'] ?? $attributes['external_id'] ?? ''),
                'slug' => (string) ($attributes['slug'] ?? ''),
                'name' => (string) ($attributes['name'] ?? ''),
            ];
        }

        return $organizations;
    }

    /**
     * Flatten a JSON:API list document into `[{id, slug, name, …attrs}]`.
     *
     * @param array<string, mixed> $doc
     *
     * @return list<array<string, mixed>>
     */
    public static function listFrom(array $doc): array
    {
        $items = [];
        $data  = is_array($doc['data'] ?? null) ? $doc['data'] : [];

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];

            $items[] = array_merge(
                $attributes,
                [
                    'id'   => (string) ($item['id'] ?? ''),
                    'slug' => (string) ($attributes['slug'] ?? ''),
                    'name' => (string) ($attributes['name'] ?? ''),
                ]
            );
        }

        return $items;
    }
}
