<?php

declare(strict_types=1);

namespace Anny\Admin;

\defined('ABSPATH') || exit;

use Anny\Api\ApiClient;
use Anny\Oauth\OauthClient;
use Anny\Regions;
use Anny\Settings;
use Anny\TokenStore;

/**
 * The "anny" settings screen: connection/region, organization picker,
 * design-token GUI (wp-color-picker + brand import) and widget defaults.
 */
final class AdminPage
{
    public const MENU_SLUG = 'anny';

    /** Script handle of the brand-import admin bundle. */
    public const ADMIN_HANDLE = 'anny-admin';

    private string $pageHook = '';

    public function __construct(
        private readonly Settings $settings,
        private readonly Regions $regions,
        private readonly TokenStore $tokens,
        private readonly OauthClient $oauth,
        private readonly ApiClient $api,
    ) {
    }

    /**
     * admin_menu
     */
    public function addMenu(): void
    {
        $this->pageHook = add_menu_page(
            __('anny', 'anny-bookings'),
            __('anny', 'anny-bookings'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render'],
            $this->menuIcon(),
            // Between Pages (20) and Comments (25), so a core menu item sits
            // on either side of anny.
            //
            // WordPress draws separators at 4, 59 and 99. Any position that
            // touches one of them puts the group gap directly against this
            // entry, which reads as stray padding: 59.5 leaves it above, and
            // anything between Comments and the next separator (e.g. 26.5)
            // leaves it below, because anny is then the last item of its
            // group. Sitting mid-group avoids the adjacency altogether.
            //
            // The fraction keeps the slot clear of plugins that claim a round
            // position.
            21.5
        );
    }

    /**
     * The anny logo as a data-URI SVG. WordPress repaints the fills to match
     * the active admin color scheme (svg-painter), so the icon ships with a
     * neutral flat fill.
     */
    private function menuIcon(): string
    {
        $icon = ANNY_DIR . '/assets/anny-icon.svg';

        if (!is_readable($icon)) {
            return 'dashicons-calendar-alt';
        }

        $svg = file_get_contents($icon);

        return $svg === false
            ? 'dashicons-calendar-alt'
            : 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * admin_init
     */
    public function registerSettings(): void
    {
        register_setting(
            'anny',
            Settings::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'show_in_rest'      => false,
            ]
        );
    }

    /**
     * Settings API sanitize callback. Delegates to the strict Settings
     * sanitizer and enforces "changing the region disconnects".
     *
     * @return array<string, mixed>
     */
    public function sanitizeSettings(mixed $input): array
    {
        $current = $this->settings->all();
        $clean   = $this->settings->sanitize($input);

        if ($clean['region'] !== $current['region'] && $this->tokens->has()) {
            // Revoke against the OLD region (settings are not saved yet).
            $this->oauth->revokeRemote();
            $this->tokens->clear();
            $this->tokens->clearReconnectFlag();

            $clean['org']            = null;
            $clean['connected_user'] = null;
            $clean['cache_version']  = (int) $current['cache_version'] + 1;
        }

        return $clean;
    }

    /**
     * admin_enqueue_scripts
     */
    public function enqueueAssets(string $hook): void
    {
        if ($hook !== $this->pageHook) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        $assetFile = ANNY_DIR . '/build/brand-import.asset.php';

        if (!file_exists($assetFile)) {
            // JS bundle not built (yet): fall back to a bare color-picker init;
            // the import button degrades gracefully.
            wp_add_inline_script(
                'wp-color-picker',
                'jQuery( function( $ ) { $( ".anny-color-field" ).wpColorPicker(); } );'
            );

            return;
        }

        $asset = include $assetFile;
        $asset = is_array($asset) ? $asset : [];

        wp_enqueue_script(
            self::ADMIN_HANDLE,
            ANNY_URL . '/build/brand-import.js',
            $asset['dependencies'] ?? [],
            (string) ($asset['version'] ?? ANNY_VERSION),
            true
        );

        wp_set_script_translations(self::ADMIN_HANDLE, 'anny-bookings', ANNY_DIR . '/languages');
    }

    /**
     * admin_notices
     */
    public function notices(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if ($this->tokens->needsReconnect()) {
            printf(
                '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('The stored anny connection could not be read and was removed.', 'anny-bookings'),
                esc_url($this->oauth->settingsUrl()),
                esc_html__('Please reconnect to anny.', 'anny-bookings')
            );
        }

        // Display-only flags set by our own redirects — no form data involved.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';

        if ($page !== self::MENU_SLUG) {
            return;
        }

        if (isset($_GET['anny-connected'])) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html__('Successfully connected to anny.', 'anny-bookings')
            );
        }

