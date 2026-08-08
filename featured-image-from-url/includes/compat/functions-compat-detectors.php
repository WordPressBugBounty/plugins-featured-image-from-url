<?php
declare(strict_types=1);

/**
 * Compatibility functions for FIFU plugin and theme detectors.
 */

if (!function_exists('fifu_is_elementor_active')) {
    /**
     * Checks if Elementor is active.
     *
     * @return bool
     */
    function fifu_is_elementor_active(): bool {
        return Fifu_Plugin_Detector::is_elementor_active();
    }
}

if (!function_exists('fifu_is_elementor_pro_active')) {
    /**
     * Checks if Elementor Pro is active.
     *
     * @return bool
     */
    function fifu_is_elementor_pro_active(): bool {
        return Fifu_Plugin_Detector::is_elementor_pro_active();
    }
}

if (!function_exists('fifu_is_elementor_editor')) {
    /**
     * Checks if Elementor editor is active.
     *
     * @return bool
     */
    function fifu_is_elementor_editor(): bool {
        return Fifu_Plugin_Detector::is_elementor_editor();
    }
}

if (!function_exists('fifu_is_essential_grid_active')) {
    /**
     * Checks if Essential Grid is active.
     *
     * @return bool
     */
    function fifu_is_essential_grid_active(): bool {
        return Fifu_Plugin_Detector::is_essential_grid_active();
    }
}

if (!function_exists('fifu_is_fusion_builder_active')) {
    /**
     * Checks if Fusion Builder is active.
     *
     * @return bool
     */
    function fifu_is_fusion_builder_active(): bool {
        return Fifu_Plugin_Detector::is_fusion_builder_active();
    }
}

if (!function_exists('fifu_is_goodlayers_core_active')) {
    /**
     * Checks if Goodlayers Core is active.
     *
     * @return bool
     */
    function fifu_is_goodlayers_core_active(): bool {
        return Fifu_Plugin_Detector::is_goodlayers_core_active();
    }
}

if (!function_exists('fifu_is_yith_woocommerce_wishlist_active')) {
    /**
     * Checks if YITH WooCommerce Wishlist is active.
     *
     * @return bool
     */
    function fifu_is_yith_woocommerce_wishlist_active(): bool {
        return Fifu_Plugin_Detector::is_yith_woocommerce_wishlist_active();
    }
}

if (!function_exists('fifu_is_yith_woocommerce_wishlist_ajax_enabled')) {
    /**
     * Checks if YITH WooCommerce Wishlist AJAX is enabled.
     *
     * @return bool
     */
    function fifu_is_yith_woocommerce_wishlist_ajax_enabled(): bool {
        return Fifu_Plugin_Detector::is_yith_woocommerce_wishlist_ajax_enabled();
    }
}

if (!function_exists('fifu_is_yith_woocommerce_badges_management_active')) {
    /**
     * Checks if YITH WooCommerce Badges Management is active.
     *
     * @return bool
     */
    function fifu_is_yith_woocommerce_badges_management_active(): bool {
        return Fifu_Plugin_Detector::is_yith_woocommerce_badges_management_active();
    }
}

if (!function_exists('fifu_is_amp_active')) {
    /**
     * Checks if AMP is active.
     *
     * @return bool
     */
    function fifu_is_amp_active(): bool {
        return Fifu_Plugin_Detector::is_amp_active();
    }
}

if (!function_exists('fifu_is_ol_scrapes_active')) {
    /**
     * Checks if OL Scrapes is active.
     *
     * @return bool
     */
    function fifu_is_ol_scrapes_active(): bool {
        return Fifu_Plugin_Detector::is_ol_scrapes_active();
    }
}

if (!function_exists('fifu_is_wp_automatic_active')) {
    /**
     * Checks if WP Automatic is active.
     *
     * @return bool
     */
    function fifu_is_wp_automatic_active(): bool {
        return Fifu_Plugin_Detector::is_wp_automatic_active();
    }
}

if (!function_exists('fifu_is_rank_math_seo_active')) {
    /**
     * Checks if Rank Math SEO is active.
     *
     * @return bool
     */
    function fifu_is_rank_math_seo_active(): bool {
        return Fifu_Plugin_Detector::is_rank_math_seo_active();
    }
}

if (!function_exists('fifu_is_yoast_seo_active')) {
    /**
     * Checks if Yoast SEO is active.
     *
     * @return bool
     */
    function fifu_is_yoast_seo_active(): bool {
        return Fifu_Plugin_Detector::is_yoast_seo_active();
    }
}

