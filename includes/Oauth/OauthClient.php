<?php

declare(strict_types=1);

namespace Anny\Oauth;

\defined('ABSPATH') || exit;

use Anny\Regions;
use Anny\Settings;
use Anny\TokenStore;
use WP_Error;

/**
 * OAuth 2.0 authorization-code client with PKCE (public client, no secret)
 * against the Cognitor auth host of the selected region.
 */
final class OauthClient
{
    /**
     * Scopes requested from Cognitor. Always sent explicitly — Cognitor's
     * default would be `['*']`.
     *
     * `user:read` covers the identity lookup behind the organization picker
     * and `b.organizations:read` the brand import (`GET /organizations/{id}`);
     * those two are what the plugin authenticates with today. The resource and
     * service scopes are requested ahead of the features that will read them.
     *
     * The entity pickers are proxied unauthenticated and are therefore never
     * scope-checked — see Anny\Rest\EntitiesController. Note that the bookings
     * API derives the required scope from the *primary* resource type of a
     * route, so authenticating those org-relationship listings later would
     * need `b.organizations:all-resources` and friends, not `b.resources:index`.
     */
    public const SCOPES = 'user:read b.organizations:read b.resources:index b.resources:read b.services:index b.services:read';

    private const LOCK_OPTION = 'anny_refresh_lock';

    private const STATE_TTL = 10 * MINUTE_IN_SECONDS;

    /** Refresh this many seconds before the access token expires. */
    private const EXPIRY_LEEWAY = 60;

    /** A refresh lock older than this is considered stale and taken over. */
    private const LOCK_TTL = 30;

    public function __construct(
        private readonly Settings $settings,
        private readonly TokenStore $tokens,
        private readonly Regions $regions,
    ) {
    }

