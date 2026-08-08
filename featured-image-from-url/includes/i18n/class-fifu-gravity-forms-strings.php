<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Gravity_Forms_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Gravity_Forms_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        $fifu['title']['addon'] = function () {
            return __("Field Add-on", FIFU_SLUG);
        };
        $fifu['field']['image'] = function () {
            return __("Featured Image", FIFU_SLUG);
        };
        $fifu['field']['slider'] = function () {
            return __("Featured Slider", FIFU_SLUG);
        };
        $fifu['field']['video'] = function () {
            return __("Featured Video", FIFU_SLUG);
        };
        $fifu['placeholder']['image'] = function () {
            return __("Image URL", FIFU_SLUG);
        };
        $fifu['placeholder']['video'] = function () {
            return __("Video URL", FIFU_SLUG);
        };
        $fifu['css']['title'] = function () {
            return __("Input CSS Classes", FIFU_SLUG);
        };
        $fifu['css']['desc'] = function () {
            return __("The CSS Class names to be added to the field input.", FIFU_SLUG);
        };
        $fifu['css']['settings'] = function () {
            return _e("Input CSS Classes", FIFU_SLUG);
        };

        return $fifu;
    }
}
