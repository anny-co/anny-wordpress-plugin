<?php

declare(strict_types=1);

namespace Anny\Rest;

\defined('ABSPATH') || exit;

use Anny\Api\ApiClient;
use Anny\Oauth\OauthClient;
use Anny\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Public OAuth redirect endpoint. CSRF protection comes from the single-use
 * state transient created at connect time (plus PKCE on the token exchange);
 * cookie authentication is unavailable on a cross-site redirect anyway.
 */
final class OauthController
{
    public const REST_NAMESPACE = 'anny/v1';

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
            '/oauth/callback',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'callback'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'code'  => ['type' => 'string'],
                    'state' => ['type' => 'string'],
                    'error' => ['type' => 'string'],
                ],
            ]
        );
    }

    /**
     * GET /oauth/callback — browser redirect target of the authorize flow.
     */
    public function callback(WP_REST_Request $request): WP_REST_Response
    {
        $state = (string) $request->get_param('state');

        if ($state === '' || preg_match('/^[0-9a-f]{32}$/', $state) !== 1) {
            return $this->redirectWithError('missing_state');
        }

        $stateData = get_transient('anny_oauth_' . $state);

        // Single use: consume the state before doing anything else.
        delete_transient('anny_oauth_' . $state);

        if (!is_array($stateData)) {
            return $this->redirectWithError('invalid_state');
        }

        $error = (string) $request->get_param('error');

        if ($error !== '') {
            return $this->redirectWithError(sanitize_key($error));
        }

        $code = (string) $request->get_param('code');

        if ($code === '') {
            return $this->redirectWithError('missing_code');
        }

        $exchanged = $this->oauth->exchangeCode($code, $stateData);

        if (is_wp_error($exchanged)) {
            return $this->redirectWithError('token_exchange_failed');
        }

        $this->persistConnectedUser();
        $this->settings->bumpCacheVersion();

        return $this->redirect(add_query_arg('anny-connected', '1', $this->oauth->settingsUrl()));
    }

    /**
     * Fetch the connected user (incl. organizations) once and persist the
     * display data plus a default organization.
     */
    private function persistConnectedUser(): void
    {
        $doc = $this->api->get('/user', ['include' => 'organization_users.organization'], true);

        if (is_wp_error($doc)) {
            return; // Connection still works; user info is cosmetic.
        }

        $attributes = is_array($doc['data']['attributes'] ?? null) ? $doc['data']['attributes'] : [];

        $update = [
            'connected_user' => [
                'name'  => sanitize_text_field((string) ($attributes['name'] ?? '')),
                'email' => sanitize_email((string) ($attributes['email'] ?? '')),
            ],
        ];

        $organizations = ApiClient::organizationsFromUserDoc($doc);

        if ($this->settings->org() === null && $organizations !== []) {
            $update['org'] = $organizations[0];
        }

        $this->settings->update($update);
    }

    private function redirectWithError(string $code): WP_REST_Response
    {
        return $this->redirect(add_query_arg('anny-error', rawurlencode($code), $this->oauth->settingsUrl()));
    }

    private function redirect(string $url): WP_REST_Response
    {
        $response = new WP_REST_Response(null, 302);
        $response->header('Location', $url);

        return $response;
    }
}
