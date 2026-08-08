<?php

defined( 'ABSPATH' ) || exit;

/**
 * Search Filter Pro integration helpers for FIFU.
 *
 * @package Fifu_Free
 */
class Fifu_Search_Filter_Pro_Integration {

    public static function is_search_filter_pro() {
        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            return ( isset( $screen->post_type ) && strpos( $screen->post_type, 'search-filter' ) !== false );
        }

        return false;
    }
}
