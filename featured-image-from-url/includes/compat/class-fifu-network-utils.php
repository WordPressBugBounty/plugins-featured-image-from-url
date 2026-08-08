<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Network helpers for FIFU multisite contexts.
 *
 * @package Fifu_Free
 */
class Fifu_Network_Utils {

    /**
     * Execute the provided callback while ensuring the main site context is active.
     *
     * @param callable $callback Callback to execute on the main site.
     */
    public static function with_main_site( callable $callback ): void {
        $switched = false;

        if ( is_multisite() ) {
            $main_site_id = function_exists( 'get_main_site_id' ) ? get_main_site_id() : 0;
            if ( $main_site_id ) {
                $current_blog_id = get_current_blog_id();
                if ( $current_blog_id !== $main_site_id ) {
                    switch_to_blog( $main_site_id );
                    $switched = true;
                }
            }
        }

        try {
            call_user_func( $callback );
        } finally {
            if ( $switched ) {
                restore_current_blog();
            }
        }
    }

    /**
     * Retrieve the network menu HTML for the plugin page.
     */
    public static function get_network_menu_html(): void {
        self::with_main_site([Fifu_Admin_Menu::class, 'render_menu_page']);
    }

    /**
     * Handle the network cloud section for the plugin.
     */
    public static function cloud(): void {
        self::with_main_site([Fifu_Admin_Cloud_Page::class, 'render']);
    }

    /**
     * Render the network troubleshooting interface.
     */
    public static function troubleshooting(): void {
        self::with_main_site( [Fifu_Admin_Troubleshooting_Page::class, 'render'] );
    }

    /**
     * Collect and output network support data.
     */
    public static function support_data(): void {
        self::with_main_site( [Fifu_Admin_Support_Data_Page::class, 'render'] );
    }
}
