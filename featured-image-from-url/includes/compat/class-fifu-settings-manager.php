<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Central manager for FIFU Free settings and options.
 *
 * @package Fifu_Free
 */
class Fifu_Settings_Manager {

    /** @var bool[] Tracks reset_settings invocations for tests. */
    public static array $reset_settings_calls = [];

    /**
     * Missing list of menu-related options derived from the legacy FIFU_SETTINGS constant.
     *
     * @var string[]
     */
    private const MENU_SETTINGS = [
        'fifu_skip',
        'fifu_html_cpt',
        'fifu_square_mobile',
        'fifu_square_desktop',
        'fifu_debug',
        'fifu_photon',
        'fifu_cdn_content',
        'fifu_reset',
        'fifu_fake',
        'fifu_default_url',
        'fifu_default_cpt',
        'fifu_pcontent_types',
        'fifu_hide_format',
        'fifu_hide_type',
        'fifu_enable_default_url',
        'fifu_wc_lbox',
        'fifu_wc_zoom',
        'fifu_hide',
        'fifu_pcontent_add',
        'fifu_pcontent_remove',
        'fifu_get_first',
        'fifu_ovw_first',
        'fifu_run_delete_all',
        'fifu_data_clean',
        'fifu_cloud_upload_auto',
        'fifu_cloud_delete_auto',
        'fifu_cloud_hotlink',
    ];

    private const FREE_PRO_ONLY_PERSISTENCE_BLOCKED_OPTIONS = [
        'fifu_taxonomy',
        'fifu_auto_category',
        'fifu_buy',
        'fifu_order_email',
        'fifu_buy_text',
        'fifu_buy_disclaimer',
        'fifu_buy_cf',
        'fifu_videos_before',
        'fifu_variations_merge',
        'fifu_gallery',
        'fifu_adaptive_height',
    ];

    private const OBSOLETE_FREE_ASIN_OPTIONS = [
        'fifu_asin',
        'fifu_asin_custom_field',
        'fifu_asin_credentials_partner',
        'fifu_asin_credentials_access',
        'fifu_asin_credentials_secret',
        'fifu_asin_credentials_locale',
    ];

    private const OBSOLETE_FREE_ISBN_OPTIONS = [
        'fifu_isbn',
        'fifu_isbn_custom_field',
    ];

    private const OBSOLETE_FREE_HTML_MEDIA_OPTIONS = [
        'fifu_html_media',
    ];

    private const OBSOLETE_FREE_VIDEO_OPTIONS = [
        'fifu_video',
        'fifu_video_background',
        'fifu_video_background_single',
        'fifu_video_privacy',
        'fifu_vimeo_access_token',
        'fifu_vimeo_access_token_scopes',
        'fifu_vimeo_mp4_rendition',
        'fifu_video_later',
        'fifu_video_later_left',
        'fifu_video_thumb',
        'fifu_video_thumb_page',
        'fifu_video_thumb_post',
        'fifu_video_thumb_cpt',
        'fifu_video_play_button',
        'fifu_video_play_hide_grid',
        'fifu_video_play_hide_grid_wc',
        'fifu_video_color',
        'fifu_video_zindex',
        'fifu_video_size',
        'fifu_play_type',
        'fifu_autoplay',
        'fifu_autoplay_front',
        'fifu_autoplay_elsewhere',
        'fifu_loop',
        'fifu_video_mute',
        'fifu_video_mute_mobile',
        'fifu_video_min_width',
        'fifu_mouse_video',
        'fifu_video_controls',
    ];

    private const OBSOLETE_FREE_POPUP_REDIRECTION_OPTIONS = [
        'fifu_popup',
        'fifu_redirection',
    ];

    private const OBSOLETE_FREE_OTFCDN_OPTIONS = [
        'fifu_otfcdn',
        'fifu_own_domain',
    ];

    private const OBSOLETE_FREE_BLOCK_UPLOAD_OPTIONS = [
        'fifu_block',
        'fifu_upload_show',
        'fifu_upload_job',
        'fifu_upload_proxy',
        'fifu_upload_private_proxy',
        'fifu_upload_domain',
        'fifu_cache_proxy',
        'fifu_proxy_list',
        'fifu_proxy_list_expiration',
        'fifu_proxy_list_timeout',
    ];

    private const OBSOLETE_FREE_REPLACE_BBPRESS_OPTIONS = [
        'fifu_error_url',
        'fifu_bbpress_fields',
    ];

