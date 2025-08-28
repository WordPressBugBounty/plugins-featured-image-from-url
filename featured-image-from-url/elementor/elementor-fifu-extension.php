<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Elementor_FIFU_Extension {

    private static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_action('init', [$this, 'i18n']);

        // Use current Elementor hooks for widgets and controls registration
        add_action('elementor/widgets/register', [$this, 'on_widgets_register']);
        add_action('elementor/controls/register', [$this, 'on_controls_register']);

        // Enqueue frontend scripts at the recommended timing
        add_action('elementor/frontend/after_enqueue_scripts', [$this, 'enqueue_widget_scripts']);
    }

    public function i18n() {
        load_plugin_textdomain(FIFU_SLUG);
    }

    // Register widgets using the new API (>= 3.5)
    public function on_widgets_register(\Elementor\Widgets_Manager $widgets_manager) {
        require_once(__DIR__ . '/widgets/widget.php');
        $widgets_manager->register(new \Elementor_FIFU_Widget());

        require_once(__DIR__ . '/widgets/widget-video.php');
        $widgets_manager->register(new \Elementor_FIFU_Video_Widget());
    }

    // Register custom controls if needed
    public function on_controls_register($controls_manager) {
        // Add custom controls registration here if needed
    }

    // Enqueue frontend scripts/styles at the recommended hook
    public function enqueue_widget_scripts() {
        // Example: wp_enqueue_script('fifu-el-frontend', plugins_url('assets/js/frontend.js', __FILE__), ['elementor-frontend'], '1.0.0', true);
    }
}

Elementor_FIFU_Extension::instance();

