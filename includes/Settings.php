<?php

declare(strict_types=1);

namespace Anny;

\defined('ABSPATH') || exit;

/**
 * CRUD + sanitization for the `anny_settings` option.
 *
 * Option shape (see CONTRACT):
 * region (string key into the region map), org (array|null), connected_user
 * (array|null), tokens_design (kebab-case token => value, only set ones),
 * defaults (locale/should_login/idp_uuid), cache_version (int).
 */
final class Settings
{
    public const OPTION = 'anny_settings';

    /**
     * Canonical design tokens edited with a color picker. Values are stored as
     * hex, because wp-color-picker has no alpha channel.
     *
     * @var list<string>
     */
    public const COLOR_TOKENS = [
        'primary-color',
        'primary-color-hover',
        'secondary-color',
        'primary-stroke-color',
        'text-primary-color',
        'text-secondary-color',
        'text-tertiary-color',
        'primary-background',
        'panel-background',
        'panel-background-light',
        'panel-background-dark',
        'input-background',
        'light-border-color',
        'table-border-color',
        'button-background',
        'button-text',
    ];

    /**
     * Canonical design tokens edited as text/unit inputs.
     *
     * The three overlay tokens live here rather than in COLOR_TOKENS: anny
     * brands express them as `rgba(…)`, which a hex-only color picker cannot
     * round-trip (it would silently drop the alpha channel on import).
     *
     * @var list<string>
     */
    public const TEXT_TOKENS = [
        'primary-color-overlay',
        'panel-background-overlay',
        'panel-background-overlay-dense',
        'small-border-radius',
        'panel-border-radius',
        'detail-border-radius',
        'panel-border-style',
        'panel-shadow',
        'detail-shadow',
        'button-width',
        'button-height',
        'logo-url',
    ];

    /**
     * Derived tokens (never editable): derived token => hex sibling it is
     * computed from at render time when unset.
     *
     * @var array<string, string>
     */
    public const DERIVED_TOKENS = [
        'primary-color-rgb'      => 'primary-color',
        'primary-background-rgb' => 'primary-background',
        'panel-background-rgb'   => 'panel-background',
    ];

    /** True while update() writes, so sanitize() passes the value through. */
    private bool $trustedWrite = false;

    public function __construct(private readonly Regions $regions)
    {
    }

    /**
     * Stored settings merged over the defaults.
     *
     * @return array{region: string, org: ?array<string, string>, connected_user: ?array<string, string>, tokens_design: array<string, string>, defaults: array{locale: string, should_login: bool, idp_uuid: string}, cache_version: int}
     */
    public function all(): array
    {
        $stored = get_option(self::OPTION, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        $defaults = [
            'region'         => 'anny.co',
            'org'            => null,
            'connected_user' => null,
            'tokens_design'  => [],
            'defaults'       => [
                'locale'       => '',
                'should_login' => false,
                'idp_uuid'     => '',
            ],
            'cache_version'  => 1,
        ];

        $merged             = array_merge($defaults, $stored);
        $merged['defaults'] = array_merge(
            $defaults['defaults'],
            is_array($merged['defaults']) ? $merged['defaults'] : []
        );

        if (!is_array($merged['tokens_design'])) {
            $merged['tokens_design'] = [];
        }

        if (!is_array($merged['org'])) {
            $merged['org'] = null;
        }

        if (!is_array($merged['connected_user'])) {
            $merged['connected_user'] = null;
        }

        $merged['cache_version'] = max(1, (int) $merged['cache_version']);

        return $merged;
    }

    /**
     * Merge a partial update into the stored option.
     *
     * Programmatic writes must not be rewritten by the Settings-API sanitize
     * callback (registered on admin_init, which also fires on admin-post.php),
     * or purging connected_user / bumping cache_version would be reverted.
     *
     * @param array<string, mixed> $partial
     */
    public function update(array $partial): void
    {
        $this->trustedWrite = true;

        try {
            update_option(self::OPTION, array_merge($this->all(), $partial));
        } finally {
            $this->trustedWrite = false;
        }
    }

    public function region(): string
    {
        return $this->all()['region'];
    }

    /**
     * Config of the currently selected region.
     *
     * @return array{label: string, widget_base: string, api: string, auth: string, client_id: string}|null
     */
    public function regionConfig(): ?array
    {
        return $this->regions->get($this->region());
    }

    /**
     * @return array{id: string, uuid: string, slug: string, name: string}|null
     */
    public function org(): ?array
    {
        $org = $this->all()['org'];

        return is_array($org) ? $org : null;
    }

    /**
     * @return array{name: string, email: string}|null
     */
    public function connectedUser(): ?array
    {
        $user = $this->all()['connected_user'];

        return is_array($user) ? $user : null;
    }

    /**
     * Global design tokens (only explicitly set ones).
     *
     * @return array<string, string>
     */
    public function designTokens(): array
    {
        return $this->all()['tokens_design'];
    }

    /**
     * @return array{locale: string, should_login: bool, idp_uuid: string}
     */
    public function defaults(): array
    {
        return $this->all()['defaults'];
    }

    /**
     * WordPress locales whose anny counterpart is more than the language part.
     *
     * anny ships formal German ("Deutsch (Sie)") as its own widget locale, and
     * expects `ua` rather than the ISO `uk` for Ukrainian. Everything else maps
     * by taking the language part, which keeps this table free of locales anny
     * may add later.
     *
     * @var array<string, string>
     */
    private const WIDGET_LOCALES = [
        'de_DE_formal'   => 'de-formal',
        'de_CH_informal' => 'de',
        'uk'             => 'ua',
        'uk_UA'          => 'ua',
    ];

    /**
     * Effective default widget locale: explicit override or the site language.
     */
    public function defaultLocale(): string
    {
        $locale = $this->defaults()['locale'];

        if ($locale !== '') {
            return $locale;
        }

        $siteLocale = get_locale();

        return self::WIDGET_LOCALES[$siteLocale] ?? substr($siteLocale, 0, 2);
    }

    public function cacheVersion(): int
    {
        return $this->all()['cache_version'];
    }

    public function bumpCacheVersion(): void
    {
        $this->update(['cache_version' => $this->cacheVersion() + 1]);
    }

    /**
     * Strict sanitizer for the Settings API.
     *
     * Unknown keys are dropped, connected_user and cache_version are never
     * user-editable and always preserved from the stored option.
     *
     * @param mixed $input Raw option input.
     *
     * @return array<string, mixed>
     */
    public function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];

