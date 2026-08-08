<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Block_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Block_Strings {

    /**
     * Reset any internal state kept for test isolation.
     *
     * Intentionally empty: this class currently does not cache mutable state,
     * but tests rely on a stable reset contract.
     *
     * @return void
     */
    public static function reset(): void {
        // Intentionally empty: kept for test isolation and API symmetry.
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        $fifu['title']['image'] = function () {
            return __("Featured Image (URL)", FIFU_SLUG);
        };
        $fifu['description']['image'] = function () {
            return __("Display a post's featured image from a URL.", FIFU_SLUG);
        };
        $fifu['label']['set'] = function () {
            return __("No featured image set.", FIFU_SLUG);
        };
        $fifu['label']['settings'] = function () {
            return __("Settings", FIFU_SLUG);
        };
        $fifu['label']['image'] = function () {
            return __("Image URL", FIFU_SLUG);
        };
        $fifu['label']['alt'] = function () {
            return __("Alternative text", FIFU_SLUG);
        };
        $fifu['section']['image'] = function () {
            return __("Featured image", FIFU_SLUG);
        };
        $fifu['placeholder']['paste'] = function () {
            return __("Paste image URL here…", FIFU_SLUG);
        };
        $fifu['link']['remove'] = function () {
            return __("Remove remote image", FIFU_SLUG);
        };
        $fifu['help']['alt'] = function () {
            return __("This field is used to provide alternative text for images, enhancing accessibility and SEO. If it is empty, then FIFU will use the post title automatically.", FIFU_SLUG);
        };

        return $fifu;
    }
}
