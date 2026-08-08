<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Meta_Box_Php_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Meta_Box_Php_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        // common
        $fifu['common']['wait'] = function () {
            return __("Please wait...", FIFU_SLUG);
        };
        $fifu['common']['image'] = function () {
            return __("Image URL", FIFU_SLUG);
        };
        $fifu['common']['alt'] = function () {
            return __("Alternative text", FIFU_SLUG);
        };

        // wait
        $fifu['title']['product']['image'] = function () {
            return __("Product image", FIFU_SLUG);
        };
        $fifu['title']['post']['image'] = function () {
            return __("Featured image", FIFU_SLUG);
        };

        return $fifu;
    }
}
