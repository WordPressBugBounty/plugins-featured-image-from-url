<?php

class Fifu_Rest_Bootstrap {

    /**
     * Registers all REST routes for FIFU. Call Fifu_Rest_Bootstrap::init() from the plugin bootstrap (e.g. featured-image-from-url.php).
     */
    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_all_routes']);
    }

    /**
     * Central entry point for all REST controllers migrated from admin/api.php.
     */
    public static function register_all_routes(): void {
        Fifu_Rest_Quick_Edit_Controller::register_routes();
        Fifu_Rest_Search_Controller::register_routes();
        Fifu_Rest_Metadata_Worker_Controller::register_routes();
    }
}
