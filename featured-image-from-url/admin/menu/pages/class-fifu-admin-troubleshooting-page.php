<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Admin_Troubleshooting_Page
{
    public static function render(): void
    {
        flush();

        $fifu = Fifu_Admin_Strings::get_settings_strings();

        wp_enqueue_style('fifu-base-ui-css', plugins_url('/admin/html/css/base-ui.css', FIFU_PLUGIN_FILE), [], Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_style('fifu-menu-css', plugins_url('/admin/html/css/menu.css', FIFU_PLUGIN_FILE), [], Fifu_Plugin_Info::get_enqueue_version());
        wp_enqueue_script('fifu-troubleshooting-js', plugins_url('/admin/html/js/troubleshooting.js', FIFU_PLUGIN_FILE), ['jquery', 'jquery-ui'], Fifu_Plugin_Info::get_enqueue_version());

        include FIFU_ADMIN_DIR . '/html/troubleshooting.html';
    }
}
