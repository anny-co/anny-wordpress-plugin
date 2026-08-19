<?php

declare(strict_types=1);

namespace Anny;

\defined('ABSPATH') || exit;

use Anny\Admin\AdminPage;
use Anny\Api\ApiClient;
use Anny\Blocks\Blocks;
use Anny\Blocks\Manifest;
use Anny\Oauth\OauthClient;
use Anny\Rest\EntitiesController;
use Anny\Rest\OauthController;

/**
 * Composition root: wires all services and centralizes every hook
 * registration of the plugin.
 */
final class Plugin
{
    private static ?self $instance = null;

    private function __construct(
        private readonly Blocks $blocks,
        private readonly AdminPage $adminPage,
        private readonly OauthClient $oauth,
        private readonly OauthController $oauthController,
        private readonly EntitiesController $entitiesController,
    ) {
    }

    public static function boot(): void
    {
        if (self::$instance !== null) {
            return;
        }

        $regions  = new Regions();
        $settings = new Settings($regions);
        $tokens   = new TokenStore();
        $oauth    = new OauthClient($settings, $tokens, $regions);
        $api      = new ApiClient($settings, $oauth);
        $manifest = new Manifest();

        self::$instance = new self(
            new Blocks($manifest, $settings, $regions, $oauth),
            new AdminPage($settings, $regions, $tokens, $oauth, $api),
            $oauth,
            new OauthController($settings, $oauth, $api),
            new EntitiesController($settings, $oauth, $api),
        );

        self::$instance->registerHooks();
    }

    private function registerHooks(): void
    {

        // Blocks + scripts.
        add_action('init', [$this->blocks, 'registerScripts'], 5);
        add_action('init', [$this->blocks, 'register']);
        add_filter('block_categories_all', [$this->blocks, 'filterCategories']);
        add_action('enqueue_block_assets', [$this->blocks, 'enqueueCanvasAssets']);
        add_action('enqueue_block_editor_assets', [$this->blocks, 'enqueueEditorAssets']);

        // REST API.
        add_action('rest_api_init', [$this->oauthController, 'registerRoutes']);
        add_action('rest_api_init', [$this->entitiesController, 'registerRoutes']);

        // Admin.
        add_action('admin_menu', [$this->adminPage, 'addMenu']);
        add_action('admin_init', [$this->adminPage, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this->adminPage, 'enqueueAssets']);
        add_action('admin_notices', [$this->adminPage, 'notices']);

        // OAuth admin-post actions.
        add_action('admin_post_anny_connect', [$this->oauth, 'handleConnect']);
        add_action('admin_post_anny_disconnect', [$this->oauth, 'handleDisconnect']);
    }

}
