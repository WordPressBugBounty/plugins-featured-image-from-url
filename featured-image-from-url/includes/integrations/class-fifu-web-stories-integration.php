<?php

defined( 'ABSPATH' ) || exit;

/**
 * Web Stories integration helpers for FIFU.
 *
 * @package Fifu_Free
 */
class Fifu_Web_Stories_Integration {

    /**
     * @return object|null
     */
    private static function get_current_screen_object() {
        $screen = null;

        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
        }

        if ( ! is_object( $screen ) && isset( $GLOBALS['current_screen'] ) && is_object( $GLOBALS['current_screen'] ) ) {
            $screen = $GLOBALS['current_screen'];
        }

        return is_object( $screen ) ? $screen : null;
    }

    public static function is_web_story() {
        $screen = self::get_current_screen_object();

        if ( is_object( $screen ) && isset( $screen->post_type ) && strpos( (string) $screen->post_type, 'web-story' ) !== false ) {
            return true;
        }

        if ( isset( $_REQUEST['_web_stories_envelope'] ) ) {
            return true;
        }

        return false;
    }
}
