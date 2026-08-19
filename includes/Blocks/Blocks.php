<?php

declare(strict_types=1);

namespace Anny\Blocks;

\defined('ABSPATH') || exit;

use Anny\Oauth\OauthClient;
use Anny\Regions;
use Anny\Settings;

/**
 * Registers all anny blocks from the manifest in a loop, wires the widget
 * scripts and implements the server-side render callback.
 */
final class Blocks
{
    public const CATEGORY = 'anny';

    /** Script handle of the anny web-components CDN bundle. */
    public const COMPONENTS_HANDLE = 'anny-components';

    /** Script handle of the shared editor bundle. */
    public const EDITOR_HANDLE = 'anny-editor';

    /**
     * Manifest options that are booleans; everything else is a string.
     *
     * @var list<string>
     */
    private const BOOLEAN_OPTIONS = [
        'fullscreen',
        'should-login',
        'close-on-backdrop',
        'show-close-button',
        'show-icon',
    ];

    public function __construct(
        private readonly Manifest $manifest,
        private readonly Settings $settings,
        private readonly Regions $regions,
        private readonly OauthClient $oauth,
    ) {
    }

    /**
     * init: register the CDN components bundle (deferred, in footer, no
     * version query — the CDN serves a "latest" build). It is only enqueued
     * from render callbacks and inside the editor canvas.
     *
     * The version stays null on purpose: the URL points at anny's rolling
     * "latest" build, whose cache lifetime the CDN owns. Appending the plugin
     * version would claim a relationship between the two that does not exist,
     * and would bust the visitor's cache on every plugin release without the
     * script having changed.
     */
    public function registerScripts(): void
    {
        wp_register_script(
            self::COMPONENTS_HANDLE,
            $this->regions->scriptUrl($this->settings->region()),
            [],
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Third-party CDN URL, versioned by the CDN itself.
            null,
            [
                'in_footer' => true,
                'strategy'  => 'defer',
            ]
        );
    }

    /**
     * init: register all blocks from the manifest.
     */
    public function register(): void
    {
        $labels = $this->manifest->labels();

        foreach ($this->manifest->entries() as $slug => $widget) {
            $slug = (string) $slug;

            if (!is_array($widget)) {
                continue;
            }

            register_block_type(
                'anny/' . $slug,
                [
                    'api_version'     => 3,
                    'title'           => $labels[$slug]['title'] ?? $slug,
                    'description'     => $labels[$slug]['description'] ?? '',
                    'category'        => self::CATEGORY,
                    'attributes'      => $this->buildAttributes($widget),
                    'supports'        => [
                        'html'   => false,
                        'anchor' => true,
                        'align'  => !empty($widget['align']) ? ['wide', 'full'] : false,
                    ],
                    'render_callback' => fn(array $attributes, string $content): string => $this->render($slug, $widget, $attributes, $content),
                ]
            );
        }
    }

    /**
     * block_categories_all: prepend the "anny" category.
     *
     * @param array<int, array<string, mixed>> $categories
     *
     * @return array<int, array<string, mixed>>
     */
    public function filterCategories(array $categories): array
    {
        foreach ($categories as $category) {
            if (($category['slug'] ?? '') === self::CATEGORY) {
                return $categories;
            }
        }

        array_unshift(
            $categories,
            [
                'slug'  => self::CATEGORY,
                'title' => __('anny', 'anny-bookings'),
                'icon'  => null,
            ]
        );

        return $categories;
    }

    /**
     * enqueue_block_assets: fires for the frontend AND for the iframed
     * editor canvas. The is_admin() guard limits it to the editor — on the
     * frontend the components bundle is only enqueued by render callbacks of
     * pages that actually contain anny blocks.
     */
    public function enqueueCanvasAssets(): void
    {
        if (!is_admin()) {
            return;
        }

        wp_enqueue_script(self::COMPONENTS_HANDLE);

        // The editor stylesheet must load here (not in enqueue_block_editor_assets):
        // only enqueue_block_assets is replayed into the iframed editor canvas,
        // where the placeholder/preview markup actually renders.
        if (file_exists(ANNY_DIR . '/build/index.css')) {
            wp_enqueue_style(
                self::EDITOR_HANDLE,
                ANNY_URL . '/build/index.css',
                [],
                ANNY_VERSION
            );
        }
    }

    /**
     * enqueue_block_editor_assets: the shared editor bundle plus its
     * bootstrap data. Guarded so a missing build (JS builds separately)
     * never fatals.
     */
    public function enqueueEditorAssets(): void
    {
        $assetFile = ANNY_DIR . '/build/index.asset.php';

        if (!file_exists($assetFile)) {
            return;
        }

        $asset = include $assetFile;
        $asset = is_array($asset) ? $asset : [];

        wp_enqueue_script(
            self::EDITOR_HANDLE,
            ANNY_URL . '/build/index.js',
            $asset['dependencies'] ?? [],
            (string) ($asset['version'] ?? ANNY_VERSION),
            true
        );

        wp_set_script_translations(self::EDITOR_HANDLE, 'anny-bookings', ANNY_DIR . '/languages');

        wp_add_inline_script(
            self::EDITOR_HANDLE,
            'window.ANNY_WP = ' . wp_json_encode($this->editorBootstrapData()) . ';',
            'before'
        );
    }