if (!function_exists('fifu_is_aioseo_active')) {
    /**
     * Checks if All in One SEO (AIOSEO) is active.
     *
     * @return bool
     */
    function fifu_is_aioseo_active(): bool {
        return Fifu_Plugin_Detector::is_aioseo_active();
    }
}

if (!function_exists('fifu_is_any_seo_plugin_active')) {
    /**
     * Checks if any known SEO plugin is active.
     *
     * @return bool
     */
    function fifu_is_any_seo_plugin_active(): bool {
        return Fifu_Plugin_Detector::is_any_seo_plugin_active();
    }
}

if (!function_exists('fifu_is_debug_bar_active')) {
    /**
     * Checks if Debug Bar is active.
     *
     * @return bool
     */
    function fifu_is_debug_bar_active(): bool {
        return Fifu_Plugin_Detector::is_debug_bar_active();
    }
}

if (!function_exists('fifu_is_query_monitor_active')) {
    /**
     * Checks if Query Monitor is active.
     *
     * @return bool
     */
    function fifu_is_query_monitor_active(): bool {
        return Fifu_Plugin_Detector::is_query_monitor_active();
    }
}

if (!function_exists('fifu_is_gravity_forms_active')) {
    /**
     * Checks if Gravity Forms is active.
     *
     * @return bool
     */
    function fifu_is_gravity_forms_active(): bool {
        return Fifu_Plugin_Detector::is_gravity_forms_active();
    }
}

if (!function_exists('fifu_is_multisite_global_media_active')) {
    /**
     * Checks if Multisite Global Media is active.
     *
     * @return bool
     */
    function fifu_is_multisite_global_media_active(): bool {
        return Fifu_Plugin_Detector::is_multisite_global_media_active();
    }
}

if (!function_exists('fifu_is_content_views_pro_active')) {
    /**
     * Checks if Content Views Pro is active.
     *
     * @return bool
     */
    function fifu_is_content_views_pro_active(): bool {
        return Fifu_Plugin_Detector::is_content_views_pro_active();
    }
}

if (!function_exists('fifu_is_woo_variation_swatches_active')) {
    /**
     * Checks if Woo Variation Swatches is active.
     *
     * @return bool
     */
    function fifu_is_woo_variation_swatches_active(): bool {
        return Fifu_Plugin_Detector::is_woo_variation_swatches_active();
    }
}

if (!function_exists('fifu_is_flatsome_active')) {
    /**
     * Checks if Flatsome theme is active.
     *
     * @return bool
     */
    function fifu_is_flatsome_active(): bool {
        return Fifu_Theme_Detector::is_flatsome_active();
    }
}

if (!function_exists('fifu_is_divi_active')) {
    /**
     * Checks if Divi theme is active.
     *
     * @return bool
     */
    function fifu_is_divi_active(): bool {
        return Fifu_Theme_Detector::is_divi_active();
    }
}

if (!function_exists('fifu_is_avada_active')) {
    /**
     * Checks if Avada theme is active.
     *
     * @return bool
     */
    function fifu_is_avada_active(): bool {
        return Fifu_Theme_Detector::is_avada_active();
    }
}

if (!function_exists('fifu_is_newspaper_active')) {
    /**
     * Checks if Newspaper theme is active.
     *
     * @return bool
     */
    function fifu_is_newspaper_active(): bool {
        return Fifu_Theme_Detector::is_newspaper_active();
    }
}

if (!function_exists('fifu_is_rey_active')) {
    /**
     * Checks if Rey theme is active.
     *
     * @return bool
     */
    function fifu_is_rey_active(): bool {
        return Fifu_Theme_Detector::is_rey_active();
    }
}

if (!function_exists('fifu_is_blocksy_active')) {
    /**
     * Checks if Blocksy theme is active.
     *
     * @return bool
     */
    function fifu_is_blocksy_active(): bool {
        return Fifu_Theme_Detector::is_blocksy_active();
    }
}

if (!function_exists('fifu_is_houzez_active')) {
    /**
     * Checks if Houzez theme is active.
     *
     * @return bool
     */
    function fifu_is_houzez_active(): bool {
        return Fifu_Theme_Detector::is_houzez_active();
    }
}

if (!function_exists('fifu_is_wpresidence_active')) {
    /**
     * Checks if WpResidence theme is active.
     *
     * @return bool
     */
    function fifu_is_wpresidence_active(): bool {
        return Fifu_Theme_Detector::is_wpresidence_active();
    }
}

if (!function_exists('fifu_is_photolio_active')) {
    /**
     * Checks if Photolio theme is active.
     *
     * @return bool
     */
    function fifu_is_photolio_active(): bool {
        return Fifu_Theme_Detector::is_photolio_active();
    }
}
