<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Plugins_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Plugins_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        $fifu['support'] = function () {
            return __("Technical support", FIFU_SLUG);
        };
        $fifu['upgrade'] = function () {
            return __("Upgrade to <b>PRO</b>", FIFU_SLUG);
        };
        $fifu['star'] = function () {
            return __("Are you enjoying FIFU? Please give it a 5-star rating!", FIFU_SLUG);
        };
        $fifu['settings'] = function () {
            return __("Settings", FIFU_SLUG);
        };

        // Plugins screen and review notice
        $fifu['rate'] = function () {
            return __("Rate ★★★★★", FIFU_SLUG);
        };
        $fifu['review']['title'] = function () {
            return __("Enjoying FIFU?", FIFU_SLUG);
        };
        $fifu['review']['message'] = function () {
            return __("If the Featured Image from URL plugin helps you, please consider leaving a 5-star review. It means a lot!", FIFU_SLUG);
        };
        $fifu['review']['leave'] = function () {
            return __("Leave a review", FIFU_SLUG);
        };
        $fifu['review']['later'] = function () {
            return __("Not now", FIFU_SLUG);
        };
        $fifu['review']['done'] = function () {
            return __("I already did", FIFU_SLUG);
        };
        $fifu['review']['help'] = function () {
            return __("Need help?", FIFU_SLUG);
        };

        return $fifu;
    }
}
