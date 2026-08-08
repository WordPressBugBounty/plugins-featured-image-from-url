<?php

defined( 'ABSPATH' ) || exit;

/**
 * WordPress context detection utilities for FIFU.
 *
 * @package Fifu_Free
 */
class Fifu_Wp_Context {

    public static function is_ajax_call() {
        return ( ( $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '' ) === 'XMLHttpRequest' ) || wp_doing_ajax();
    }

    public static function is_dashboard() {
        return ! is_home() &&
            ! is_singular( 'post' ) &&
            ! is_author() &&
            ! is_search() &&
            ! is_singular( 'page' ) &&
            ! is_singular( 'product' ) &&
            ! is_archive() &&
            ( ! class_exists( 'WooCommerce' ) || ( class_exists( 'WooCommerce' ) && ( ! is_shop() && ! is_product_category() && ! is_cart() ) ) );
    }

    /**
     * Returns the current WordPress admin screen when available.
     *
     * WordPress normally exposes the screen through get_current_screen(), but some
     * integration/CLI/bootstrap contexts only populate $GLOBALS['current_screen'].
     *
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

    public static function check_screen_base() {
        $screen = self::get_current_screen_object();

        if ( ! is_object( $screen ) || ! isset( $screen->base ) ) {
            return false;
        }

        switch ( $screen->base ) {
            case 'edit':
            case 'edit-tags':
                return 'list';
            case 'post':
            case 'term':
                return 'edit';
            case 'post-new':
                return 'new';
            default:
                return false;
        }
    }

    public static function on_cpt_page() {
        return strpos( $_SERVER['REQUEST_URI'] ?? '', 'wp-admin/edit.php' ) !== false &&
            strpos( $_SERVER['REQUEST_URI'] ?? '', 'post_type=' ) !== false;
    }

    public static function is_gutenberg_screen() {
        if ( ! is_admin() ) {
            return false;
        }

        $screen = self::get_current_screen_object();
        if ( ! is_object( $screen ) ) {
            return false;
        }

        if ( method_exists( $screen, 'is_block_editor' ) ) {
            return (bool) $screen->is_block_editor();
        }

        return (bool) ( $screen->is_block_editor ?? false );
    }

    /**
     * Indicates when the current screen is an editor instance.
     *
     * @return bool
     */
    public static function is_editor_screen(): bool {
        if ( ! is_admin() ) {
            return false;
        }

        $screen = self::get_current_screen_object();
        if ( ! is_object( $screen ) ) {
            return false;
        }

        $parent_base = $screen->parent_base ?? '';
        $is_block_editor = false;

        if ( method_exists( $screen, 'is_block_editor' ) ) {
            $is_block_editor = (bool) $screen->is_block_editor();
        } else {
            $is_block_editor = (bool) ( $screen->is_block_editor ?? false );
        }

        return $parent_base === 'edit' || $is_block_editor;
    }

    /**
     * Determines if the home or WooCommerce shop page is being viewed.
     *
     * @return bool
     */
    public static function is_home_or_shop(): bool {
        return is_home() || ( class_exists( 'WooCommerce' ) && is_shop() );
    }
}
