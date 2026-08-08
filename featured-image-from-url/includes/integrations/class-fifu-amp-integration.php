<?php

defined( 'ABSPATH' ) || exit;

/**
 * AMP integration helpers for FIFU.
 *
 * @package Fifu_Free
 */
class Fifu_Amp_Integration {

    public static function is_amp_request() {
        return function_exists( 'amp_is_request' ) && amp_is_request();
    }

    public static function amp_url( $url, $width, $height ) {
        return array( 0 => $url, 1 => $width, 2 => $height );
    }
}
