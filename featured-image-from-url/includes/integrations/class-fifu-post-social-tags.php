<?php

defined('ABSPATH') || exit;

/**
 * Renders FIFU social image meta tags for posts.
 */
class Fifu_Post_Social_Tags {

    /**
     * Outputs og/twitter image tags for the current post.
     */
    public static function render_post_image_tags(): void {
        if (
            is_front_page()
            || is_home()
            || is_tax()
            || !is_singular()
        ) {
            return;
        }

        $post_id = (int) get_queried_object_id();

        if ($post_id <= 0) {
            return;
        }
        $title = str_replace("'", "&#39;", strip_tags(get_the_title($post_id)));
        $description = str_replace("'", "&#39;", wp_strip_all_tags(get_post_field('post_excerpt', $post_id)));

        $arr = self::fifu_get_post_image_urls_db2_first((int) $post_id);

        if (empty($arr)) {
            return;
        }

        foreach ($arr as $url) {
            if (!$url) {
                continue;
            }
            if (Fifu_Speedup_Url_Service::is_speedup_url($url)) {
                $url = Fifu_Speedup_Url_Service::get_signed_url($url, 1280, 672, null, null, false);
            } elseif (Fifu_Options_Utils::is_on('fifu_photon')) {
                $url = Fifu_Jetpack_Cdn_Service::build_photon_url($url, null, get_post_thumbnail_id($post_id));
            }
            include FIFU_INCLUDES_DIR . '/html/og-image.html';
        }

        foreach ($arr as $url) {
            if (!$url) {
                continue;
            }
            if (Fifu_Speedup_Url_Service::is_speedup_url($url)) {
                $url = Fifu_Speedup_Url_Service::get_signed_url($url, 1280, 672, null, null, false);
            } elseif (Fifu_Options_Utils::is_on('fifu_photon')) {
                $url = Fifu_Jetpack_Cdn_Service::build_photon_url($url, null, get_post_thumbnail_id($post_id));
            }
            include FIFU_INCLUDES_DIR . '/html/twitter-image.html';
        }
    }

    /**
     * Collects the single supported featured image URL.
     *
     * @param int $post_id
     * @return string[]
     */
    private static function fifu_get_post_image_urls_db2_first(int $post_id): array
    {
        $url = Fifu_Post_Image_Url_Read_Service::get_image_url($post_id);

        return $url === null ? [] : [$url];
    }
}
