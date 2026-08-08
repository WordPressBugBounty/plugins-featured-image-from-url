<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Admin_Support_Data_Page
{
    public static function render(): void
    {
        $fifu = Fifu_Admin_Strings::get_settings_strings();

        wp_enqueue_style('fifu-base-ui-css', plugins_url('/admin/html/css/base-ui.css', FIFU_PLUGIN_FILE), [], Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_style('fifu-menu-css', plugins_url('/admin/html/css/menu.css', FIFU_PLUGIN_FILE), [], Fifu_Plugin_Info::get_enqueue_version());

        wp_enqueue_script('fifu-support-data-js', plugins_url('/admin/html/js/support-data.js', FIFU_PLUGIN_FILE), ['jquery', 'jquery-ui'], Fifu_Plugin_Info::get_enqueue_version());

        $skip = self::opt_string('fifu_skip');
        $html_cpt = self::opt_string('fifu_html_cpt');
        $square_mobile = self::opt_string('fifu_square_mobile');
        $square_desktop = self::opt_string('fifu_square_desktop');
        $enable_debug = get_option('fifu_debug');
        $enable_photon = get_option('fifu_photon');
        $enable_cloud_hotlink = get_option('fifu_cloud_hotlink');
        $enable_cloud_delete_auto = get_option('fifu_cloud_delete_auto');
        $enable_cloud_upload_auto = get_option('fifu_cloud_upload_auto');
        $enable_cdn_content = get_option('fifu_cdn_content');
        $enable_reset = get_option('fifu_reset');
        $enable_fake = get_option('fifu_fake');
        $default_url = self::opt_string('fifu_default_url');
        $default_cpt = self::opt_string('fifu_default_cpt');
        $pcontent_types = self::opt_string('fifu_pcontent_types');
        $hide_format = self::opt_string('fifu_hide_format');
        $hide_type = self::opt_string('fifu_hide_type');
        $enable_default_url = get_option('fifu_enable_default_url');
        $enable_wc_lbox = get_option('fifu_wc_lbox');
        $enable_wc_zoom = get_option('fifu_wc_zoom');
        $enable_hide = get_option('fifu_hide');
        $enable_pcontent_add = get_option('fifu_pcontent_add');
        $enable_pcontent_remove = get_option('fifu_pcontent_remove');
        $enable_get_first = get_option('fifu_get_first');
        $enable_ovw_first = get_option('fifu_ovw_first');
        $enable_run_delete_all = get_option('fifu_run_delete_all');
        $enable_run_delete_all_time = get_option('fifu_run_delete_all_time');
        $enable_data_clean = 'toggleoff';
        $woo_version = '';
        if (\function_exists('WC')) {
            $wc = WC();
            if (\is_object($wc) && isset($wc->version)) {
                $woo_version = self::opt_string_value($wc->version);
            }
        }

        $fifu_stats = new Fifu_Migration_Stats();

        $product_count = 0;

        if (\post_type_exists('product')) {
            $product_posts = \wp_count_posts('product');
            $product_count = (int) ($product_posts->publish ?? 0);
        }

        ob_start();

        include FIFU_ADMIN_DIR . '/html/support-data-content.php';

        $support_data = (string) ob_get_clean();

        include FIFU_ADMIN_DIR . '/html/support-data.html';
    }

    private static function opt_string(string $key): string
    {
        $value = get_option($key);
        return self::opt_string_value($value);
    }

    /**
     * @param mixed $value
     */
    private static function opt_string_value($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        return '';
    }

}
