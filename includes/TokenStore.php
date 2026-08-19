<?php

declare(strict_types=1);

namespace Anny;

\defined('ABSPATH') || exit;

/**
 * Encrypted at-rest storage for the OAuth token set.
 *
 * Tokens are sealed with libsodium secretbox using a key derived from the
 * site's AUTH_KEY/AUTH_SALT and stored in a non-autoloaded option. Tokens
 * never reach JavaScript. A blob that fails to decrypt (e.g. after the salts
 * were rotated) is discarded and a reconnect notice is raised.
 */
final class TokenStore
{
    public const OPTION = 'anny_tokens';

    private const RECONNECT_TRANSIENT = 'anny_reconnect_notice';

    /**
     * @param array{access_token: string, refresh_token: string, expires_at: int, jti: string} $tokens
     */
    public function save(array $tokens): void
    {
        $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox((string) wp_json_encode($tokens), $nonce, $this->key());
        $blob   = base64_encode($nonce . $cipher);

        if (get_option(self::OPTION, null) === null) {
            add_option(self::OPTION, $blob, '', false);
        } else {
            update_option(self::OPTION, $blob, false);
        }
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_at: int, jti: string}|null
     */
    public function load(): ?array
    {
        $blob = get_option(self::OPTION);

        if (!is_string($blob) || $blob === '') {
            return null;
        }

        $raw = base64_decode($blob, true);

        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            $this->discardCorrupt();

            return null;
        }

        $nonce  = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain  = sodium_crypto_secretbox_open($cipher, $nonce, $this->key());

        if ($plain === false) {
            $this->discardCorrupt();

            return null;
        }

        $tokens = json_decode($plain, true);

        return is_array($tokens) ? $tokens : null;
    }

    public function has(): bool
    {
        $blob = get_option(self::OPTION);

        return is_string($blob) && $blob !== '';
    }

    public function clear(): void
    {
        delete_option(self::OPTION);
    }

    public function needsReconnect(): bool
    {
        return (bool) get_transient(self::RECONNECT_TRANSIENT);
    }

    public function flagReconnect(): void
    {
        set_transient(self::RECONNECT_TRANSIENT, 1, DAY_IN_SECONDS);
    }

    public function clearReconnectFlag(): void
    {
        delete_transient(self::RECONNECT_TRANSIENT);
    }

    private function discardCorrupt(): void
    {
        $this->clear();
        $this->flagReconnect();
    }

    private function key(): string
    {
        $material = (defined('AUTH_KEY') ? AUTH_KEY : '') . (defined('AUTH_SALT') ? AUTH_SALT : '');

        return sodium_crypto_generichash($material, '', 32);
    }
}
