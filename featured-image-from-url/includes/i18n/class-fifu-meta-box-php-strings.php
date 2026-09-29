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
        $fifu['common']['updating_featured_image'] = function () {
            return __("Updating featured image...", FIFU_SLUG);
        };
        $fifu['common']['image'] = function () {
            return __("Image URL", FIFU_SLUG);
        };
        $fifu['common']['alt'] = function () {
            return __("Alternative text", FIFU_SLUG);
        };
        $fifu['common']['media'] = function () {
            return __("Featured media", FIFU_SLUG);
        };
        $fifu['common']['media_type'] = function () {
            return __("Media type", FIFU_SLUG);
        };
        $fifu['common']['pro'] = function () {
            return __("PRO", FIFU_SLUG);
        };
        $fifu['common']['upgrade'] = function () {
            return __("Upgrade to PRO", FIFU_SLUG);
        };
        $fifu['common']['add_media'] = function () {
            return __("Add Media", FIFU_SLUG);
        };

        // wait
        $fifu['title']['product']['image'] = function () {
            return __("Product image", FIFU_SLUG);
        };
        $fifu['title']['post']['image'] = function () {
            return __("Featured image", FIFU_SLUG);
        };
        $fifu['title']['video'] = function () {
            return __("Featured video", FIFU_SLUG);
        };
        $fifu['title']['slider'] = function () {
            return __("Featured slider", FIFU_SLUG);
        };
        $fifu['title']['audio'] = function () {
            return __("Featured audio", FIFU_SLUG);
        };
        $fifu['title']['product_gallery'] = function () {
            return __("Image/video gallery", FIFU_SLUG);
        };
        $fifu['url']['video'] = function () {
            return __("Video URL", FIFU_SLUG);
        };
        $fifu['url']['audio'] = function () {
            return __("Audio URL", FIFU_SLUG);
        };
        $fifu['url']['thumbnail'] = function () {
            return __("Thumbnail URL", FIFU_SLUG);
        };
        $fifu['button']['add_images_slider'] = function () {
            return __("Add images to slider", FIFU_SLUG);
        };
        $fifu['button']['preview'] = function () {
            return __("Preview", FIFU_SLUG);
        };
        $fifu['button']['copy_debug_data'] = function () {
            return __("Copy debug data", FIFU_SLUG);
        };

        return $fifu;
    }
}
