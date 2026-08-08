<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Api_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Api_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        $fifu['info']['try'] = function () {
            return __("try again later", FIFU_SLUG);
        };

        return $fifu;
    }
}
