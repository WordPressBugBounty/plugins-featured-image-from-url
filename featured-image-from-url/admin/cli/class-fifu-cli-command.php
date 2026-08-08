<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Fifu_Attachment_Update_Service', false)) {
    require_once __DIR__ . '/../../includes/meta/class-fifu-attachment-update-service.php';
}
if (!class_exists('Fifu_Attachment_Factory', false)) {
    require_once __DIR__ . '/../../includes/meta/class-fifu-attachment-factory.php';
}
if (!class_exists('Fifu_Default_Image_Service', false)) {
    require_once __DIR__ . '/../../includes/meta/class-fifu-default-image-service.php';
}
if (!class_exists('Fifu_Image_Url_Utils', false)) {
    require_once __DIR__ . '/../../includes/url/class-fifu-image-url-utils.php';
}

/**
 * The new central WP-CLI command handler for FIFU.
 */
class Fifu_CLI_Command extends \WP_CLI_Command {

    private static function warn(string $message): void
    {
        if (class_exists('\WP_CLI') && method_exists('\WP_CLI', 'warning')) {
            \WP_CLI::warning($message);
            return;
        }

        if (class_exists('\WP_CLI') && method_exists('\WP_CLI', 'line')) {
            \WP_CLI::line('Warning: ' . $message);
            return;
        }
    }

    /**
     * Resets plugin configuration to defaults.
     *
     * ## OPTIONS
     * [<state>]
     * : Reserved for future reset variants.
     *
     * @param array $args       Positional arguments passed to the command.
     * @param array $assoc_args Associative arguments passed to the command.
     */
    public function reset(array $args, array $assoc_args): void {
        Fifu_Settings_Manager::reset_settings();
    }

