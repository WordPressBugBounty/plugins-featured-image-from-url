<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Quick_Edit_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Quick_Edit_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        // titles
        $fifu['title']['image'] = function () {
            return __("Featured image", FIFU_SLUG);
        };
        $fifu['title']['video'] = function () {
            return __("Featured video", FIFU_SLUG);
        };
        $fifu['title']['slider'] = function () {
            return __("Featured slider", FIFU_SLUG);
        };
        $fifu['title']['search'] = function () {
            return __("Image search", FIFU_SLUG);
        };
        $fifu['title']['gallery']['image'] = function () {
            return __("Image gallery", FIFU_SLUG);
        };
        $fifu['title']['gallery']['video'] = function () {
            return __("Video gallery", FIFU_SLUG);
        };
        $fifu['title']['variable']['product'] = function () {
            return __("Product", FIFU_SLUG);
        };
        $fifu['title']['variable']['variation'] = function () {
            return __("Variations", FIFU_SLUG);
        };
        $fifu['title']['variable']['name'] = function () {
            return __("Name", FIFU_SLUG);
        };

        // tips
        $fifu['tip']['column'] = function () {
            return __("Quick edit", FIFU_SLUG);
        };
        $fifu['tip']['image'] = function () {
            return __("Set featured image with URL", FIFU_SLUG);
        };
        $fifu['tip']['video'] = function () {
            return __("Set featured video with URL", FIFU_SLUG);
        };
        $fifu['tip']['search'] = function () {
            return __("Search images using keywords. Example: sun,sea", FIFU_SLUG);
        };

        // placeholder
        $fifu['url']['image'] = function () {
            return __("Image URL", FIFU_SLUG);
        };
        $fifu['url']['video'] = function () {
            return __("Video URL", FIFU_SLUG);
        };
        $fifu['image']['keywords'] = function () {
            return __("Keywords", FIFU_SLUG);
        };

        // button
        $fifu['button']['save'] = function () {
            return __("Save", FIFU_SLUG);
        };
        $fifu['button']['clean'] = function () {
            return __("Clear", FIFU_SLUG);
        };
        $fifu['button']['upload'] = function () {
            return __("Upload to media library", FIFU_SLUG);
        };
        $fifu['button']['uploading'] = function () {
            return __("Uploading...", FIFU_SLUG);
        };

        // pro
        $fifu['unlock'] = function () {
            return __("Upgrade to PRO", FIFU_SLUG);
        };

        return $fifu;
    }
}
