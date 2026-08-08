<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Elementor_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Elementor_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        $fifu['title']['image'] = function () {
            return __("Featured Image", FIFU_SLUG);
        };
        $fifu['section']['image'] = function () {
            return __("Featured image", FIFU_SLUG);
        };
        $fifu['control']['image'] = function () {
            return __("Image URL", FIFU_SLUG);
        };
        $fifu['control']['alt'] = function () {
            return __("Alternative text", FIFU_SLUG);
        };
        $fifu['title']['video'] = function () {
            return __("Featured Video", FIFU_SLUG);
        };
        $fifu['section']['video'] = function () {
            return __("Featured video", FIFU_SLUG);
        };
        $fifu['control']['video'] = function () {
            return __("Video URL", FIFU_SLUG);
        };
        $fifu['control']['pro'] = function () {
            return __("Requires FIFU PRO", FIFU_SLUG);
        };
        $fifu['help']['alt'] = function () {
            return __("This field is used to provide alternative text for images, enhancing accessibility and SEO. If it is empty, then FIFU will use the post title automatically.", FIFU_SLUG);
        };

        return $fifu;
    }
}
