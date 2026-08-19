<?php
/**
 * Uninstall handler: remove every option and transient the plugin created.
 */

declare(strict_types=1);

// WP_UNINSTALL_PLUGIN is the guard that matters here — WordPress only defines
// it when it runs this file as an uninstall handler. The ABSPATH check is
// redundant next to it, but it is what static checkers look for.
defined('ABSPATH') || exit;
defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('anny_settings');
delete_option('anny_tokens');
delete_option('anny_refresh_lock');

global $wpdb;

/*
 * Entity/brand caches and OAuth state transients (incl. their timeout rows).
 *
 * These are wildcard-named — `anny_` + an md5 of the cache key — so there is
 * no list of names to hand to delete_transient(), and no API for deleting
 * transients by prefix. Caching the query would be pointless: this runs once,
 * during uninstall, and its whole purpose is to drop rows that any cache would
 * be holding stale copies of.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefix delete of plugin transients on uninstall; no API covers it.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_anny_') . '%',
        $wpdb->esc_like('_transient_timeout_anny_') . '%'
    )
);