    /**
     * admin-post handler: start the authorization-code flow.
     */
    public function handleConnect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to connect this site to anny.', 'anny-bookings'), 403);
        }

        check_admin_referer('anny_connect');

        $region = $this->settings->regionConfig();

        if ($region === null || $region['client_id'] === '') {
            wp_safe_redirect(add_query_arg('anny-error', 'not_configured', $this->settingsUrl()));
            exit;
        }

        $verifier    = $this->randomVerifier(64);
        $state       = bin2hex(random_bytes(16));
        $redirectUri = rest_url('anny/v1/oauth/callback');

        set_transient(
            'anny_oauth_' . $state,
            [
                'verifier'     => $verifier,
                'user_id'      => get_current_user_id(),
                'region'       => $this->settings->region(),
                'redirect_uri' => $redirectUri,
            ],
            self::STATE_TTL
        );

        $challenge = $this->base64UrlEncode(hash('sha256', $verifier, true));

        $authorizeUrl = $region['auth'] . '/oauth/authorize?' . http_build_query(
            [
                'response_type'         => 'code',
                'client_id'             => $region['client_id'],
                'redirect_uri'          => $redirectUri,
                'scope'                 => self::SCOPES,
                'state'                 => $state,
                'code_challenge'        => $challenge,
                'code_challenge_method' => 'S256',
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        // The authorize URL is the Cognitor host of the selected region, built
        // from the plugin-shipped region map — never from request input — so
        // it cannot be steered by a visitor. wp_safe_redirect() would refuse
        // it outright, since the host is by definition not this site.
        // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Deliberate off-site redirect to the OAuth provider.
        wp_redirect($authorizeUrl);
        exit;
    }

    /**
     * admin-post handler: disconnect and revoke.
     */
    public function handleDisconnect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to disconnect this site from anny.', 'anny-bookings'), 403);
        }

        check_admin_referer('anny_disconnect');

        $this->disconnect();

        wp_safe_redirect(add_query_arg('anny-disconnected', '1', $this->settingsUrl()));
        exit;
    }

    /**
     * Exchange an authorization code for tokens using the state payload
     * stored at connect time.
     *
     * @param array{verifier: string, user_id: int, region: string, redirect_uri: string} $state
     */
    public function exchangeCode(string $code, array $state): bool|WP_Error
    {
        $region = $this->regions->get((string) ($state['region'] ?? ''));

        if ($region === null || $region['client_id'] === '') {
            return new WP_Error('anny_no_region', __('The anny region of this login attempt is not configured.', 'anny-bookings'));
        }

        $data = $this->requestTokens(
            $region,
            [
                'grant_type'    => 'authorization_code',
                'client_id'     => $region['client_id'],
                'redirect_uri'  => (string) ($state['redirect_uri'] ?? ''),
                'code'          => $code,
                'code_verifier' => (string) ($state['verifier'] ?? ''),
            ]
        );

        if (is_wp_error($data)) {
            return $data;
        }

        $stored = $this->storeTokenResponse($data);

        if (is_wp_error($stored)) {
            return $stored;
        }

        $this->tokens->clearReconnectFlag();

        return true;
    }

    /**
     * Currently valid access token, transparently refreshing when needed.
     * Null means "not connected" (or the refresh failed).
     */
    public function getValidAccessToken(): ?string
    {
        $tokens = $this->tokens->load();

        if ($tokens === null || ($tokens['access_token'] ?? '') === '') {
            return null;
        }

        if ((int) ($tokens['expires_at'] ?? 0) - self::EXPIRY_LEEWAY > time()) {
            return (string) $tokens['access_token'];
        }

        return $this->refresh();
    }

    public function isConnected(): bool
    {
        $tokens = $this->tokens->load();

        return $tokens !== null && ($tokens['access_token'] ?? '') !== '';
    }

    /**
     * Refresh the token pair. Guarded by an atomic add_option() lock so
     * concurrent requests do not race on the rotating refresh token.
     */
    public function refresh(): ?string
    {
        $tokens = $this->tokens->load();

        if ($tokens === null || ($tokens['refresh_token'] ?? '') === '') {
            return null;
        }

        $region = $this->settings->regionConfig();

        if ($region === null || $region['client_id'] === '') {
            return null;
        }

        if (!add_option(self::LOCK_OPTION, (string) time(), '', false)) {
            $lockedAt = (int) get_option(self::LOCK_OPTION);

            if ($lockedAt > time() - self::LOCK_TTL) {
                // Another request is refreshing right now. Use the current
                // token if it is still within its hard lifetime.
                return (int) ($tokens['expires_at'] ?? 0) > time()
                    ? (string) $tokens['access_token']
                    : null;
            }

            update_option(self::LOCK_OPTION, (string) time(), false); // Stale lock: take over.
        }

        try {
            $data = $this->requestTokens(
                $region,
                [
                    'grant_type'    => 'refresh_token',
                    'client_id'     => $region['client_id'],
                    'refresh_token' => (string) $tokens['refresh_token'],
                ]
            );

            if (is_wp_error($data)) {
                if ($data->get_error_code() === 'anny_invalid_grant') {
                    // Refresh token revoked/expired upstream: force reconnect.
                    $this->tokens->clear();
                    $this->tokens->flagReconnect();
                }

                return null;
            }

            $stored = $this->storeTokenResponse($data);

            return is_wp_error($stored) ? null : (string) $data['access_token'];
        } finally {
            delete_option(self::LOCK_OPTION);
        }
    }

    /**
     * Best-effort revocation of the current access token at Cognitor.
     */
    public function revokeRemote(): void
    {
        $tokens = $this->tokens->load();
        $region = $this->settings->regionConfig();

        if ($tokens === null || $region === null || ($tokens['jti'] ?? '') === '') {
            return;
        }

        wp_remote_request(
            $region['auth'] . '/api/oauth/tokens/' . rawurlencode((string) $tokens['jti']),
            [
                'method'  => 'DELETE',
                'timeout' => 10,
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $tokens['access_token'],
                ],
            ]
        );
    }

    /**
     * Revoke upstream (best effort), purge tokens and connection state.
     */
    public function disconnect(): void
    {
        $this->revokeRemote();
        $this->tokens->clear();
        $this->tokens->clearReconnectFlag();
        $this->settings->update(
            [
                'org'            => null,
                'connected_user' => null,
            ]
        );
        $this->settings->bumpCacheVersion();
    }

    public function settingsUrl(): string
    {
        // Connection state changes always land on the Connection tab.
        return admin_url('admin.php?page=anny&tab=connection');
    }

    /**
     * @return array<string, mixed> Decoded JWT payload (empty on failure).
     */
    public function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return [];
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * POST to the region's token endpoint and decode the response.
     *
     * @param array{label: string, widget_base: string, api: string, auth: string, client_id: string} $region
     * @param array<string, string>                                                                   $body
     *
     * @return array<string, mixed>|WP_Error
     */
    private function requestTokens(array $region, array $body): array|WP_Error
    {
        $response = wp_remote_post(
            $region['auth'] . '/oauth/token',
            [
                'timeout' => 10,
                'headers' => ['Accept' => 'application/json'],
                'body'    => $body,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $data = is_array($data) ? $data : [];

        if ($code < 200 || $code >= 300 || ($data['access_token'] ?? '') === '') {
            $error = (string) ($data['error'] ?? '');

            return new WP_Error(
                $error === 'invalid_grant' ? 'anny_invalid_grant' : 'anny_token_error',
                sprintf(
                    /* translators: %d: HTTP status code. */
                    __('The anny token endpoint returned an error (HTTP %d).', 'anny-bookings'),
                    $code
                ),
                ['status' => $code, 'error' => $error]
            );
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data Token endpoint response.
     */
    private function storeTokenResponse(array $data): bool|WP_Error
    {
        $accessToken = (string) ($data['access_token'] ?? '');

        if ($accessToken === '') {
            return new WP_Error('anny_token_error', __('The anny token response did not contain an access token.', 'anny-bookings'));
        }

        $payload = $this->decodeJwtPayload($accessToken);

        $this->tokens->save(
            [
                'access_token'  => $accessToken,
                'refresh_token' => (string) ($data['refresh_token'] ?? ''),
                'expires_at'    => time() + (int) ($data['expires_in'] ?? 0),
                'jti'           => (string) ($payload['jti'] ?? ''),
            ]
        );

        return true;
    }

    /**
     * PKCE code verifier from the RFC 7636 unreserved alphabet.
     */
    private function randomVerifier(int $length): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
        $max      = strlen($alphabet) - 1;
        $verifier = '';

        for ($i = 0; $i < $length; $i++) {
            $verifier .= $alphabet[random_int(0, $max)];
        }

        return $verifier;
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $encoded): string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), false);

        return $decoded === false ? '' : $decoded;
    }
}
