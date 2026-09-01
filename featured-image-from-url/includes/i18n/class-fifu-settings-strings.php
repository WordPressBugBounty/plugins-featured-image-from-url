<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Settings-specific UI strings for the admin area.
 *
 * @package Fifu_Free
 */
class Fifu_Settings_Strings {


    /**
     * @return array<string, mixed>
     */
    public static function get_tab_labels(): array {
        $tabs = array();

        // tabs
            $tabs['help'] = function () {
                _e("Help", FIFU_SLUG);
            };
            $tabs['admin'] = function () {
                _e("Admin", FIFU_SLUG);
            };
            $tabs['image'] = function () {
                _e("Image", FIFU_SLUG);
            };
            $tabs['auto'] = function () {
                _e("Automatic", FIFU_SLUG);
            };
            $tabs['metadata'] = function () {
                _e("Metadata", FIFU_SLUG);
            };
            $tabs['dev'] = function () {
                _e("Developers", FIFU_SLUG);
            };
            $tabs['slider'] = function () {
                _e("Slider", FIFU_SLUG);
            };
            $tabs['audio'] = function () {
                _e("Audio", FIFU_SLUG);
            };
            $tabs['video'] = function () {
                _e("Video", FIFU_SLUG);
            };
            $tabs['trouble'] = function () {
                _e("Troubleshooting", FIFU_SLUG);
            };
            $tabs['key'] = function () {
                _e("License key", FIFU_SLUG);
            };
            $tabs['cloud'] = function () {
                _e("Cloud", FIFU_SLUG);
            };

        return $tabs;
    }


    /**
     * @return array<string, mixed>
     */
    public static function get_option_labels(): array {
        $options = array();

        // options
            $options['settings'] = function () {
                return __("Settings", FIFU_SLUG);
            };
            $options['cloud'] = function () {
                return __("Cloud", FIFU_SLUG);
            };
            $options['troubleshooting'] = function () {
                return __("Troubleshooting", FIFU_SLUG);
            };
            $options['status'] = function () {
                return __("Status", FIFU_SLUG);
            };
            $options['upgrade'] = function () {
                return __("Upgrade to <b>PRO</b>", FIFU_SLUG);
            };

        return $options;
    }


    /**
     * @return array<string, mixed>
     */
    public static function get_help_texts(): array {
        $help = array();

        // support
            $help['support']['email'] = function () {
                _e("If you need help, refer to the troubleshooting or send an email to", FIFU_SLUG);
            };
            $help['support']['with'] = function () {
                _e("with this", FIFU_SLUG);
            };
            $help['support']['status'] = function () {
                _e("status", FIFU_SLUG);
            };

        // start
            $help['start']['url']['external'] = function () {
                _e("Hi, I'm a REMOTE image!", FIFU_SLUG);
            };
            $help['start']['url']['not'] = function () {
                _e("It means I'm NOT in your media library.", FIFU_SLUG);
            };
            $help['start']['url']['url'] = function () {
                _e("Don't you believe me? So why don't you check my Internet address (also known as URL)?", FIFU_SLUG);
            };
            $help['start']['url']['right'] = function () {
                _e("1. Right-click on me now", FIFU_SLUG);
            };
            $help['start']['url']['copy'] = function () {
                _e("2. Select \"Copy image address\"", FIFU_SLUG);
            };
            $help['start']['url']['paste'] = function () {
                _e("3. Paste it here:", FIFU_SLUG);
            };
            $help['start']['url']['drag'] = function () {
                _e("Or just drag me and drop me here", FIFU_SLUG);
            };
            $help['start']['url']['click'] = function () {
                _e("Right click me!", FIFU_SLUG);
            };
            $help['start']['post']['famous'] = function () {
                _e("Now that you have my address (also known as URL), how about making me famous?", FIFU_SLUG);
            };
            $help['start']['post']['create'] = function () {
                _e("You just need to create a post and use me as the featured image:", FIFU_SLUG);
            };
            $help['start']['post']['new'] = function () {
                _e("1. Add a new post", FIFU_SLUG);
            };
            $help['start']['post']['box'] = function () {
                _e("2. Find the field", FIFU_SLUG);
            };
            $help['start']['post']['featured'] = function () {
                _e("Featured image", FIFU_SLUG);
            };
            $help['start']['post']['address'] = function () {
                _e("3. Paste my address into \"Image URL\" field.", FIFU_SLUG);
            };
            $help['start']['post']['storage'] = function () {
                _e("And don't worry about storage. I will NOT be uploaded to your media library.", FIFU_SLUG);
            };

        // dev
            $help['dev']['function'] = function () {
                _e("Are you a WordPress developer? Now you can easily integrate your code with FIFU using the functions below.", FIFU_SLUG);
            };
            $help['dev']['args'] = function () {
                _e("All you need is to provide the post ID and the image URL. FIFU plugin will handle the rest by setting the custom fields and creating the metadata.", FIFU_SLUG);
            };
            $help['dev']['field']['image'] = function () {
                _e("Featured image", FIFU_SLUG);
            };
            $help['dev']['field']['video'] = function () {
                _e("Featured video", FIFU_SLUG);
            };
            $help['dev']['field']['slider'] = function () {
                _e("Featured slider", FIFU_SLUG);
            };
            $help['dev']['field']['product']['image'] = function () {
                _e("Product image", FIFU_SLUG);
            };
            $help['dev']['field']['product']['video'] = function () {
                _e("Product video", FIFU_SLUG);
            };
            $help['dev']['field']['gallery']['image'] = function () {
                _e("Image gallery", FIFU_SLUG);
            };
            $help['dev']['field']['gallery']['video'] = function () {
                _e("Video gallery", FIFU_SLUG);
            };
            $help['dev']['field']['category']['image'] = function () {
                _e("Product category image", FIFU_SLUG);
            };
            $help['dev']['field']['category']['video'] = function () {
                _e("Product category video", FIFU_SLUG);
            };

        return $help;
    }


    /**
     * @return array<string, mixed>
     */
    public static function get_messages(): array {
        $messages = array();

        // messages
            $messages['wait'] = function () {
                _e("Please wait a few seconds...", FIFU_SLUG);
            };

        return $messages;
    }


}