    /**
     * Server-side renderer. Mirrors toElementAttributes() in src/manifest.js
     * — the two implementations MUST stay structurally in sync.
     *
     * @param array<string, mixed> $widget     Manifest entry.
     * @param array<string, mixed> $attributes Block attributes.
     * @param string               $content    Rendered InnerBlocks (button trigger).
     */
    public function render(string $slug, array $widget, array $attributes, string $content): string
    {
        $element = (string) ($widget['element'] ?? '');

        // The element tag comes exclusively from the plugin-shipped manifest.
        if (preg_match('/^a-[a-z][a-z-]*$/', $element) !== 1) {
            return '';
        }

        // Required target missing: render nothing but leave a hint. Mirrors
        // isUnconfigured() in src/components/edit.js.
        $unconfigured = !empty($widget['entity'])
            ? $this->entitySlug($widget, $attributes) === ''
            : ($slug === 'modal-button'
                && trim((string) ($attributes['resource'] ?? '')) === ''
                && trim((string) ($attributes['organization'] ?? '')) === '');

        if ($unconfigured) {
            return sprintf('<!-- anny: %s not configured -->', esc_html($slug));
        }

        wp_enqueue_script(self::COMPONENTS_HANDLE);

        $attributeHtml = '';

        foreach ($this->toElementAttributes($attributes, $widget) as $name => $value) {
            $attributeHtml .= sprintf(' %s="%s"', esc_attr((string) $name), esc_attr($value));
        }

        $inner = '';

        if (!empty($widget['innerBlocks']) && !empty($attributes['useCustomTrigger'])) {
            $inner = $content; // Slotted custom trigger, already rendered by core.
        }

        return sprintf(
            '<div %1$s><%2$s%3$s>%4$s</%2$s></div>',
            get_block_wrapper_attributes(),
            $element,
            $attributeHtml,
            $inner
        );
    }

