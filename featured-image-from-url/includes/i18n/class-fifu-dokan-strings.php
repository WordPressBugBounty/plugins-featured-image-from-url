<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Dokan_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Dokan_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        $fifu['title']['product']['image'] = function () {
            _e("Product image", FIFU_SLUG);
        };
        $fifu['title']['product']['gallery'] = function () {
            _e("Image gallery", FIFU_SLUG);
        };

        $fifu['placeholder']['product']['image'] = function () {
            _e("Image URL", FIFU_SLUG);
        };

        return $fifu;
    }
}
