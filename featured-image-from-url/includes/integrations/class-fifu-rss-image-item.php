<?php

defined('ABSPATH') || exit;

/**
 * Outputs FIFU media:content tags inside RSS feeds.
 */
class Fifu_Rss_Image_Item {

    /**
     * Echoes the <media:content> tag for the current post's featured media.
     */
    public static function render_media_content(): void {
        global $post;
        if (!isset($post) || !isset($post->ID)) {
            return;
        }

        $post_id = (int) $post->ID;
        $thumbnail = Fifu_Post_Main_Image_Resolver::get_main_image_url($post_id, true);
        if ($thumbnail) {
            if (Fifu_Speedup_Url_Service::is_speedup_url($thumbnail)) {
                $thumbnail = Fifu_Speedup_Url_Service::get_signed_url($thumbnail, 1280, 853, null, null, false);
            }
        } elseif (has_post_thumbnail($post_id)) {
            $thumbnail = wp_get_attachment_url(get_post_thumbnail_id($post_id));
        }

        if (!$thumbnail) {
            return;
        }

        $clean_url = esc_url($thumbnail);
        echo '<media:content url="' . $clean_url . '" medium="image"></media:content>' . "\n";
    }
}
