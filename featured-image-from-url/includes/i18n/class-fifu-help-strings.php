<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Help_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Help_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        // title
        $fifu['title']['examples'] = function () {
            return __("Examples", FIFU_SLUG);
        };
        $fifu['title']['keywords'] = function () {
            return __("Keywords", FIFU_SLUG);
        };
        $fifu['title']['empty'] = function () {
            return __("Empty", FIFU_SLUG);
        };
        $fifu['title']['more'] = function () {
            return __("More", FIFU_SLUG);
        };
        $fifu['title']['url'] = function () {
            return __("Image URL", FIFU_SLUG);
        };
        $fifu['desc']['url'] = function () {
            return __("Loads the corresponding image. You should use an absolute URL, which means including the protocol (http/https) and the domain.", FIFU_SLUG);
        };
        $fifu['desc']['keywords'] = function () {
            return __("Loads a list of images from a search engine using the typed keywords. Choose the image that is most suitable.", FIFU_SLUG);
        };
        $fifu['desc']['empty'] = function () {
            return __("Loads a list of images from a search engine based on the post title. Choose the most suitable image.", FIFU_SLUG);
        };
        $fifu['desc']['more'] = function () {
            return __("FIFU can auto set images based on post title, remote web page address, ASIN, custom fields, and more. Check FIFU Settings → Automatic.", FIFU_SLUG);
        };
        $fifu['search']['loading'] = function () {
            return __("Loading...", FIFU_SLUG);
        };

        return $fifu;
    }
}
