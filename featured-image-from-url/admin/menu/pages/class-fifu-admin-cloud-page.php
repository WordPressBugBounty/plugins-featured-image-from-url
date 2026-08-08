<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Admin_Cloud_Page
{
    public static function render(): void
    {
        flush();

        $fifucloud = Fifu_Cloud_Strings::get_strings();

        wp_enqueue_style(
            'fifu-menu-su-css',
            plugins_url('/admin/html/css/menu-su.css', FIFU_PLUGIN_FILE),
            [],
            Fifu_Plugin_Info::get_enqueue_version()
        );
        wp_enqueue_style(
            'fifu-pro-css',
            plugins_url('/admin/html/css/pro.css', FIFU_PLUGIN_FILE),
            [],
            Fifu_Plugin_Info::get_enqueue_version()
        );
        wp_enqueue_script(
            'fifu-menu-su-js',
            plugins_url('/admin/html/js/menu-su.js', FIFU_PLUGIN_FILE),
            ['jquery', 'jquery-ui', 'fifu-rest-route-js'],
            Fifu_Plugin_Info::get_enqueue_version()
        );

        wp_enqueue_style(
            'fifu-base-ui-css',
            plugins_url('/admin/html/css/base-ui.css', FIFU_PLUGIN_FILE),
            [],
            Fifu_Plugin_Info::get_enqueue_version()
        );
        wp_enqueue_style(
            'fifu-menu-css',
            plugins_url('/admin/html/css/menu.css', FIFU_PLUGIN_FILE),
            [],
            Fifu_Plugin_Info::get_enqueue_version()
        );
        wp_enqueue_script(
            'fifu-cloud-js',
            plugins_url('/admin/html/js/cloud.js', FIFU_PLUGIN_FILE),
            ['jquery', 'jquery-ui', 'fifu-menu-su-js'],
            Fifu_Plugin_Info::get_enqueue_version()
        );

        wp_localize_script('fifu-cloud-js', 'fifuScriptCloudVars', [
            'signUpComplete' => self::is_sign_up_complete(),
            'restNamespaceV2' => defined('FIFU_REST_NAMESPACE_V2') ? FIFU_REST_NAMESPACE_V2 : FIFU_SLUG . '/v2',
            'down' => self::get_string($fifucloud, ['ws', 'down']),
            'notConnected' => self::get_string($fifucloud, ['ws', 'connection', 'fail']),
            'noImages' => self::get_string($fifucloud, ['table', 'no', 'images']),
            'noPosts' => self::get_string($fifucloud, ['table', 'no', 'posts']),
            'noData' => self::get_string($fifucloud, ['table', 'no', 'data']),
            'filterResults' => self::get_string($fifucloud, ['table', 'filter']),
            'showResults' => self::get_string($fifucloud, ['table', 'show']),
            'selectAll' => self::get_string($fifucloud, ['table', 'select', 'all']),
            'limit' => self::get_string($fifucloud, ['table', 'limit']),
            'selectNone' => self::get_string($fifucloud, ['table', 'select', 'none']),
            'delete' => self::get_string($fifucloud, ['table', 'delete']),
            'load' => self::get_string($fifucloud, ['table', 'load']),
            'category' => self::get_string($fifucloud, ['table', 'category']),
            'slider' => self::get_string($fifucloud, ['table', 'slider']),
            'gallery' => self::get_string($fifucloud, ['table', 'gallery']),
            'featured' => self::get_string($fifucloud, ['table', 'featured']),
            'dialogDelete' => self::get_string($fifucloud, ['table', 'dialog', 'delete']),
            'dialogCancel' => self::get_string($fifucloud, ['table', 'dialog', 'cancel']),
            'dialogYes' => self::get_string($fifucloud, ['table', 'dialog', 'yes']),
            'dialogNo' => self::get_string($fifucloud, ['table', 'dialog', 'no']),
            'upload' => self::get_string($fifucloud, ['table', 'upload']),
            'link' => self::get_string($fifucloud, ['table', 'link']),
        ]);

        $enable_cloud_upload_auto = get_option('fifu_cloud_upload_auto');
        $enable_cloud_delete_auto = get_option('fifu_cloud_delete_auto');
        $enable_cloud_hotlink = get_option('fifu_cloud_hotlink');

        include FIFU_ADMIN_DIR . '/html/cloud.html';

        $cloudCronSettingsChanged = false;

        if (
            Fifu_Settings_Manager::is_valid_nonce(
                'nonce_fifu_form_cloud_upload_auto',
                Fifu_Admin_Menu::ACTION_CLOUD
            )
        ) {
            Fifu_Settings_Manager::update_single_option(
                'fifu_input_cloud_upload_auto',
                'fifu_cloud_upload_auto'
            );

            $cloudCronSettingsChanged = true;
        }

        if (
            Fifu_Settings_Manager::is_valid_nonce(
                'nonce_fifu_form_cloud_delete_auto',
                Fifu_Admin_Menu::ACTION_CLOUD
            )
        ) {
            Fifu_Settings_Manager::update_single_option(
                'fifu_input_cloud_delete_auto',
                'fifu_cloud_delete_auto'
            );

            $cloudCronSettingsChanged = true;
        }

        if (Fifu_Settings_Manager::is_valid_nonce('nonce_fifu_form_cloud_hotlink', Fifu_Admin_Menu::ACTION_CLOUD)) {
            Fifu_Settings_Manager::update_single_option('fifu_input_cloud_hotlink', 'fifu_cloud_hotlink');
        }

        if ($cloudCronSettingsChanged) {
            Fifu_Cloud_Cron_Service::sync_schedules();
        }
    }

    public static function is_sign_up_complete(): bool
    {
        return isset(get_option('fifu_su_privkey')[0]);
    }

    public static function get_string(array $strings, array $path): string
    {
        $value = $strings;
        foreach ($path as $segment) {
            if (!isset($value[$segment])) {
                return '';
            }
            $value = $value[$segment];
        }

        if (!is_callable($value)) {
            return '';
        }

        ob_start();
        $return = $value();
        $output = ob_get_clean();
        $content = $output !== '' ? $output : $return ?? '';
        return trim((string) $content);
    }
}