    private const OBSOLETE_FREE_SLIDER_OPTIONS = [
        'fifu_slider',
        'fifu_slider_auto',
        'fifu_slider_gallery',
        'fifu_slider_thumb',
        'fifu_slider_counter',
        'fifu_slider_crop',
        'fifu_slider_single',
        'fifu_slider_vertical',
        'fifu_slider_ctrl',
        'fifu_slider_stop',
        'fifu_slider_speed',
        'fifu_slider_pause',
        'fifu_slider_left',
        'fifu_slider_right',
    ];

    /**
     * Provides the menu settings keys that will replace FIFU_SETTINGS.
     *
     * @return string[]
     */
    public static function get_menu_settings(): array {
        return self::MENU_SETTINGS;
    }

    // TODO: Add metadata map (types, defaults, validation) currently implicit in the legacy settings handlers.

    /**
     * Register and ensure defaults for all plugin menu settings.
     */
    public static function register_menu_settings(): void {
        foreach ( self::get_menu_settings() as $option_name ) {
            self::ensure_default( (string) $option_name );
        }

    }

    /**
     * Reset configurable Free settings while preserving default image values.
     */
    public static function reset_settings(): void {
        foreach ( self::get_menu_settings() as $option_name ) {
            if ( in_array( $option_name, array( 'fifu_default_url', 'fifu_enable_default_url' ), true ) ) {
                continue;
            }

            delete_option( $option_name );
        }

        self::$reset_settings_calls[] = true;
    }

    /**
     * Utility reset used by CLI tests.
     */
    public static function reset(): void {
        self::$reset_settings_calls = [];
    }

    /**
     * Ensure the requested option has a default value before use.
     *
     * @param string $option_name Option key whose default should be enforced.
     */
    public static function ensure_default( string $option_name ): void {
        if ( self::is_obsolete_free_option( $option_name ) ) {
            return;
        }

        if ( self::is_free_pro_only_persistence_blocked_option( $option_name ) ) {
            return;
        }

        register_setting( 'settings-group', $option_name );

        $arrEmpty = array(
            'fifu_default_url',
                                    'fifu_square_mobile',
            'fifu_square_desktop',
            'fifu_skip',
            'fifu_html_cpt',
            'fifu_hide_format',
            'fifu_hide_type',
            'fifu_pcontent_types',
        );
        $arrDefaultType = array( 'fifu_default_cpt' );
        $arrOn = array( 'fifu_wc_zoom', 'fifu_wc_lbox' );
        $arrOnNo = array(
            'fifu_fake',
        );
        $arrOffNo = array( 'fifu_data_clean', 'fifu_run_delete_all', 'fifu_reset' );

        if ( get_option( $option_name ) === false ) {
            if ( in_array( $option_name, $arrEmpty, true ) ) {
                update_option( $option_name, '' );
            } elseif ( in_array( $option_name, $arrDefaultType, true ) ) {
                update_option( $option_name, 'post,page,product', 'no' );
            } elseif ( in_array( $option_name, $arrOn, true ) ) {
                update_option( $option_name, 'toggleon' );
            } elseif ( in_array( $option_name, $arrOnNo, true ) ) {
                update_option( $option_name, 'toggleon', 'no' );
            } elseif ( in_array( $option_name, $arrOffNo, true ) ) {
                update_option( $option_name, 'toggleoff', 'no' );
            } else {
                update_option( $option_name, 'toggleoff' );
            }
        }
    }