        if ($this->trustedWrite) {
            return $input;
        }

        $current = $this->all();
        $clean   = $current;

        // Region: must be one of the known region keys.
        if (isset($input['region']) && is_string($input['region'])) {
            $clean['region'] = array_key_exists($input['region'], $this->regions->all())
                ? $input['region']
                : $current['region'];
        }

        if (array_key_exists('org', $input)) {
            $clean['org'] = $this->sanitizeOrg($input['org']);
        }

        if (array_key_exists('tokens_design', $input)) {
            $clean['tokens_design'] = $this->sanitizeTokens(
                is_array($input['tokens_design']) ? $input['tokens_design'] : []
            );
        }

        if (array_key_exists('defaults', $input)) {
            $defaults          = is_array($input['defaults']) ? $input['defaults'] : [];
            $clean['defaults'] = [
                'locale'       => $this->sanitizeLocale((string) ($defaults['locale'] ?? '')),
                'should_login' => !empty($defaults['should_login']),
                'idp_uuid'     => $this->sanitizeUuid((string) ($defaults['idp_uuid'] ?? '')),
            ];
        }

        // Never user-editable.
        $clean['connected_user'] = $current['connected_user'];
        $clean['cache_version']  = $current['cache_version'];

        return $clean;
    }

    /**
     * Accepts the org either as an array or as the JSON-encoded select value
     * posted by the settings screen.
     *
     * @return array{id: string, uuid: string, slug: string, name: string}|null
     */
    private function sanitizeOrg(mixed $org): ?array
    {
        if (is_string($org)) {
            // Settings API input arrives already unslashed (wp-admin/options.php).
            $org = $org === '' ? null : json_decode($org, true);
        }

        if (!is_array($org)) {
            return null;
        }

        $clean = [
            'id'   => sanitize_text_field((string) ($org['id'] ?? '')),
            'uuid' => sanitize_text_field((string) ($org['uuid'] ?? '')),
            'slug' => sanitize_text_field((string) ($org['slug'] ?? '')),
            'name' => sanitize_text_field((string) ($org['name'] ?? '')),
        ];

        return ($clean['id'] === '' && $clean['slug'] === '') ? null : $clean;
    }

    /**
     * Only canonical tokens survive; colors must be valid hex, the logo must
     * be a URL, everything else is plain text. Empty values are dropped.
     *
     * @param array<string, mixed> $tokens
     *
     * @return array<string, string>
     */
    private function sanitizeTokens(array $tokens): array
    {
        $clean = [];

        foreach (self::COLOR_TOKENS as $token) {
            $value = isset($tokens[$token]) ? trim((string) $tokens[$token]) : '';

            if ($value === '') {
                continue;
            }

            $color = sanitize_hex_color($value);

            if (is_string($color) && $color !== '') {
                $clean[$token] = $color;
            }
        }

        foreach (self::TEXT_TOKENS as $token) {
            $value = isset($tokens[$token]) ? trim((string) $tokens[$token]) : '';

            if ($value === '') {
                continue;
            }

            $clean[$token] = $token === 'logo-url'
                ? esc_url_raw($value)
                : sanitize_text_field($value);
        }

        return array_filter($clean, static fn(string $value): bool => $value !== '');
    }

    /**
     * Locale codes are language tags, optionally with a variant suffix — the
     * limit has to leave room for anny's longest one, `de-formal`.
     */
    private function sanitizeLocale(string $locale): string
    {
        $locale = strtolower((string) preg_replace('/[^A-Za-z-]/', '', $locale));

        return substr($locale, 0, 12);
    }

    private function sanitizeUuid(string $uuid): string
    {
        $uuid = strtolower(trim($uuid));

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid) === 1
            ? $uuid
            : '';
    }
}
