<?php

if (!defined('ABSPATH')) {
    exit;
}

class Fifu_Admin_Meta_Box_Renderer {

    private static function get_attachment_alt(int $attachment_id): string {
        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        return is_string($alt) ? $alt : '';
    }

    public static function render_featured_image_box( $post ): void {
        if (!$post instanceof WP_Post) {
            return;
        }

        $margin = 'margin-top:5px;margin-left:3px;';
        $width = 'width:100%;';
        $height = 'height:150px;';
        $align = 'text-align:left;';

        $url = esc_url(Fifu_Post_Image_Url_Read_Service::get_image_url((int) $post->ID) ?? '');
        $alt = esc_attr(Fifu_Post_Image_Alt_Read_Service::get_image_alt((int) $post->ID));
        $display_url = $url;
        $display_input_url = $url;
        $display_alt = $alt;
        $is_native_preview_only = false;
        $native_preview_url = '';
        $native_preview_alt = '';

        if ($url) {
            $show_button = 'display:none;';
            $show_alt = $show_image = $show_link = '';
        } else {
            $show_alt = $show_link = 'display:none;';
            $show_image = 'display:none;';
            $show_button = '';
        }

        $is_debug_enabled = Fifu_Options_Utils::is_on('fifu_debug');
        $fifu = Fifu_Meta_Box_Strings::get_featured_image_box_strings();
        include FIFU_ADMIN_DIR . '/html/meta-box.html';
    }

}
