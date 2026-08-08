<?php

defined( 'ABSPATH' ) || exit;

/**
 * Provides display configuration data for WooCommerce integrations.
 */
class Fifu_Woocommerce_Display_Configuration {

    /**
     * Returns the configured zoom display mode.
     *
     * @return string
     */
    public static function get_zoom_display_mode(): string {
        return Fifu_Options_Utils::is_on( 'fifu_wc_zoom' ) ? 'inline' : 'none';
    }

    /**
     * Indicates whether the lightbox integration is active.
     *
     * @return bool
     */
    public static function is_lightbox_enabled(): bool {
        return Fifu_Options_Utils::is_on( 'fifu_wc_lbox' );
    }

    /**
     * Checks if the active theme already provides WooCommerce templates.
     *
     * @return bool
     */
    public static function theme_has_woocommerce_templates(): bool {
        return file_exists( get_template_directory() . '/woocommerce' );
    }

    /**
     * Returns the current image size configuration.
     *
     * @return mixed
     */
    public static function get_current_image_size() {
        if ( class_exists( 'WooCommerce' ) ) {
            if ( is_shop() ) {
                return wc_get_image_size( 'woocommerce_get_image_size_woocommerce_thumbnail' );
            }
            if ( is_product() ) {
                return wc_get_image_size( 'woocommerce_get_image_size_woocommerce_single' );
            }
        }

        return null;
    }

    /**
     * Outputs CSS that triggers the lightbox display behavior.
     *
     * @return void
     */
    public static function output_lightbox_trigger_css(): void {
        if ( Fifu_Options_Utils::is_off( 'fifu_wc_lbox' ) ) {
            echo '<style>[class$="woocommerce-product-gallery__trigger"] {display:none !important;}</style>';
        }
    }
}
