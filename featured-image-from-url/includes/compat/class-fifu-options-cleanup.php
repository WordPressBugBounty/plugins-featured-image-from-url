<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Fifu_Options_Cleanup {

    /**
     * Responsável por apagar/limpar options legadas.
     *
     * @param wpdb|null $wpdb
     * @return void
     */
    public static function delete_deprecated_options( ?wpdb $wpdb = null ): void {
        if ( null === $wpdb ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            global $wpdb;
        }

        $options_table = $wpdb->prefix . 'options';
        $in_list = "'fifu_cpt0','fifu_cpt1','fifu_cpt2','fifu_cpt3','fifu_cpt4','fifu_cpt5','fifu_cpt6','fifu_cpt7','fifu_cpt8','fifu_cpt9','fifu_data_generation','fifu_debug_mode','fifu_fake2','fifu_priority','fifu_update_all_id','fifu_update_all_status','fifu_update_all_timestamp','fifu_update_number','fifu_wc_theme','fifu_max_url','fifu_variation_attach_id_0','fifu_variation_attach_id_1','fifu_variation_attach_id_2','fifu_variation_attach_id_3','fifu_variation_attach_id_4','fifu_variation_attach_id_5','fifu_variation_attach_id_6','fifu_variation_attach_id_7','fifu_variation_attach_id_8','fifu_variation_attach_id_9','fifu_default_width','fifu_video_margin_bottom','fifu_video_vertical_margin','fifu_video_width_rtio','fifu_video_height_arch','fifu_video_height_ctgr','fifu_video_height_home','fifu_video_height_page','fifu_video_height_post','fifu_video_height_prod','fifu_video_height_shop','fifu_video_width_arch','fifu_video_width_ctgr','fifu_video_width_home','fifu_video_width_page','fifu_video_width_post','fifu_video_width_prod','fifu_video_width_shop','fifu_variation_gallery','fifu_video_height','fifu_video_crop','fifu_shortcode_max_height','fifu_image_height_shop','fifu_image_width_shop','fifu_image_height_prod','fifu_image_width_prod','fifu_image_height_cart','fifu_image_width_cart','fifu_image_height_ctgr','fifu_image_width_ctgr','fifu_image_height_arch','fifu_image_width_arch','fifu_image_height_home','fifu_image_width_home','fifu_image_height_page','fifu_image_width_page','fifu_image_height_post','fifu_image_width_post','fifu_parameters','fifu_slider_fade','fifu_flickr_post','fifu_flickr_page','fifu_flickr_arch','fifu_flickr_cart','fifu_flickr_ctgr','fifu_flickr_home','fifu_flickr_prod','fifu_flickr_shop','fifu_original','fifu_save_dimensions','fifu_save_dimensions_redirect','fifu_save_dimensions_all','fifu_clean_dimensions_all','fifu_css','fifu_jquery','fifu_class','fifu_shortcode_min_width','fifu_media_library','fifu_auto_set_blocked','fifu_isbn_blocked','fifu_shortpixel','fifu_video_black','fifu_flickr','fifu_giphy','fifu_video_related','fifu_spinner_slider','fifu_spinner_video','fifu_spinner_image','fifu_valid','fifu_column_height','fifu_spinner_db','fifu_spinner_cron_metadata','fifu_confirm_delete_all','fifu_confirm_delete_all_time','fifu_gallery_selector','fifu_video_gallery_icon','fifu_hover','fifu_hover_selector','fifu_shortcode','fifu_grid_category','fifu_rss_width','fifu_bbpress_avatar','fifu_bbpress_copy','fifu_screenshot_high','fifu_screenshot_height','fifu_screenshot_scale','fifu_sizes','fifu_cdn_crop','fifu_cdn_social','fifu_mouse_vimeo','fifu_mouse_youtube','fifu_api_key_youtube','fifu_api_key_googledrive','fifu_bearer_token_twitter','fifu_ck','fifu_chk','fifu_install','fifu_auto_alt','fifu_variation','fifu_social_image_only','fifu_pop_first','fifu_content','fifu_content_page','fifu_content_cpt','fifu_query_strings','fifu_decode','fifu_spinner_nth','fifu_check','fifu_video_priority','fifu_update_ignore','fifu_video_list_priority','fifu_dynamic_alt','fifu_rss','fifu_social_home_url','fifu_social','fifu_lazy','fifu_hide_page','fifu_hide_post','fifu_hide_cpt','fifu_fake_created','fifu_su_always_connected','fifu_cloak','fifu_same_size','fifu_crop_delay','fifu_crop_ignore_parent','fifu_crop_default','fifu_fit','fifu_crop_ratio','fifu_crop0','fifu_crop1','fifu_crop2','fifu_crop3','fifu_crop4','fifu_video_play_draw','fifu_proxies','fifu_auto_set_license','fifu_square','fifu_shortform'";
        $in_list .= sprintf(",'%s','%s'", 'fifu_' . 'tags', 'fifu_' . 'tags_orientation');

        $names_in = $wpdb->get_col( "SELECT option_name FROM {$options_table} WHERE option_name IN ({$in_list})" );
        $names_cors = $wpdb->get_col( "SELECT option_name FROM {$options_table} WHERE option_name LIKE 'fifu_cors_proxy_%'" );
        $names_session = $wpdb->get_col( "SELECT option_name FROM {$options_table} WHERE option_name LIKE 'fifu_session_%'" );

        $wpdb->query( "DELETE FROM {$options_table} WHERE option_name IN ({$in_list})" );
        $wpdb->query( "DELETE FROM {$options_table} WHERE option_name LIKE 'fifu_cors_proxy_%'" );
        $wpdb->query( "DELETE FROM {$options_table} WHERE option_name LIKE 'fifu_session_%'" );

        $all = array_unique( array_merge( (array) $names_in, (array) $names_cors, (array) $names_session ) );
        foreach ( $all as $opt ) {
            if ( $opt !== null && $opt !== '' ) {
                wp_cache_delete( $opt, 'options' );
            }
        }
    }
}
