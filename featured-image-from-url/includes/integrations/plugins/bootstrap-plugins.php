<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/gravity-forms/class-fifu-gravity-forms-integration.php';
require_once __DIR__ . '/elementor/class-fifu-elementor-integration.php';

add_action('plugins_loaded', static function (): void {
    if (class_exists('WOOBE')) {
        Fifu_Woobe_Integration::register_hooks();
    }

    if (function_exists('dokan')) {
        Fifu_Dokan_Integration::register_hooks();
    }

    if (function_exists('mvx_is_module_active') || class_exists('MVX')) {
        Fifu_Mvx_Integration::register_hooks();
    }

    if (class_exists('Dfrps_Plugin')) {
        Fifu_Datafeedr_Integration::register_hooks();
    }

    if (function_exists('pll_the_languages')) {
        Fifu_Polylang_Integration::register_hooks();
    }

    if (class_exists('\ContentEgg\application\Plugin')) {
        Fifu_Content_Egg_Integration::register_hooks();
    }

    // Comments must be in English.
    Fifu_Wordpress_Importer_Integration::register_hooks();

    if (class_exists('Fifu_Plugin_Detector') && Fifu_Plugin_Detector::is_gravity_forms_active()) {
        Fifu_Gravity_Forms_Integration::init();
    }

    if (
        class_exists('Fifu_Elementor_Integration') &&
        class_exists('Fifu_Plugin_Detector') &&
        Fifu_Plugin_Detector::is_elementor_active()
    ) {
        Fifu_Elementor_Integration::register_hooks();
    }

});