    /**
     * Update settings based on the submitted request payload.
     *
     * @return array<string,mixed> Structured data returned by the legacy update handler.
     */
    public static function update_from_request(): array {
        if ( self::is_valid_nonce( 'nonce_fifu_form_skip' ) ) {
            self::update_single_option( 'fifu_input_skip', 'fifu_skip' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_html_cpt' ) ) {
            self::update_single_option( 'fifu_input_html_cpt', 'fifu_html_cpt' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_square' ) ) {
            self::update_single_option( 'fifu_input_square_mobile', 'fifu_square_mobile' );
            self::update_single_option( 'fifu_input_square_desktop', 'fifu_square_desktop' );
        }
        if ( self::is_valid_nonce( 'nonce_fifu_form_debug' ) ) {
            self::update_single_option( 'fifu_input_debug', 'fifu_debug' );
        }


        if ( self::is_valid_nonce( 'nonce_fifu_form_photon' ) ) {
            self::update_single_option( 'fifu_input_photon', 'fifu_photon' );
        }
        if ( self::is_valid_nonce( 'nonce_fifu_form_cdn_content' ) ) {
            self::update_single_option( 'fifu_input_cdn_content', 'fifu_cdn_content' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_reset' ) ) {
            self::update_single_option( 'fifu_input_reset', 'fifu_reset' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_fake' ) ) {
            self::update_single_option( 'fifu_input_fake', 'fifu_fake' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_default_url' ) ) {
            self::update_single_option( 'fifu_input_default_url', 'fifu_default_url' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_default_cpt' ) ) {
            self::update_single_option( 'fifu_input_default_cpt', 'fifu_default_cpt' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_pcontent_types' ) ) {
            self::update_single_option( 'fifu_input_pcontent_types', 'fifu_pcontent_types' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_hide_format' ) ) {
            self::update_single_option( 'fifu_input_hide_format', 'fifu_hide_format' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_hide_type' ) ) {
            self::update_single_option( 'fifu_input_hide_type', 'fifu_hide_type' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_enable_default_url' ) ) {
            self::update_single_option( 'fifu_input_enable_default_url', 'fifu_enable_default_url' );
        }


        if ( self::is_valid_nonce( 'nonce_fifu_form_wc_lbox' ) ) {
            self::update_single_option( 'fifu_input_wc_lbox', 'fifu_wc_lbox' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_wc_zoom' ) ) {
            self::update_single_option( 'fifu_input_wc_zoom', 'fifu_wc_zoom' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_hide' ) ) {
            self::update_single_option( 'fifu_input_hide', 'fifu_hide' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_pcontent_add' ) ) {
            self::update_single_option( 'fifu_input_pcontent_add', 'fifu_pcontent_add' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_pcontent_remove' ) ) {
            self::update_single_option( 'fifu_input_pcontent_remove', 'fifu_pcontent_remove' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_get_first' ) ) {
            self::update_single_option( 'fifu_input_get_first', 'fifu_get_first' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_ovw_first' ) ) {
            self::update_single_option( 'fifu_input_ovw_first', 'fifu_ovw_first' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_run_delete_all' ) ) {
            self::update_single_option( 'fifu_input_run_delete_all', 'fifu_run_delete_all' );
        }

        if ( self::is_valid_nonce( 'nonce_fifu_form_data_clean' ) ) {
            self::update_single_option( 'fifu_input_data_clean', 'fifu_data_clean' );
        }

        if ( Fifu_Options_Utils::is_on( 'fifu_run_delete_all' ) ) {
            update_option( 'fifu_run_delete_all_time', current_time( 'mysql' ), 'no' );
        }

        $arr = array();

        if ( isset( $_POST['fifu_input_default_url'] ) ) {
            $arr['fifu_default_url'] = wp_strip_all_tags( $_POST['fifu_input_default_url'] );
        } else {
            $default_url = get_option( 'fifu_default_url' );
            $arr['fifu_default_url'] = $default_url ? $default_url : '';
        }

        if ( isset( $_POST['fifu_input_default_cpt'] ) ) {
            $arr['fifu_default_cpt'] = wp_strip_all_tags( $_POST['fifu_input_default_cpt'] );
        } else {
            $arr['fifu_default_cpt'] = null;
        }

        if ( isset( $_POST['fifu_input_pcontent_types'] ) ) {
            $arr['fifu_pcontent_types'] = wp_strip_all_tags( $_POST['fifu_input_pcontent_types'] );
        } else {
            $arr['fifu_pcontent_types'] = null;
        }

        if ( isset( $_POST['fifu_input_hide_format'] ) ) {
            $arr['fifu_hide_format'] = wp_strip_all_tags( $_POST['fifu_input_hide_format'] );
        } else {
            $arr['fifu_hide_format'] = null;
        }

        if ( isset( $_POST['fifu_input_hide_type'] ) ) {
            $arr['fifu_hide_type'] = wp_strip_all_tags( $_POST['fifu_input_hide_type'] );
        } else {
            $arr['fifu_hide_type'] = null;
        }

        return $arr;
    }

    /**
     * Validate and persist a single option from the request body.
     *
     * @param string $input_name  Input field name submitted via the settings form.
     * @param string $option_name Corresponding option key to update.
     */
    public static function update_single_option( string $input_name, string $option_name ): void {
        if ( self::is_obsolete_free_option( $option_name ) ) {
            return;
        }

        if ( self::is_free_pro_only_persistence_blocked_option( $option_name ) ) {
            return;
        }

        if ( ! isset( $_POST[ $input_name ] ) ) {
            return;
        }

        $value = $_POST[ $input_name ] ?? '';

        $arr_boolean = array(
            'fifu_cdn_content',
            'fifu_data_clean',
            'fifu_enable_default_url',
            'fifu_fake',
            'fifu_get_first',
            'fifu_hide',
            'fifu_pcontent_add',
            'fifu_pcontent_remove',
            'fifu_debug',
            'fifu_ovw_first',
            'fifu_photon',
            'fifu_reset',
            'fifu_run_delete_all',
            'fifu_wc_lbox',
            'fifu_wc_zoom',
            'fifu_cloud_upload_auto',
            'fifu_cloud_delete_auto',
            'fifu_cloud_hotlink',
        );
        if ( in_array( $option_name, $arr_boolean, true ) ) {
            if ( in_array( $value, array( 'on', 'off' ), true ) ) {
                update_option( $option_name, 'toggle' . $value );
            }

            return;
        }

        $arr_square_type = array( 'fifu_square_mobile', 'fifu_square_desktop' );
        if ( in_array( $option_name, $arr_square_type, true ) ) {
            if ( in_array( $value, array( '', 'crop', 'extend' ), true ) ) {
                update_option( $option_name, $value );
            }

            return;
        }

        $arr_url = array( 'fifu_default_url' );
        if ( in_array( $option_name, $arr_url, true ) ) {
            if ( ! is_scalar( $value ) ) {
                return;
            }

            $raw_value = (string) $value;

            if ( '' === $raw_value ) {
                update_option( $option_name, '' );
                return;
            }

            $trimmed_value = trim( $raw_value );

            if ( '' === $trimmed_value ) {
                return;
            }

            if ( filter_var( $trimmed_value, FILTER_VALIDATE_URL ) ) {
                update_option( $option_name, esc_url_raw( $trimmed_value ) );
            }

            return;
        }

        $arr_textarea = array();
        if ( in_array( $option_name, $arr_textarea, true ) ) {
            update_option( $option_name, sanitize_textarea_field( $value ) );

            return;
        }

        $arr_text = array(
            'fifu_default_cpt',
            'fifu_pcontent_types',
            'fifu_hide_format',
            'fifu_hide_type',
            'fifu_skip',
            'fifu_html_cpt',
        );
        if ( in_array( $option_name, $arr_text, true ) ) {
            update_option( $option_name, sanitize_text_field( $value ) );
        }
    }

    private static function obsolete_free_option_groups(): array {
        return array(
            self::OBSOLETE_FREE_ASIN_OPTIONS,
            self::OBSOLETE_FREE_ISBN_OPTIONS,
            self::OBSOLETE_FREE_HTML_MEDIA_OPTIONS,
            self::OBSOLETE_FREE_VIDEO_OPTIONS,
            self::OBSOLETE_FREE_POPUP_REDIRECTION_OPTIONS,
            self::OBSOLETE_FREE_OTFCDN_OPTIONS,
            self::OBSOLETE_FREE_BLOCK_UPLOAD_OPTIONS,
            self::OBSOLETE_FREE_REPLACE_BBPRESS_OPTIONS,
            self::OBSOLETE_FREE_SLIDER_OPTIONS,
        );
    }

    private static function obsolete_free_options(): array {
        $options = array();
        foreach ( self::obsolete_free_option_groups() as $group ) {
            $options = array_merge( $options, $group );
        }
        return array_values( array_unique( $options ) );
    }

    private static function is_obsolete_free_option( string $option_name ): bool {
        return in_array( $option_name, self::obsolete_free_options(), true );
    }

    private static function is_free_pro_only_persistence_blocked_option( string $option_name ): bool {
        return in_array( $option_name, self::FREE_PRO_ONLY_PERSISTENCE_BLOCKED_OPTIONS, true );
    }

    /**
     * Validate a nonce used for settings submissions.
     *
     * @param string $nonce_field Nonce field name from the request payload.
     * @param string $action      Action name used when the nonce was generated.
     *
     * @return bool True when the nonce is valid for the given action.
     */
    public static function is_valid_nonce( string $nonce_field, ?string $action = null ): bool {
        $default_action = 'fifu_settings_action';
        if ( defined( 'Fifu_Admin_Menu::ACTION_SETTINGS' ) ) {
            $default_action = Fifu_Admin_Menu::ACTION_SETTINGS;
        }

        if ( $action === null ) {
            $action = $default_action;
        }

        return isset( $_POST[ $nonce_field ] ) && wp_verify_nonce( $_POST[ $nonce_field ], $action );
    }
}