    /**
     * Build the ordered HTML attribute map for a widget element.
     *
     * NOTE: this is the PHP twin of `toElementAttributes()` in
     * src/manifest.js. Any change here must be mirrored there.
     *
     * Order and semantics (per the shared contract):
     *  1. base-url (always)
     *  2. entity attribute (when the widget has an entity)
     *  3. manifest options in manifest order — booleans emit "true"/"false"
     *     only when explicitly set (should-login falls back to the global
     *     default and is therefore always emitted); locale falls back to the
     *     global default, then the site language; idp-uuid falls back to the
     *     global default; label is omitted with a custom trigger
     *  4. merged design tokens (global < per-block) with derived *-rgb values
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $widget
     *
     * @return array<string, string>
     */
    public function toElementAttributes(array $attributes, array $widget): array
    {
        $out    = [];
        $region = $this->settings->regionConfig();

        // 1. Region base URL.
        $out['base-url'] = (string) ($region['widget_base'] ?? '');

        // 2. Entity slug.
        if (!empty($widget['entity']) && !empty($widget['entityAttr'])) {
            $out[(string) $widget['entityAttr']] = $this->entitySlug($widget, $attributes);
        }

        // 3. Options in manifest order.
        $defaults         = $this->settings->defaults();
        $useCustomTrigger = !empty($widget['innerBlocks']) && !empty($attributes['useCustomTrigger']);
        $options          = is_array($widget['options'] ?? null) ? $widget['options'] : [];

        foreach ($options as $option) {
            $option = (string) $option;

            if ($option === 'label' && $useCustomTrigger) {
                continue; // The slotted InnerBlocks content is the trigger.
            }

            $camel = self::camelize($option);
            $isSet = array_key_exists($camel, $attributes)
                && $attributes[$camel] !== null
                && $attributes[$camel] !== '';

            if (in_array($option, self::BOOLEAN_OPTIONS, true)) {
                if ($isSet) {
                    $out[$option] = $attributes[$camel] ? 'true' : 'false';
                } elseif ($option === 'should-login') {
                    // Falls back to the global default, which is always a
                    // defined boolean — so should-login is always emitted
                    // (identical to the JS twin).
                    $out[$option] = !empty($defaults['should_login']) ? 'true' : 'false';
                }

                continue;
            }

            $value = $isSet ? (string) $attributes[$camel] : '';

            if ($value === '') {
                if ($option === 'locale') {
                    $value = $this->settings->defaultLocale();
                } elseif ($option === 'idp-uuid') {
                    $value = (string) ($defaults['idp_uuid'] ?? '');
                }
            }

            if ($value !== '') {
                $out[$option] = $value;
            }
        }

        // 4. Design tokens: global overlaid by per-block overrides.
        $blockTokens = is_array($attributes['tokens'] ?? null) ? $attributes['tokens'] : [];
        $blockTokens = array_filter(
            $blockTokens,
            static fn($value): bool => is_string($value) && $value !== ''
        );

        $tokens = array_merge($this->settings->designTokens(), $blockTokens);

        foreach (Settings::DERIVED_TOKENS as $derived => $source) {
            if (($tokens[$derived] ?? '') !== '') {
                continue;
            }

            $rgb = self::hexToRgb((string) ($tokens[$source] ?? ''));

            if ($rgb !== null) {
                $tokens[$derived] = $rgb;
            }
        }

        $allowedTokens = self::canonicalTokenKeys();

        foreach ($tokens as $key => $value) {
            $key   = (string) $key;
            $value = (string) $value;

            if ($value === '' || !in_array($key, $allowedTokens, true)) {
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * Effective entity slug of a block: the stored value, falling back to the
     * organization selected in the plugin settings for organization widgets —
     * the site as a whole is scoped to that organization, so its widgets
     * follow it instead of carrying their own copy of the slug.
     *
     * NOTE: this is the PHP twin of `entitySlugFor()` in src/manifest.js.
     *
     * @param array<string, mixed> $widget
     * @param array<string, mixed> $attributes
     */
    private function entitySlug(array $widget, array $attributes): string
    {
        $slug = trim((string) ($attributes['entitySlug'] ?? ''));

        if ($slug !== '' || ($widget['entity'] ?? '') !== 'organization') {
            return $slug;
        }

        $org = $this->settings->org();

        return trim((string) ($org['slug'] ?? ''));
    }

    /**
     * Editor bootstrap data injected as window.ANNY_WP.
     *
     * @return array<string, mixed>
     */
    public function editorBootstrapData(): array
    {
        $region   = $this->settings->regionConfig();
        $defaults = $this->settings->defaults();

        return [
            'connected'     => $this->oauth->isConnected(),
            'region'        => $this->settings->region(),
            'widgetBase'    => (string) ($region['widget_base'] ?? ''),
            'org'           => $this->settings->org(),
            'defaults'      => [
                'locale'      => $this->settings->defaultLocale(),
                'shouldLogin' => (bool) ($defaults['should_login'] ?? false),
                'idpUuid'     => (string) ($defaults['idp_uuid'] ?? ''),
            ],
            'tokens'        => (object) $this->settings->designTokens(),
            'settingsUrl'   => $this->oauth->settingsUrl(),
            'restNamespace' => 'anny/v1',
        ];
    }

    /**
     * Block attribute schema for a widget — kept identical to
     * buildAttributes() in the editor bundle.
     *
     * @param array<string, mixed> $widget
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildAttributes(array $widget): array
    {
        $attributes = [];

        if (!empty($widget['entity'])) {
            $attributes['entitySlug'] = ['type' => 'string'];
        }

        $options = is_array($widget['options'] ?? null) ? $widget['options'] : [];

        foreach ($options as $option) {
            $option = (string) $option;

            $attributes[self::camelize($option)] = [
                'type' => in_array($option, self::BOOLEAN_OPTIONS, true) ? 'boolean' : 'string',
            ];
        }

        $attributes['tokens'] = [
            'type'    => 'object',
            'default' => [],
        ];

        if (!empty($widget['innerBlocks'])) {
            $attributes['useCustomTrigger'] = [
                'type'    => 'boolean',
                'default' => false,
            ];
        }

        return $attributes;
    }

    /**
     * kebab-case option name → camelCase block attribute name.
     */
    private static function camelize(string $kebab): string
    {
        return lcfirst(str_replace('-', '', ucwords($kebab, '-')));
    }

    /**
     * `#rrggbb` → `"r, g, b"`; anything else (incl. shorthand and non-hex
     * values) is skipped per contract.
     */
    private static function hexToRgb(string $hex): ?string
    {
        $hex = trim($hex); // keep in sync with hexToRgbString() in src/manifest.js

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $hex, $matches) !== 1) {
            return null;
        }

        return sprintf(
            '%d, %d, %d',
            (int) hexdec(substr($matches[1], 0, 2)),
            (int) hexdec(substr($matches[1], 2, 2)),
            (int) hexdec(substr($matches[1], 4, 2))
        );
    }

    /**
     * @return list<string>
     */
    private static function canonicalTokenKeys(): array
    {
        return array_merge(
            Settings::COLOR_TOKENS,
            Settings::TEXT_TOKENS,
            array_keys(Settings::DERIVED_TOKENS)
        );
    }
}
