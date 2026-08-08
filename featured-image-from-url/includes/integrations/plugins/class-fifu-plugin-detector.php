<?php
declare(strict_types=1);

/**
 * Class Fifu_Plugin_Detector
 *
 * Detects active plugins and their states.
 */
class Fifu_Plugin_Detector {
    /**
     * Checks if Elementor is active.
     *
     * @return bool
     */
    public static function is_elementor_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('elementor/elementor.php') || self::is_elementor_pro_active();
    }

    /**
     * Checks if Elementor Pro is active.
     *
     * @return bool
     */
    public static function is_elementor_pro_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('elementor-pro/elementor-pro.php');
    }

    /**
     * Checks if Elementor editor is active.
     *
     * @return bool
     */
    public static function is_elementor_editor(): bool {
        if (!self::is_elementor_active()) {
            return false;
        }
        return class_exists('\Elementor\Plugin') && (\Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode());
    }

    /**
     * Checks if Essential Grid is active.
     *
     * @return bool
     */
    public static function is_essential_grid_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('essential-grid/essential-grid.php');
    }

    /**
     * Checks if Fusion Builder is active.
     *
     * @return bool
     */
    public static function is_fusion_builder_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('fusion-builder/fusion-builder.php');
    }

    /**
     * Checks if Goodlayers Core is active.
     *
     * @return bool
     */
    public static function is_goodlayers_core_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('goodlayers-core/goodlayers-core.php');
    }

    /**
     * Checks if YITH WooCommerce Wishlist is active.
     *
     * @return bool
     */
    public static function is_yith_woocommerce_wishlist_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('yith-woocommerce-wishlist/init.php');
    }

    /**
     * Checks if YITH WooCommerce Wishlist AJAX is enabled.
     *
     * @return bool
     */
    public static function is_yith_woocommerce_wishlist_ajax_enabled(): bool {
        return 'yes' === get_option('yith_wcwl_ajax_enable', 'no');
    }

    /**
     * Checks if YITH WooCommerce Badges Management is active.
     *
     * @return bool
     */
    public static function is_yith_woocommerce_badges_management_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('yith-woocommerce-badges-management/init.php');
    }

    /**
     * Checks if AMP is active.
     *
     * @return bool
     */
    public static function is_amp_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('amp/amp.php');
    }

    /**
     * Checks if OL Scrapes is active.
     *
     * @return bool
     */
    public static function is_ol_scrapes_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('ol_scrapes/ol_scrapes.php');
    }

    /**
     * Checks if WP Automatic is active.
     *
     * @return bool
     */
    public static function is_wp_automatic_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('wp-automatic/wp-automatic.php');
    }

    /**
     * Checks if Rank Math SEO is active.
     *
     * @return bool
     */
    public static function is_rank_math_seo_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('seo-by-rank-math/rank-math.php');
    }

    /**
     * Checks if Yoast SEO is active.
     *
     * @return bool
     */
    public static function is_yoast_seo_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('wordpress-seo/wp-seo.php');
    }

    /**
     * Checks if All in One SEO (AIOSEO) is active.
     *
     * @return bool
     */
    public static function is_aioseo_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php') ||
               is_plugin_active('all-in-one-seo-pack-pro/all_in_one_seo_pack.php');
    }

    /**
     * Checks if any known SEO plugin is active.
     *
     * @return bool
     */
    public static function is_any_seo_plugin_active(): bool {
        return self::is_yoast_seo_active() || self::is_rank_math_seo_active() || self::is_aioseo_active();
    }

    /**
     * Checks if Debug Bar is active.
     *
     * @return bool
     */
    public static function is_debug_bar_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('debug-bar/debug-bar.php');
    }

    /**
     * Checks if Query Monitor is active.
     *
     * @return bool
     */
    public static function is_query_monitor_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('query-monitor/query-monitor.php');
    }

    /**
     * Checks if Gravity Forms is active.
     *
     * @return bool
     */
    public static function is_gravity_forms_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('gravityforms/gravityforms.php');
    }

    /**
     * Checks if Multisite Global Media is active.
     *
     * @return bool
     */
    public static function is_multisite_global_media_active(): bool {
        return class_exists('\MultisiteGlobalMedia\Plugin');
    }

    /**
     * Checks if Content Views Pro is active.
     *
     * @return bool
     */
    public static function is_content_views_pro_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('pt-content-views-pro/content-views.php');
    }

    /**
     * Checks if Woo Variation Swatches is active.
     *
     * @return bool
     */
    public static function is_woo_variation_swatches_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('woo-variation-swatches/woo-variation-swatches.php');
    }

    /**
     * Checks if WP All Import is active.
     *
     * @return bool
     */
    /**
     * Checks if WCFM is active.
     *
     * Legacy: `fifu_is_wcfm_active()`.
     *
     * @return bool
     */
    public static function is_wcfm_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active('wc-frontend-manager/wc_frontend_manager.php');
    }

    /**
     * Extracts image URLs from WCFM widget content.
     *
     * Legacy: `fifu_get_wcfm_url()`.
     *
     * @param string $content
     *
     * @return string|null
     */
    public static function get_wcfm_image_url_from_content(string $content): ?string {
        $url_parts = explode('fifu_image_url=', $content);
        $url = $url_parts[1] ?? null;
        if ($url) {
            $decoded = urldecode(explode('&', $url)[0]);
            return $decoded;
        }
        return null;
    }

    /**
     * Checks if Toolset Forms plugin is active.
     *
     * Legacy: `fifu_is_toolset_active()`.
     *
     * @return bool
     */
    public static function is_toolset_forms_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active('cred-frontend-editor/plugin.php');
    }

    /**
     * Checks if AliPlugin is active.
     *
     * Legacy: `fifu_is_aliplugin_active()`.
     *
     * @return bool
     */
    public static function is_aliplugin_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active('aliplugin/aliplugin.php');
    }

    /**
     * Checks if SlotsLaunch is active.
     *
     * Legacy: `fifu_is_slotslaunch_active()`.
     *
     * @return bool
     */
    public static function is_slotslaunch_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active('slotslaunch-wp/slotslaunch.php');
    }

    /**
     * Checks if Sirv integration is active.
     *
     * Legacy: `fifu_is_sirv_active()`.
     *
     * @return bool
     */
    public static function is_sirv_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active('sirv/sirv.php');
    }

    /**
     * Checks if WooCommerce Multilingual (WCML) is active.
     *
     * Legacy: `fifu_wpml_is_wcml_active()`.
     *
     * @return bool
     */
    public static function is_wcml_active(): bool {
        return defined('WCML_VERSION') || function_exists('wcml_is_multi_currency_on');
    }
}