    /**
     * Toggles the debug option.
     *
     * ## OPTIONS
     * [<state>]
     * : on/off to enable or disable debug mode.
     */
    public function debug(array $args, array $assoc_args): void {
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_debug', 'toggleon', 'no');
                break;
            case 'off':
                update_option('fifu_debug', 'toggleoff', 'no');
                break;
        }
    }

    /**
     * Adjusts automatic content generation behavior.
     *
     * ## OPTIONS
     * [<state>]
     * : Enables or disables automatic content generation.
     *
     * [--skip=<string>]
     * : Updates the skip option for automatic content handling.
     *
     * [--cpt=<string>]
     * : Filters automatic content to the provided post type.
     *
     * [--overwrite=<on|off>]
     * : Adjusts whether newer content overwrites existing images.
     *
     * [--media=<string>]
     * : Filters media sources for automatic content.
     */
    public function content(array $args, array $assoc_args): void {
        if (array_key_exists('skip', $assoc_args)) {
            $skip = (string) $assoc_args['skip'];
            update_option('fifu_skip', $skip, 'no');
            return;
        }
        if (array_key_exists('cpt', $assoc_args)) {
            $cpt = (string) $assoc_args['cpt'];
            update_option('fifu_html_cpt', $cpt, 'no');
            return;
        }
        if (array_key_exists('overwrite', $assoc_args)) {
            $toggle = (string) $assoc_args['overwrite'];
            switch ($toggle) {
                case 'on':
                    update_option('fifu_ovw_first', 'toggleon', 'no');
                    break;
                case 'off':
                    update_option('fifu_ovw_first', 'toggleoff', 'no');
                    break;
            }
            return;
        }
        if (array_key_exists('media', $assoc_args)) {
            self::warn('Automatic content media selection is available in FIFU PRO.');
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_get_first', 'toggleon', 'no');
                break;
            case 'off':
                update_option('fifu_get_first', 'toggleoff', 'no');
                break;
        }
    }

    /**
     * Reports that title-based image search requires FIFU PRO.
     */
    public function search(array $args, array $assoc_args): void {
        self::warn('Auto set featured image using post title and search engine is available in FIFU PRO.');
    }

    /**
     * Manages ASIN lookup configuration.
     *
     * ## OPTIONS
     * [<state>]
     * : Enables or disables ASIN lookups.
     *
     * [--field=<string>]
     * : Sets the custom field name to use for ASIN lookups.
     */
    public function asin(array $args, array $assoc_args): void {
        self::warn('Auto set product images from ASIN is available in FIFU PRO.');
    }

    /**
     * Reports that custom-field media automation requires FIFU PRO.
     */
    public function customfield(array $args, array $assoc_args): void {
        self::warn('Auto set featured media from custom field is available in FIFU PRO.');
    }

    /**
     * Configures featured image behavior.
     *
     * ## OPTIONS
     * [<state>]
     * : Enables or disables featured image enhancements.
     *
     * [--pcontent-add=<on|off>]
     * : Toggles adding featured images to post content.
     *
     * [--pcontent-remove=<on|off>]
     * : Toggles removing duplicate featured images from content.
     *
     * [--pcontent-types=<string>]
     * : Filters which post types receive injected content images.
     *
     * [--hide=<on|off>]
     * : Toggles hiding the featured media output.
     *
     * [--hide-types=<string>]
     * : Filters the post types where media is hidden.
     *
     * [--hide-formats=<string>]
     * : Filters the post formats where media is hidden.
     *
     * [--default=<on|off>]
     * : Toggles the default featured image fallback.
     *
     * [--default-url=<string>]
     * : Sets the default image URL.
     *
     * [--default-types=<string>]
     * : Filters which post types use the default image.
     *
     * [--replace=<string>]
     * : Defines a replacement URL when media is missing.
     *
     * [--block=<on|off>]
     * : Toggles right-click blocking.
     *
     * [--popup=<on|off>]
     * : Toggles the popup notice for missing media.
     *
     * [--redirection=<on|off>]
     * : Toggles redirects from missing media URLs.
     *
     */
    public function image(array $args, array $assoc_args): void {
        if (array_key_exists('pcontent-add', $assoc_args)) {
            $toggle = (string) $assoc_args['pcontent-add'];
            switch ($toggle) {
                case 'on':
                    update_option('fifu_pcontent_add', 'toggleon', 'no');
                    break;
                case 'off':
                    update_option('fifu_pcontent_add', 'toggleoff', 'no');
                    break;
            }
            return;
        }
        if (array_key_exists('pcontent-remove', $assoc_args)) {
            $toggle = (string) $assoc_args['pcontent-remove'];
            switch ($toggle) {
                case 'on':
                    update_option('fifu_pcontent_remove', 'toggleon', 'no');
                    break;
                case 'off':
                    update_option('fifu_pcontent_remove', 'toggleoff', 'no');
                    break;
            }
            return;
        }
        if (array_key_exists('pcontent-types', $assoc_args)) {
            $types = (string) $assoc_args['pcontent-types'];
            update_option('fifu_pcontent_types', $types, 'no');
            return;
        }
        if (array_key_exists('hide', $assoc_args)) {
            $hideToggle = (string) $assoc_args['hide'];
            switch ($hideToggle) {
                case 'on':
                    update_option('fifu_hide', 'toggleon', 'no');
                    break;
                case 'off':
                    update_option('fifu_hide', 'toggleoff', 'no');
                    break;
            }
            return;
        }
        if (array_key_exists('hide-types', $assoc_args)) {
            $hideTypes = (string) $assoc_args['hide-types'];
            update_option('fifu_hide_type', $hideTypes, 'no');
            return;
        }
        if (array_key_exists('hide-formats', $assoc_args)) {
            $hideFormats = (string) $assoc_args['hide-formats'];
            update_option('fifu_hide_format', $hideFormats, 'no');
            return;
        }
        if (array_key_exists('default', $assoc_args)) {
            $defaultToggle = (string) $assoc_args['default'];
            switch ($defaultToggle) {
                case 'on':
                    update_option('fifu_enable_default_url', 'toggleon', 'no');
                    $default_url = get_option('fifu_default_url');
                    if (!$default_url) {
                        Fifu_Default_Image_Service::delete_default_image();
                    } elseif (Fifu_Options_Utils::is_on('fifu_fake')) {
                        $att_id = (int) get_option('fifu_default_attach_id');
                        $current_url = $att_id ? Fifu_Attachment_Update_Service::get_attachment_remote_url($att_id) : '';
                        if (!$att_id || !$current_url) {
                            $attachment_factory = new Fifu_Attachment_Factory();
                            $att_id = $attachment_factory->create_attachment_for_url($default_url);
                            if ($att_id > 0) {
                                Fifu_Attachment_Update_Service::initialize_remote_attachment($att_id, $default_url, null);
                                update_option('fifu_default_attach_id', $att_id, 'no');
                            }
                            Fifu_Default_Image_Service::apply_default_to_all_missing_thumbnails();
                        } else {
                            Fifu_Default_Image_Service::update_default_url($default_url);
                        }
                    }
                    break;
                case 'off':
                    update_option('fifu_enable_default_url', 'toggleoff', 'no');
                    Fifu_Default_Image_Service::delete_default_image();
                    break;
            }
            return;
        }
        if (array_key_exists('default-url', $assoc_args)) {
            $defaultUrl = (string) $assoc_args['default-url'];
            update_option('fifu_default_url', $defaultUrl, 'no');
            if (Fifu_Options_Utils::is_off('fifu_enable_default_url')) {
                Fifu_Default_Image_Service::delete_default_image();
            } elseif (!$defaultUrl) {
                Fifu_Default_Image_Service::delete_default_image();
            }
            return;
        }
        if (array_key_exists('default-types', $assoc_args)) {
            $defaultTypes = (string) $assoc_args['default-types'];
            update_option('fifu_default_cpt', $defaultTypes, 'no');
            return;
        }
        if (array_key_exists('replace', $assoc_args)) {
            self::warn('Replace Not Found Image is available in FIFU PRO.');
            return;
        }
        if (array_key_exists('block', $assoc_args)) {
            self::warn('Disable right-click is available in FIFU PRO.');
            return;
        }
        if (array_key_exists('popup', $assoc_args)) {
            self::warn('Custom Popup is available in FIFU PRO.');
            return;
        }
        if (array_key_exists('redirection', $assoc_args)) {
            self::warn('Page Redirection is available in FIFU PRO.');
            return;
        }
    }

    /**
     * Configures upload-related options.
     *
     * ## OPTIONS
     * [<state>]
     * : Enables or disables upload integrations.
     *
     * [--domain=<string>]
     * : Sets the domain used by uploads.
     *
     * [--show-button=<on|off>]
     * : Toggles whether the upload button is shown.
     *
     * [--job=<on|off>]
     * : Enables or disables the periodic upload job.
     *
     * [--proxy=<on|off>]
     * : Toggles the use of a proxy for upload traffic.
     *
     * [--private-proxy=<string>]
     * : Defines a private proxy host for uploads.
     */
    public function upload(array $args, array $assoc_args): void {
        self::warn('Save in the Media Library is available in FIFU PRO.');
    }

    /**
     * Controls featured video settings.
     *
     * ## OPTIONS
     * [<state>]
     * : Enables or disables featured video interactions.
     *
     * [--thumb-home=<on|off>]
     * : Toggles a thumbnail definition for the homepage.
     *
     * [--thumb-page=<on|off>]
     * : Toggles a thumbnail definition for pages.
     *
     * [--thumb-post=<on|off>]
     * : Toggles a thumbnail definition for posts.
     *
     * [--thumb-cpt=<on|off>]
     * : Toggles a thumbnail definition for custom post types.
     *
     * [--play=<on|off>]
     * : Toggles the video play button.
     *
     * [--play-color=<string>]
     * : Sets the video play button color.
     *
     * [--play-mode=<string>]
     * : Sets the play mode type.
     *
     * [--play-zindex=<integer>]
     * : Sets the play button z-index.
     *
     * [--play-size=<integer>]
     * : Sets the play button size.
     *
     * [--play-hide=<on|off>]
     * : Toggles play button hiding behavior.
     *
     * [--play-hide-wc=<on|off>]
     * : Toggles play button hiding behavior on WooCommerce.
     *
     * [--min-width=<integer>]
     * : Defines minimum video width for rendering.
     *
     * [--controls=<on|off>]
     * : Toggles video controls.
     *
     * [--mouse=<on|off>]
     * : Toggles mouse-over behavior.
     *
     * [--autoplay=<on|off>]
     * : Toggles autoplay functionality.
     *
     * [--autoplay-front=<on|off>]
     * : Toggles autoplay on the front page.
     *
     * [--autoplay-front-all=<on|off>]
     * : Toggles simultaneous autoplay for all homepage videos.
     *
     * [--autoplay-else=<on|off>]
     * : Toggles autoplay on other pages.
     *
     * [--loop=<on|off>]
     * : Toggles looping behavior.
     *
     * [--mute-desktop=<on|off>]
     * : Toggles mute on desktop.
     *
     * [--mute-mobile=<on|off>]
     * : Toggles mute on mobile.
     *
     * [--background=<on|off>]
     * : Toggles background video behavior.
     *
     * [--background-single=<on|off>]
     * : Toggles background video on single posts.
     *
     * [--vimeo-access-token=<string>]
     * : Sets the Vimeo access token used for metadata and oEmbed requests.
     *
     * [--vimeo-mp4-rendition=<rendition>]
     * : Sets the Vimeo API MP4 file rendition. Supported values: 1080p, 720p, 540p, 360p, 240p.
     *
     * [--privacy=<on|off>]
     * : Toggles video privacy defaults.
     *
     * [--later=<on|off>]
     * : Toggles the “later” button.
     *
     * [--later-left=<on|off>]
     * : Toggles the “later-left” button.
     */
    public function video(array $args, array $assoc_args): void {
        self::warn('Featured Video is available in FIFU PRO.');
    }

    /**
     * Discovery-only stub for Premium custom video support.
     *
     * ## OPTIONS
     * [<state>]
     * : Reserved for Premium custom video operations.
     */
    public function customvideo(array $args, array $assoc_args): void {
        self::warn('Custom Video is available in FIFU PRO.');
    }

    /**
     * Reports that license-key management requires FIFU Premium.
     *
     * ## OPTIONS
     *
     * [<state>]
     * : Reserved for compatibility with the Premium command.
     *
     * [--number=<string>]
     * : Ignored by the Free version.
     *
     * @param array $args       Positional arguments passed to the command.
     * @param array $assoc_args Associative arguments passed to the command.
     */
    public function key(array $args, array $assoc_args): void
    {
        self::warn('License key management is available in FIFU Premium.');
    }

    /**
     * Controls fake metadata maintenance.
     *
     * ## OPTIONS
     * [<state>]
     * : Enables or disables fake metadata updates.
     */
    public function metadata(array $args, array $assoc_args): void {
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_fake_stop', false, 'no');
                Fifu_Meta_Maintenance_Controller::enable_fake();
                update_option('fifu_fake', 'toggleon', 'no');
                break;
            case 'off':
                update_option('fifu_fake_stop', true, 'no');
                update_option('fifu_fake', 'toggleoff', 'no');
                break;
        }
    }

    /**
     * Triggers metadata cleanup routines.
     *
     * ## OPTIONS
     * None.
     */
    public function clean(array $args, array $assoc_args): void {
        Fifu_Meta_Maintenance_Controller::enable_clean();
        update_option('fifu_data_clean', 'toggleoff', 'no');
    }

    /**
     * Toggles metadata scheduling.
     *
     * ## OPTIONS
     * [<state>]
     * : Turns scheduled metadata updates on or off.
     */
    public function schedule(array $args, array $assoc_args): void {
        self::warn('Schedule Metadata Generation is available in FIFU PRO.');
    }

    /**
     * Updates CDN and performance-related toggles.
     *
     * ## OPTIONS
     * [<state>]
     * : Enables or disables CDN integrations.
     *
     * [--content=<on|off>]
     * : Enables or disables CDN content delivery.
     *
     * [--fifu=<on|off>]
     * : PRO compatibility placeholder for FIFU CDN delivery.
     *
     * [--domain=<on|off>]
     * : PRO compatibility placeholder for use of the site domain with CDN assets.
     */
    public function cdn(array $args, array $assoc_args): void {
        if (array_key_exists('content', $assoc_args)) {
            $toggle = (string) $assoc_args['content'];
            switch ($toggle) {
                case 'on':
                    update_option('fifu_cdn_content', 'toggleon', 'no');
                    break;
                case 'off':
                    update_option('fifu_cdn_content', 'toggleoff', 'no');
                    break;
            }
            return;
        }
        if (array_key_exists('fifu', $assoc_args)) {
            self::warn('FIFU CDN is available in FIFU PRO.');
            return;
        }
        if (array_key_exists('domain', $assoc_args)) {
            self::warn('Use your site domain with FIFU CDN is available in FIFU PRO.');
            return;
        }
        switch ($args[0] ?? '') {
            case 'on':
                update_option('fifu_photon', 'toggleon', 'no');
                break;
            case 'off':
                update_option('fifu_photon', 'toggleoff', 'no');
                break;
        }
    }

    /**
     * Adjusts square sizing overrides.
     *
     * ## OPTIONS
     * [<state>]
     * : Enables or disables square mode overrides.
     *
     * [--desktop=<string>]
     * : Desktop width for square mode.
     *
     * [--mobile=<string>]
     * : Mobile width for square mode.
     */
    public function square(array $args, array $assoc_args): void {
        if (array_key_exists('desktop', $assoc_args)) {
            $desktop = (string) $assoc_args['desktop'];
            update_option('fifu_square_desktop', $desktop, 'no');
            return;
        }
        if (array_key_exists('mobile', $assoc_args)) {
            $mobile = (string) $assoc_args['mobile'];
            update_option('fifu_square_mobile', $mobile, 'no');
            return;
        }
    }

    /**
     * Toggles audio behavior.
     *
     * ## OPTIONS
     * [<state>]
     * : Enables or disables audio playback.
     */
    public function audio(array $args, array $assoc_args): void {
        return;
    }

    public function bbpress(array $args, array $assoc_args): void {
        self::warn('bbPress and BuddyBoss Platform is available in FIFU PRO.');
    }

    /**
     * Persists custom size definitions.
     *
     * ## OPTIONS
     * [<state>]
     * : Enables or disables custom size handling.
     *
     * [--save=<string>]
     * : Saves a width x height pair for later reuse.
     */
    public function sizes(array $args, array $assoc_args): void {
        if (array_key_exists('save', $assoc_args)) {
            $size = explode('=', (string) $assoc_args['save']);
            $name = $size[0] ?? '';
            $size = explode('x', $size[1] ?? '');
            $w = (int) ($size[0] ?? 0);
            $h = (int) ($size[1] ?? 0);
            $c = ($size[2] ?? '0') === '1';
            $value = [
                'w' => $w,
                'h' => $h,
                'c' => $c,
            ];
            update_option('fifu_defined_size_' . $name, $value, 'no');
            return;
        }
    }

    /**
     * Slider CLI discovery stub kept for Premium visibility only.
     *
     * ## OPTIONS
     * [<state>]
     * : Enables or disables the Featured Slider toggle. (Available in Premium)
     *
     * [--time-image=<integer>]
     * : Sets the slider image pause duration.
     *
     * [--time-transition=<integer>]
     * : Sets the slider transition duration.
     *
     * [--left=<url>]
     * : Sets the left arrow URL.
     *
     * [--right=<url>]
     * : Sets the right arrow URL.
     */
    public function slider(array $args, array $assoc_args): void {
        self::warn('Featured Slider WP-CLI options are available in FIFU Premium.');
    }

    /**
     * Configures WooCommerce-specific options.
     *
     * ## OPTIONS
     * [<state>]
     * : Enables or disables WooCommerce integrations.
     *
     * [--lightbox=<on|off>]
     * : Toggles WooCommerce lightbox integration.
     *
     * [--zoom=<on|off>]
     * : Toggles WooCommerce zoom integration.
     *
     * [--category-auto=<on|off>]
     * : Toggles automatic category image creation.
     *
     * [--gallery=<on|off>]
     * : Toggles product gallery mode.
     *
     * [--adaptive=<on|off>]
     * : Toggles adaptive heights for product sliders.
     *
     * [--videos-before=<on|off>]
     * : Toggles video placement before images.
     *
     * [--variations-merge=<on|off>]
     * : Toggles variation merging in galleries.
     *
     * [--buy-text=<string>]
     * : Sets the quick buy button text.
     *
     * [--buy-disclaimer=<string>]
     * : Sets the quick buy disclaimer text.
     *
     * [--buy-cf=<string>]
     * : Sets the quick buy custom field.
     *
     * [--buy=<on|off>]
     * : Toggles quick buy behavior.
     */
    public function woo(array $args, array $assoc_args): void {
        if (array_key_exists('lightbox', $assoc_args)) {
            $toggle = (string) $assoc_args['lightbox'];
            switch ($toggle) {
                case 'on':
                    update_option('fifu_wc_lbox', 'toggleon', 'no');
                    break;
                case 'off':
                    update_option('fifu_wc_lbox', 'toggleoff', 'no');
                    break;
            }
            return;
        }
        if (array_key_exists('zoom', $assoc_args)) {
            $toggle = (string) $assoc_args['zoom'];
            switch ($toggle) {
                case 'on':
                    update_option('fifu_wc_zoom', 'toggleon', 'no');
                    break;
                case 'off':
                    update_option('fifu_wc_zoom', 'toggleoff', 'no');
                    break;
            }
            return;
        }
        if (array_key_exists('category-auto', $assoc_args)) {
            $toggle = (string) $assoc_args['category-auto'];
            switch ($toggle) {
                case 'on':
                    update_option('fifu_auto_category', 'toggleoff', 'no');
                    break;
                case 'off':
                    update_option('fifu_auto_category', 'toggleoff', 'no');
                    break;
            }
            return;
        }
        if (array_key_exists('buy-text', $assoc_args) || array_key_exists('buy-disclaimer', $assoc_args) || array_key_exists('buy-cf', $assoc_args)) {
            self::warn('Quick Buy settings are available in FIFU PRO only.');
            return;
        }
        if (array_key_exists('buy', $assoc_args)) {
            update_option('fifu_buy', 'toggleoff', false);
            self::warn('Quick Buy is available in FIFU PRO only.');
            return;
        }
    }

}
