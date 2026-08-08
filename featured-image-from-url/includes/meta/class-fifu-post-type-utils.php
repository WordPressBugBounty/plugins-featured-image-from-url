<?php

defined( 'ABSPATH' ) || exit;

/**
 * Post type and format helpers for FIFU.
 *
 * @package Fifu_Free
 */
class Fifu_Post_Type_Utils {

    public static function get_post_types() {
        $arr = array();
        foreach ( get_post_types() as $post_type ) {
            if ( post_type_supports( $post_type, 'thumbnail' ) ) {
                $arr[] = $post_type;
            }
        }

        return $arr;
    }

    public static function get_post_types_str() {
        $str = '';
        $i   = 0;
        foreach ( self::get_post_types() as $type ) {
            $str = ( $i++ === 0 ) ? $type : $str . ', ' . $type;
        }
        return $str;
    }

    public static function get_post_formats_str() {
        $post_formats = array_keys( get_post_format_strings() );
        return implode( ', ', $post_formats );
    }

    public static function get_default_cpt_arr() {
        $cpts = get_option( 'fifu_default_cpt' );
        if ( ! $cpts ) {
            return null;
        }
        return explode( ',', str_replace( ' ', '', $cpts ) );
    }

    public static function is_valid_default_cpt( $post_id ) {
        $cpts = self::get_default_cpt_arr();
        if ( ! $cpts ) {
            return false;
        }
        $type = get_post_type( $post_id );
        return in_array( $type, $cpts, true );
    }

    public static function is_valid_cpt( $post_id ) {
        $types = get_option( 'fifu_html_cpt' );
        if ( ! $types ) {
            return true;
        }

        $types = explode( ',', $types );
        $type  = get_post_type( $post_id );

        foreach ( $types as $t ) {
            if ( $t === $type ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if a post type is considered a custom post type for FIFU.
     *
     * @param int|null $post_id Optional post ID context.
     * @return bool
     */
    public static function is_custom_post_type( ?int $post_id = null ): bool {
        $resolved_post_id = $post_id;

        if (
            ! $resolved_post_id &&
            function_exists( 'is_singular' ) &&
            is_singular() &&
            function_exists( 'get_queried_object_id' )
        ) {
            $resolved_post_id = (int) get_queried_object_id();
        }

        if ( ! $resolved_post_id ) {
            $resolved_post_id = (int) get_the_ID();
        }

        if ( ! $resolved_post_id ) {
            return false;
        }

        $type = get_post_type( $resolved_post_id );
        if ( ! $type ) {
            return false;
        }

        $valid_types = array_diff( self::get_post_types(), array( 'post', 'page' ) );
        return in_array( $type, $valid_types, true );
    }

    /**
     * Determines whether the current singular context is a custom post type.
     *
     * @param int|null $post_id Optional post ID context.
     * @return bool
     */
    public static function is_singular_custom_post_type( ?int $post_id = null ): bool {
        if ( ! function_exists( 'is_singular' ) || ! is_singular() ) {
            return false;
        }

        $resolved_post_id = $post_id;

        if ( null === $resolved_post_id ) {
            if ( function_exists( 'get_queried_object_id' ) ) {
                $resolved_post_id = (int) get_queried_object_id();
            }

            if ( ! $resolved_post_id && function_exists( 'get_the_ID' ) ) {
                $resolved_post_id = (int) get_the_ID();
            }
        }

        if ( ! $resolved_post_id ) {
            return false;
        }

        $type = get_post_type( $resolved_post_id );
        if ( ! $type ) {
            return false;
        }

        if ( in_array( $type, array( 'post', 'page' ), true ) ) {
            return false;
        }

        if ( function_exists( 'get_post_type_object' ) ) {
            $post_type_object = get_post_type_object( $type );
            if ( $post_type_object ) {
                return empty( $post_type_object->_builtin );
            }
        }

        return in_array( $type, get_post_types(), true );
    }
}
