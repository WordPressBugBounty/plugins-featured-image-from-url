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
        $fifu['title']['media'] = function () {
            return __("Featured media", FIFU_SLUG);
        };
        $fifu['title']['image'] = function () {
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
        $fifu['title']['pro'] = function () {
            return __("PRO", FIFU_SLUG);
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
        $fifu['title']['gallery']['mixed'] = function () {
            return __("Image/video gallery", FIFU_SLUG);
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
        $fifu['url']['imageOrKeywords'] = function () {
            return __("Image URL or Keywords", FIFU_SLUG);
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
        $fifu['image']['keywords'] = function () {
            return __("Keywords", FIFU_SLUG);
        };
        $fifu['image']['alt'] = function () {
            return __("Alternative text", FIFU_SLUG);
        };
        $fifu['image']['altHelp'] = function () {
            return __("This field is used to provide alternative text for images, enhancing accessibility and SEO. If it is empty, then FIFU will use the post title automatically.", FIFU_SLUG);
        };

        // button
        $fifu['button']['save'] = function () {
            return __("Save", FIFU_SLUG);
        };
        $fifu['button']['preview'] = function () {
            return __("Preview", FIFU_SLUG);
        };
        $fifu['button']['clean'] = function () {
            return __("Clear", FIFU_SLUG);
        };
        $fifu['button']['removeImage'] = function () {
            return __("Remove remote image", FIFU_SLUG);
        };
        $fifu['button']['upload'] = function () {
            return __("Upload to media library", FIFU_SLUG);
        };
        $fifu['button']['uploading'] = function () {
            return __("Uploading...", FIFU_SLUG);
        };
        $fifu['button']['addImages'] = function () {
            return __("Add images to slider", FIFU_SLUG);
        };
        $fifu['button']['addMedia'] = function () {
            return __("Add Media", FIFU_SLUG);
        };
        $fifu['button']['copyDebugData'] = function () {
            return __("Copy debug data", FIFU_SLUG);
        };

        // pro
        $fifu['unlock'] = function () {
            return __("Upgrade to PRO", FIFU_SLUG);
        };

        return $fifu;
    }
}
