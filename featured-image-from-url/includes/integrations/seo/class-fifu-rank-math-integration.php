<?php

defined('ABSPATH') || exit;

/**
 * Integrates FIFU logic with Rank Math filters.
 */
class Fifu_Rank_Math_Integration {

    /**
     * Original query-bearing social image URLs, indexed by network and queryless URL.
     *
     * @var array<string, array<string, string>>
     */
    private static $social_image_urls = [
        'facebook' => [],
        'twitter' => [],
    ];

    /**
     * Expose FIFU's logical featured-image ALT to single attachment ALT reads.
     *
     * @param mixed  $value
     * @param int    $object_id
     * @param string $meta_key
     * @param bool   $single
     * @param string $meta_type
     * @return mixed
     */
    public static function filter_attachment_image_alt($value, $object_id, $meta_key, $single, $meta_type = '') {
        if ($meta_key !== '_wp_attachment_image_alt' || $value !== null || !$single) {
            return $value;
        }

        $attachment_id = (int) $object_id;
        if ($attachment_id <= 0 || !function_exists('fifu_is_fifu_attachment') || !fifu_is_fifu_attachment($attachment_id)) {
            return $value;
        }

        $attachment = get_post($attachment_id);
        if (!$attachment instanceof WP_Post || $attachment->post_type !== 'attachment') {
            return $value;
        }

        $post_id = (int) $attachment->post_parent;
        if ($post_id <= 0 || (int) get_post_thumbnail_id($post_id) !== $attachment_id) {
            return $value;
        }

        $alt = Fifu_Post_Image_Alt_Read_Service::get_image_alt($post_id);
        return $alt === null ? $value : $alt;
    }

    /**
     * Preserves the Facebook OpenGraph image URL provided by Rank Math.
     *
     * @param mixed $image_url
     * @return mixed
     */
    public static function filter_facebook_image($image_url) {
        self::capture_social_image_url('facebook', $image_url);
        return $image_url;
    }

    /**
     * Preserves the Twitter card image URL provided by Rank Math.
     *
     * @param mixed $image_url
     * @return mixed
     */
    public static function filter_twitter_image($image_url) {
        self::capture_social_image_url('twitter', $image_url);
        return $image_url;
    }

    /**
     * Restore a captured Facebook image URL after Rank Math removes its query.
     *
     * @param mixed $image_url
     * @return mixed
     */
    public static function restore_facebook_image($image_url) {
        return self::restore_social_image_url('facebook', $image_url);
    }

    /**
     * Restore a captured Twitter image URL after Rank Math removes its query.
     *
     * @param mixed $image_url
     * @return mixed
     */
    public static function restore_twitter_image($image_url) {
        return self::restore_social_image_url('twitter', $image_url);
    }

    /**
     * Capture the first query-bearing URL for each network and queryless URL.
     *
     * @param string $network
     * @param mixed $image_url
     */
    private static function capture_social_image_url(string $network, $image_url): void {
        if (!is_string($image_url)) {
            return;
        }

        $query_position = strpos($image_url, '?');
        if ($query_position === false || $query_position === strlen($image_url) - 1) {
            return;
        }

        $queryless_url = substr($image_url, 0, $query_position);
        if (!isset(self::$social_image_urls[$network][$queryless_url])) {
            self::$social_image_urls[$network][$queryless_url] = $image_url;
        }
    }

    /**
     * Restore an exact captured URL when Rank Math supplies its queryless form.
     *
     * @param string $network
     * @param mixed $image_url
     * @return mixed
     */
    private static function restore_social_image_url(string $network, $image_url) {
        if (!is_string($image_url)) {
            return $image_url;
        }

        return self::$social_image_urls[$network][$image_url] ?? $image_url;
    }

    /**
     * Keeps Rank Math sitemap caching enabled in the free build.
     */
    public static function filter_sitemap_caching($enabled): bool {
        return true;
    }

    /**
     * Leaves Rank Math sitemap image URLs unchanged in the free build.
     *
     * @param mixed $src
     * @param mixed $post
     * @return mixed
     */
    public static function filter_sitemap_xml_img_src($src, $post) {
        return $src;
    }
}
