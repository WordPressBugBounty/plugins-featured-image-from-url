<?php

defined( 'ABSPATH' ) || exit;

/**
 * HTML attribute helper utilities for FIFU.
 *
 * @package Fifu_Free
 */
class Fifu_Html_Attribute_Utils {

    public static function get_attribute( $attribute, $html ) {
        $attribute = $attribute . '=';
        if ( strpos( $html, $attribute ) === false ) {
            return null;
        }

        $aux = explode( $attribute, $html );
        $aux = $aux[1] ?? null;

        if ( empty( $aux ) ) {
            return null;
        }

        $quote = $aux[0] ?? '';

        if ( $quote === '&' ) {
            preg_match( '/^&[^;]+;/', $aux, $matches );
            if ( $matches ) {
                $quote = $matches[0] ?? '';
            }
        }

        $aux = explode( $quote, $aux );
        if ( $aux ) {
            return $aux[1] ?? null;
        }

        return null;
    }

    public static function replace_attribute( $html, $attribute, $value ) {
        $attribute = $attribute . '=';
        if ( strpos( $html, $attribute ) === false ) {
            return $html;
        }

        $matches = array();
        preg_match( '/' . $attribute . '[^ ]+/', $html, $matches );
        return str_replace( $matches[0] ?? '', $attribute . '"' . $value . '"', $html );
    }

    public static function get_delimiter( $property, $html ) {
        $delimiter = explode( $property . '=', $html );
        return $delimiter ? substr( $delimiter[1] ?? '', 0, 1 ) : null;
    }

    public static function normalize( $tag ) {
        $tag = str_replace( 'amp;', '', $tag );
        $tag = str_replace( '#038;', '', $tag );
        return $tag;
    }

    /**
     * Ensures an alt attribute exists on the provided HTML tag.
     *
     * @param string $html       Tag HTML.
     * @param string $custom_alt Alt text to enforce.
     * @return string
     */
    public static function ensure_alt_attribute( string $html, string $custom_alt ): string {
        preg_match( '/<img (.+?)\/?>/', $html, $matches );
        if ( ! isset( $matches[1] ) ) {
            return $html;
        }

        $attributes = $matches[1];
        if ( ! preg_match( '/alt=[\'\"][^[\'\"]*[\'\"]/', $attributes ) ) {
            $html = str_replace( '<img ', "<img {$custom_alt} ", $html );
        }

        return $html;
    }
}
