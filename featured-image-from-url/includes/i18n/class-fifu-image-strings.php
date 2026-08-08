<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Image_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Image_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        $fifu['photo']['credit'] = function () {
            return __("Photo credit", FIFU_SLUG);
        };

        return $fifu;
    }
}