        if (isset($_GET['anny-disconnected'])) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html__('Disconnected from anny.', 'anny-bookings')
            );
        }

        if (isset($_GET['anny-error'])) {
            $code = sanitize_key(wp_unslash((string) $_GET['anny-error']));
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            printf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                esc_html($this->errorMessage($code))
            );
        }
    }

    /**
     * Render the settings screen: two tabs, Styling first, Connection second.
     */
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Display-only tab switch — no form data involved.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : 'styling';

        if (!in_array($tab, ['styling', 'connection'], true)) {
            $tab = 'styling';
        }

        $connected = $this->oauth->isConnected();

        ?>
        <div class="wrap anny-settings">
            <h1><?php esc_html_e('anny', 'anny-bookings'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a
                    href="<?php echo esc_url($this->tabUrl('styling')); ?>"
                    class="nav-tab<?php echo $tab === 'styling' ? ' nav-tab-active' : ''; ?>"
                ><?php esc_html_e('Styling', 'anny-bookings'); ?></a>
                <a
                    href="<?php echo esc_url($this->tabUrl('connection')); ?>"
                    class="nav-tab<?php echo $tab === 'connection' ? ' nav-tab-active' : ''; ?>"
                ><?php esc_html_e('Connection', 'anny-bookings'); ?></a>
            </nav>

            <?php
            if ($tab === 'connection') {
                $this->renderConnectionTab($connected);
            } else {
                $this->renderStylingTab($connected);
            }
            ?>
        </div>
        <?php
    }

    private function tabUrl(string $tab): string
    {
        return add_query_arg(
            [
                'page' => self::MENU_SLUG,
                'tab'  => $tab,
            ],
            admin_url('admin.php')
        );
    }

    /**
     * Second tab: connection status, region and organization.
     */
    private function renderConnectionTab(bool $connected): void
    {
        $settings  = $this->settings->all();
        $regions   = $this->regions->all();
        $regionKey = $settings['region'];

        $regionConfig = $this->regions->get($regionKey);
        $org          = $this->settings->org();
        $user         = $this->settings->connectedUser();

        ?>
        <h2><?php esc_html_e('Connection', 'anny-bookings'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Status', 'anny-bookings'); ?></th>
                <td>
                    <?php if ($connected) : ?>
                        <p>
                            <strong><?php esc_html_e('Connected', 'anny-bookings'); ?></strong>
                            <?php
                            if ($user !== null && ($user['name'] !== '' || $user['email'] !== '')) {
                                printf(
                                    /* translators: 1: user name, 2: user email. */
                                    esc_html__('as %1$s (%2$s)', 'anny-bookings'),
                                    esc_html($user['name']),
                                    esc_html($user['email'])
                                );
                            }
                            ?>
                        </p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="anny_disconnect" />
                            <?php wp_nonce_field('anny_disconnect'); ?>
                            <?php submit_button(__('Disconnect', 'anny-bookings'), 'secondary', 'submit', false); ?>
                        </form>
                    <?php else : ?>
                        <p><strong><?php esc_html_e('Not connected', 'anny-bookings'); ?></strong></p>
                        <?php if ($regionConfig !== null && $regionConfig['client_id'] !== '') : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="anny_connect" />
                                <?php wp_nonce_field('anny_connect'); ?>
                                <?php submit_button(__('Connect with anny', 'anny-bookings'), 'primary', 'submit', false); ?>
                            </form>
                        <?php else : ?>
                            <p class="description">
                                <?php esc_html_e('This region has no OAuth client configured yet, so connecting is not possible.', 'anny-bookings'); ?>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <form method="post" action="options.php">
            <?php settings_fields('anny'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="anny-region"><?php esc_html_e('Data region', 'anny-bookings'); ?></label>
                    </th>
                    <td>
                        <select id="anny-region" name="<?php echo esc_attr(Settings::OPTION); ?>[region]">
                            <?php foreach ($regions as $key => $region) : ?>
                                <option value="<?php echo esc_attr((string) $key); ?>" <?php selected($regionKey, (string) $key); ?>>
                                    <?php echo esc_html($region['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('The anny region your data lives in. Changing the region disconnects the current anny account.', 'anny-bookings'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Organization', 'anny-bookings'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="anny-org"><?php esc_html_e('Organization', 'anny-bookings'); ?></label>
                    </th>
                    <td>
                        <?php $this->renderOrgPicker($connected, $org); ?>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * First (default) tab: design tokens and widget defaults.
     */
    private function renderStylingTab(bool $connected): void
    {
        $settings = $this->settings->all();
        $org      = $this->settings->org();

        if (!$connected) {
            printf(
                '<div class="notice notice-warning inline"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('Not connected to anny yet — brand import and the block entity pickers are unavailable.', 'anny-bookings'),
                esc_url($this->tabUrl('connection')),
                esc_html__('Set up the connection', 'anny-bookings')
            );
        }

        ?>
        <form method="post" action="options.php">
                <?php settings_fields('anny'); ?>

                <h2><?php esc_html_e('Design tokens', 'anny-bookings'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Global styling for all anny widgets on this site. Individual blocks can override every token. Empty fields keep the widget default.', 'anny-bookings'); ?>
                </p>
                <p>
                    <button
                        type="button"
                        class="button"
                        id="anny-brand-import"
                        <?php disabled(!$connected || $org === null); ?>
                    >
                        <?php esc_html_e('Import brand from anny', 'anny-bookings'); ?>
                    </button>
                    <span id="anny-brand-import-status" class="description"></span>
                    <span class="description">
                        <?php esc_html_e('Fills the color fields from the brand of your anny organization.', 'anny-bookings'); ?>
                    </span>
                </p>

                <?php foreach ($this->tokenGroups() as $group) : ?>
                    <h3><?php echo esc_html($group['title']); ?></h3>
                    <table class="form-table" role="presentation">
                        <?php foreach ($group['tokens'] as $token) : ?>
                            <?php $this->renderTokenField($token, $group['type'], $settings['tokens_design']); ?>
                        <?php endforeach; ?>
                    </table>
                <?php endforeach; ?>

                <h2><?php esc_html_e('Defaults', 'anny-bookings'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="anny-default-locale"><?php esc_html_e('Widget language', 'anny-bookings'); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                class="small-text"
                                id="anny-default-locale"
                                name="<?php echo esc_attr(Settings::OPTION); ?>[defaults][locale]"
                                value="<?php echo esc_attr($settings['defaults']['locale']); ?>"
                                placeholder="<?php echo esc_attr($this->settings->defaultLocale()); ?>"
                            />
                            <p class="description">
                                <?php esc_html_e('Widget language code, e.g. "de", "en", or "de-formal" for formal German. Leave empty to use the site language.', 'anny-bookings'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Require login', 'anny-bookings'); ?></th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr(Settings::OPTION); ?>[defaults][should_login]"
                                    value="1"
                                    <?php checked($settings['defaults']['should_login']); ?>
                                />
                                <?php esc_html_e('Visitors must log in to anny before booking.', 'anny-bookings'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="anny-default-idp-uuid"><?php esc_html_e('Identity provider UUID', 'anny-bookings'); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                class="regular-text code"
                                id="anny-default-idp-uuid"
                                name="<?php echo esc_attr(Settings::OPTION); ?>[defaults][idp_uuid]"
                                value="<?php echo esc_attr($settings['defaults']['idp_uuid']); ?>"
                            />
                            <p class="description">
                                <?php esc_html_e('Optional: UUID of a single-sign-on identity provider used by the widgets.', 'anny-bookings'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Organization select (JSON-encoded option values, decoded by the
     * Settings sanitizer) or a graceful fallback.
     *
     * @param array{id: string, uuid: string, slug: string, name: string}|null $org
     */
    private function renderOrgPicker(bool $connected, ?array $org): void
    {
        if (!$connected) {
            echo '<p class="description">' . esc_html__('Connect with anny to pick one of your organizations.', 'anny-bookings') . '</p>';

            return;
        }

        $organizations = $this->api->organizations();

        if (is_wp_error($organizations)) {
            // Keep the currently selected organization across saves.
            if ($org !== null) {
                printf(
                    '<input type="hidden" name="%s[org]" value="%s" /><p><strong>%s</strong></p>',
                    esc_attr(Settings::OPTION),
                    esc_attr((string) wp_json_encode($org)),
                    esc_html($org['name'] !== '' ? $org['name'] : $org['slug'])
                );
            }

            printf(
                '<p class="description">%s</p>',
                esc_html__('Your anny organizations could not be loaded right now. Please try again later.', 'anny-bookings')
            );

            return;
        }

        ?>
        <select id="anny-org" name="<?php echo esc_attr(Settings::OPTION); ?>[org]">
            <option value=""><?php esc_html_e('— No organization —', 'anny-bookings'); ?></option>
            <?php foreach ($organizations as $option) : ?>
                <option
                    value="<?php echo esc_attr((string) wp_json_encode($option)); ?>"
                    <?php selected($org !== null && $org['id'] === $option['id']); ?>
                >
                    <?php echo esc_html($option['name'] !== '' ? $option['name'] : $option['slug']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('The organization whose resources, services and plans the block pickers offer.', 'anny-bookings'); ?>
        </p>
        <?php
    }

    /**
     * One design-token row: color picker input or plain text input.
     *
     * @param array<string, string> $values Stored global tokens.
     */
    private function renderTokenField(string $token, string $type, array $values): void
    {
        $labels = $this->tokenLabels();
        $label  = $labels[$token] ?? $token;
        $hint   = $this->tokenHints()[$token] ?? '';
        $value  = (string) ($values[$token] ?? '');
        $id     = 'anny-token-' . $token;
        $name   = Settings::OPTION . '[tokens_design][' . $token . ']';

        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input
                    type="text"
                    id="<?php echo esc_attr($id); ?>"
                    name="<?php echo esc_attr($name); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    class="<?php echo esc_attr($type === 'color' ? 'anny-color-field' : 'regular-text code'); ?>"
                    data-anny-token="<?php echo esc_attr($token); ?>"
                    aria-describedby="<?php echo esc_attr($hint !== '' ? $id . '-hint' : ''); ?>"
                />
                <?php if ($hint !== '') : ?>
                    <p class="description" id="<?php echo esc_attr($id); ?>-hint"><?php echo esc_html($hint); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Value-format hints for the tokens whose accepted input is not obvious
     * from the label. Tokens sharing a format share the string, so they share
     * a translation too.
     *
     * The wording is duplicated in TOKEN_HINTS in src/manifest.js, which
     * renders the same hints in the block inspector.
     *
     * @return array<string, string>
     */
    private function tokenHints(): array
    {
        $radius  = __('CSS length, e.g. 8px or 0.', 'anny-bookings');
        $shadow  = __('CSS box-shadow, e.g. 0 2px 8px rgba(0, 0, 0, 0.1) — or none.', 'anny-bookings');
        $size    = __('CSS length, e.g. 200px or 100%.', 'anny-bookings');
        $overlay = __('Any CSS color. Overlays are usually semi-transparent, e.g. rgba(97, 149, 237, 0.14).', 'anny-bookings');

        return [
            'primary-color-overlay'          => $overlay,
            'panel-background-overlay'       => $overlay,
            'panel-background-overlay-dense' => $overlay,
            'small-border-radius'            => $radius,
            'panel-border-radius'            => $radius,
            'detail-border-radius'           => $radius,
            'panel-border-style'             => __('CSS border shorthand, e.g. 1px solid #e5e5e5 — or none.', 'anny-bookings'),
            'panel-shadow'                   => $shadow,
            'detail-shadow'                  => $shadow,
            'button-width'                   => $size,
            'button-height'                  => $size,
            'logo-url'                       => __('Full URL of an image, e.g. https://example.com/logo.svg.', 'anny-bookings'),
        ];
    }

    /**
     * Sensibly grouped canonical tokens for the GUI.
     *
     * @return list<array{title: string, type: string, tokens: list<string>}>
     */
    private function tokenGroups(): array
    {
        return [
            [
                'title'  => __('Brand colors', 'anny-bookings'),
                'type'   => 'color',
                'tokens' => [
                    'primary-color',
                    'primary-color-hover',
                    'secondary-color',
                    'primary-stroke-color',
                ],
            ],
            [
                'title'  => __('Text colors', 'anny-bookings'),
                'type'   => 'color',
                'tokens' => [
                    'text-primary-color',
                    'text-secondary-color',
                    'text-tertiary-color',
                ],
            ],
            [
                'title'  => __('Backgrounds', 'anny-bookings'),
                'type'   => 'color',
                'tokens' => [
                    'primary-background',
                    'panel-background',
                    'panel-background-light',
                    'panel-background-dark',
                    'input-background',
                ],
            ],
            [
                'title'  => __('Borders & buttons', 'anny-bookings'),
                'type'   => 'color',
                'tokens' => [
                    'light-border-color',
                    'table-border-color',
                    'button-background',
                    'button-text',
                ],
            ],
            [
                'title'  => __('Layout & effects', 'anny-bookings'),
                'type'   => 'text',
                'tokens' => Settings::TEXT_TOKENS,
            ],
        ];
    }

    /**
     * Translated labels for every canonical design token.
     *
     * @return array<string, string>
     */
    private function tokenLabels(): array
    {
        return [
            'primary-color'                  => __('Primary color', 'anny-bookings'),
            'primary-color-hover'            => __('Primary color (hover)', 'anny-bookings'),
            'primary-color-overlay'          => __('Primary color overlay', 'anny-bookings'),
            'secondary-color'                => __('Secondary color', 'anny-bookings'),
            'primary-stroke-color'           => __('Primary stroke color', 'anny-bookings'),
            'text-primary-color'             => __('Primary text color', 'anny-bookings'),
            'text-secondary-color'           => __('Secondary text color', 'anny-bookings'),
            'text-tertiary-color'            => __('Tertiary text color', 'anny-bookings'),
            'primary-background'             => __('Primary background', 'anny-bookings'),
            'panel-background'               => __('Panel background', 'anny-bookings'),
            'panel-background-light'         => __('Panel background (light)', 'anny-bookings'),
            'panel-background-overlay'       => __('Panel background overlay', 'anny-bookings'),
            'panel-background-overlay-dense' => __('Panel background overlay (dense)', 'anny-bookings'),
            'panel-background-dark'          => __('Panel background (dark)', 'anny-bookings'),
            'input-background'               => __('Input background', 'anny-bookings'),
            'light-border-color'             => __('Light border color', 'anny-bookings'),
            'table-border-color'             => __('Table border color', 'anny-bookings'),
            'button-background'              => __('Button background', 'anny-bookings'),
            'button-text'                    => __('Button text color', 'anny-bookings'),
            'small-border-radius'            => __('Small border radius', 'anny-bookings'),
            'panel-border-radius'            => __('Panel border radius', 'anny-bookings'),
            'detail-border-radius'           => __('Detail border radius', 'anny-bookings'),
            'panel-border-style'             => __('Panel border style', 'anny-bookings'),
            'panel-shadow'                   => __('Panel shadow', 'anny-bookings'),
            'detail-shadow'                  => __('Detail shadow', 'anny-bookings'),
            'button-width'                   => __('Button width', 'anny-bookings'),
            'button-height'                  => __('Button height', 'anny-bookings'),
            'logo-url'                       => __('Logo URL', 'anny-bookings'),
        ];
    }

    private function errorMessage(string $code): string
    {
        $messages = [
            'not_configured'        => __('This region has no OAuth client configured yet.', 'anny-bookings'),
            'missing_state'         => __('The login response was missing its state parameter.', 'anny-bookings'),
            'invalid_state'         => __('The login attempt expired or was already used. Please try again.', 'anny-bookings'),
            'missing_code'          => __('The login response was missing the authorization code.', 'anny-bookings'),
            'token_exchange_failed' => __('anny did not accept the authorization code. Please try again.', 'anny-bookings'),
            'access_denied'         => __('The login was cancelled on the anny consent screen.', 'anny-bookings'),
        ];

        return $messages[$code] ?? sprintf(
            /* translators: %s: error code. */
            __('Connecting to anny failed (%s).', 'anny-bookings'),
            $code
        );
    }
}
