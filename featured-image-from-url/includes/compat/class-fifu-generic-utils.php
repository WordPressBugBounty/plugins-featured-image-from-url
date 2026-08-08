<?php

defined( 'ABSPATH' ) || exit;

/**
 * Generic utility helpers for FIFU.
 *
 * @package Fifu_Free
 */
class Fifu_Generic_Utils {

    public static function starts_with( $text, $substr ) {
        return substr( $text, 0, strlen( $substr ) ) === $substr;
    }

    public static function ends_with( $text, $substr ) {
        return substr( $text, -strlen( $substr ) ) === $substr;
    }

    public static function split_ratio( $ratio ) {
        if ( strpos( $ratio, ':' ) !== false ) {
            $aux = explode( ':', $ratio );
            return array( intval( $aux[0] ?? 0 ), intval( $aux[1] ?? 0 ) );
        }
        return null;
    }

    public static function is_portrait( $width, $height ) {
        return $height > $width;
    }

    public static function is_landscape( $width, $height ) {
        return $width >= $height;
    }

    public static function md5_vars( ...$args ) {
        $result = '';
        foreach ( $args as $arg ) {
            $result .= $arg;
        }
        return md5( $result );
    }

    public static function unit_test() {
        return 'Hello, World!';
    }
}
