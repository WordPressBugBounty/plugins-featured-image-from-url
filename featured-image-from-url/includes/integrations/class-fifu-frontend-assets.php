<?php

defined('ABSPATH') || exit;

/**
 * Enqueues FIFU frontend assets that previously depended on legacy hooks.
 */
class Fifu_Frontend_Assets {

    /**
     * Adds the head assets and resource hints that modern FIFU builds expect.
     */
    public static function add_head_assets(): void {
        if (Fifu_Amp_Integration::is_amp_request()) {
            return;
        }

        if (Fifu_Admin_Menu::is_su_sign_up_complete()) {
            echo '<link rel="preconnect" href="https://cloud.fifu.app">';
            echo '<link rel="preconnect" href="https://cdn.fifu.app">';
        }

        if (Fifu_Options_Utils::is_on('fifu_photon')) {
            for ($index = 0; $index <= 3; $index++) {
                echo "<link rel='dns-prefetch' href='https://i{$index}.wp.com/'>";
                echo "<link rel='preconnect' href='https://i{$index}.wp.com/' crossorigin>";
            }
        }

        $is_product = class_exists('Fifu_Woocommerce_Context') && Fifu_Woocommerce_Context::is_product();

        if (class_exists('WooCommerce')) {
            wp_register_style('fifu-woo', plugins_url('/includes/html/css/woo.css', FIFU_PLUGIN_FILE), array(), Fifu_Plugin_Info::get_enqueue_version());
            wp_enqueue_style('fifu-woo');
            wp_add_inline_style('fifu-woo', 'img.zoomImg {display:' . Fifu_Woocommerce_Display_Configuration::get_zoom_display_mode() . ' !important}');
        }

        $main_image_url = Fifu_Post_Main_Image_Resolver::get_main_image_url(get_queried_object_id(), true);
        $base64_main_image_url = null;

        if (Fifu_Theme_Detector::is_flatsome_active() || (!Fifu_Woocommerce_Display_Configuration::is_lightbox_enabled() && $is_product)) {
            wp_enqueue_script('fifu-image-js', plugins_url('/includes/html/js/image.js', FIFU_PLUGIN_FILE), array('jquery'), Fifu_Plugin_Info::get_enqueue_version());
            wp_localize_script('fifu-image-js', 'fifuImageVars', [
                'fifu_is_front_page' => is_front_page() || is_home(),
                'fifu_woo_lbox_enabled' => Fifu_Woocommerce_Display_Configuration::is_lightbox_enabled(),
                'fifu_is_flatsome_active' => Fifu_Theme_Detector::is_flatsome_active(),
                'fifu_main_image_url' => $main_image_url,
                'base64_main_image_url' => $base64_main_image_url,
                'fifu_local_image_url' => get_the_post_thumbnail_url(get_the_ID(), 'full'),
            ]);
        }

        if ($is_product) {
            wp_enqueue_script('fifu-photoswipe-fix', plugins_url('/includes/html/js/photoswipe-fix.js', FIFU_PLUGIN_FILE), array('jquery'), Fifu_Plugin_Info::get_enqueue_version());
            wp_localize_script('fifu-photoswipe-fix', 'fifuSwipeVars', [
                'theme' => get_option('template'),
            ]);
        }

    }

}
