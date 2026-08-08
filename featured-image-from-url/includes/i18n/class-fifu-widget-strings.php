<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Widget_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Widget_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        // words
        $fifu['word']['settings'] = function () {
            return _e("Settings", FIFU_SLUG);
        };
        $fifu['word']['rows'] = function () {
            return _e("Rows", FIFU_SLUG);
        };
        $fifu['word']['columns'] = function () {
            return _e("Columns", FIFU_SLUG);
        };

        // label
        $fifu['label']['gallery'] = function () {
            return _e("Product gallery settings", FIFU_SLUG);
        };

        // titles
        $fifu['title']['media'] = function () {
            return __("Featured media", FIFU_SLUG);
        };
        $fifu['title']['grid'] = function () {
            return __("Featured grid", FIFU_SLUG);
        };
        $fifu['title']['gallery'] = function () {
            return __("Product gallery", FIFU_SLUG);
        };
        $fifu['title']['slider'] = function () {
            return _e("Featured slider", FIFU_SLUG);
        };

        // description
        $fifu['description']['media'] = function () {
            return __("Displays the featured image, video, or slider from the current post, page, or custom post type.", FIFU_SLUG);
        };
        $fifu['description']['grid'] = function () {
            return __("Displays the images from the featured slider in a grid format.", FIFU_SLUG);
        };
        $fifu['description']['gallery'] = function () {
            return __("Displays the product gallery.", FIFU_SLUG);
        };

        return $fifu;
    }
}
