<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Rest_Routing_Integration
{
    /**
     * Hooks REST API initialization to the routing integration.
     */
    public static function register_hooks(): void
    {
        add_action('rest_api_init', [self::class, 'on_rest_api_init']);
    }

    /**
     * Registers every FIFU REST controller.
     */
    public static function on_rest_api_init(): void
    {
        Fifu_Rest_Controller::register_routes();
        Fifu_Rest_Media_Controller::register_routes();
        Fifu_Rest_Sizes_Controller::register_routes();
        Fifu_Rest_Cloud_Controller::register_routes();
        // Comments must be in English.
        Fifu_Rest_Migration_Controller::register_routes();
    }
}
