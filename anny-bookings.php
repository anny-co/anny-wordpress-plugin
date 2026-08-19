<?php
/**
 * Plugin Name:       anny bookings
 * Description:       Embed anny booking widgets as native Gutenberg blocks — booking pages, panels, buttons, calendars and maps.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            anny
 * Author URI:        https://anny.co
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       anny-bookings
 * Domain Path:       /languages
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('ANNY_VERSION', '1.0.0');
define('ANNY_FILE', __FILE__);
define('ANNY_DIR', __DIR__);
define('ANNY_URL', untrailingslashit(plugin_dir_url(__FILE__)));

/*
 * Tiny PSR-4 autoloader: Anny\ → includes/.
 * No runtime composer dependency (wordpress.org-friendly).
 */
spl_autoload_register(
    static function (string $class): void {
        $prefix = 'Anny\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $path     = ANNY_DIR . '/includes/' . str_replace('\\', '/', $relative) . '.php';

        if (is_readable($path)) {
            require $path;
        }
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        \Anny\Plugin::boot();
    }
);
