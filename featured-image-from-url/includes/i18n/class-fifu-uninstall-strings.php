<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Uninstall_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Uninstall_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        $fifu['button']['text']['clean'] = function () {
            return __("Clear metadata and deactivate", FIFU_SLUG);
        };
        $fifu['button']['text']['deactivate'] = function () {
            return __("Deactivate", FIFU_SLUG);
        };
        $fifu['button']['description']['clean'] = function () {
            return __("If you don't intend to use FIFU again", FIFU_SLUG);
        };
        $fifu['button']['description']['deactivate'] = function () {
            return __("If it's a temporary deactivation", FIFU_SLUG);
        };
        $fifu['text']['why'] = function () {
            return __("Why are you deactivating FIFU?", FIFU_SLUG);
        };
        $fifu['text']['email'] = function () {
            return __("The developer will respond within 8 hours.", FIFU_SLUG);
        };
        $fifu['text']['reason']['conflict'] = function () {
            return __("Doesn't work with a specific theme, plugin, or URL...", FIFU_SLUG);
        };
        $fifu['text']['reason']['pro'] = function () {
            return __("Works well, but I would need a new or PRO feature...", FIFU_SLUG);
        };
        $fifu['text']['reason']['seo'] = function () {
            return __("Concerned about SEO, performance, or copyright...", FIFU_SLUG);
        };
        $fifu['text']['reason']['local'] = function () {
            return __("I wish it worked with my local images...", FIFU_SLUG);
        };
        $fifu['text']['reason']['undestand'] = function () {
            return __("I didn't understand how it works...", FIFU_SLUG);
        };
        $fifu['text']['reason']['others'] = function () {
            return __("Others...", FIFU_SLUG);
        };

        return $fifu;
    }
}
