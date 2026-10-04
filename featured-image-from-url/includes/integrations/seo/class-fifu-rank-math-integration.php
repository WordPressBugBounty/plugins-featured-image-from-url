<?php

defined('ABSPATH') || exit;

/**
 * Integrates FIFU logic with Rank Math filters.
 */
class Fifu_Rank_Math_Integration {

    /**
     * Original social image URLs indexed by network and queryless URL.
     *
     * @var array<string, array<string, string>>
     */
    private static $social_image_urls = [
        'facebook' => [],
        'twitter' => [],
    ];

    /**
     * Temporary Rank Math URLs mapped back to their exact FIFU originals.
     *
     * @var array<string, array<string, string>>
     */
    private static $social_image_compatibility_urls = [
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
     * Preserves FIFU's Facebook URL and skips Rank Math's destructive validation for it.
     */
    public static function filter_facebook_image($image_url) {
        self::capture_social_image_url('facebook', $image_url);
        return self::prepare_social_image_url('facebook', $image_url);
    }

    /**
     * Preserves FIFU's Twitter URL and skips Rank Math's destructive validation for it.
     */
    public static function filter_twitter_image($image_url) {
        self::capture_social_image_url('twitter', $image_url);
        return self::prepare_social_image_url('twitter', $image_url);
    }

    /**
     * Restores the original Facebook image URL when Rank Math removes its query.
     */
    public static function restore_facebook_image($image_url) {
        return self::restore_social_image_url('facebook', $image_url);
    }

    /**
     * Restores the original Twitter image URL when Rank Math removes its query.
     */
    public static function restore_twitter_image($image_url) {
        return self::restore_social_image_url('twitter', $image_url);
    }

    /**
     * Stores query-bearing URLs without changing their original representation.
     *
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
     * Makes a current FIFU featured URL look filter-provided to Rank Math 1.0.275.
     *
     * Rank Math strips the query and rejects extensionless URLs when its image
     * filter returns the unchanged candidate. A fragment marker changes only
     * Rank Math's internal string; fragments are not sent in HTTP requests, and
     * the final output filters restore the exact source URL before rendering.
     *
     * @param mixed $image_url
     * @return mixed
     */
    private static function prepare_social_image_url(string $network, $image_url) {
        if (!is_string($image_url) || !self::is_current_fifu_featured_image($image_url)) {
            return $image_url;
        }

        static $sequence = 0;
        $sequence++;
        $marker = 'fifu-rank-math=' . $network . '-' . $sequence . '-'
            . substr(hash('sha256', $network . "\0" . $image_url . "\0" . $sequence), 0, 16);
        $fragment_separator = strpos($image_url, '#') === false ? '#' : '&';
        $compatibility_url = $image_url . $fragment_separator . $marker;

        self::$social_image_compatibility_urls[$network][$compatibility_url] = $image_url;

        return $compatibility_url;
    }

    /**
     * Limits the validation bypass to the exact URL of the current FIFU featured attachment.
     */
    private static function is_current_fifu_featured_image(string $image_url): bool {
        if (!function_exists('get_queried_object_id') || !class_exists('Fifu_Attachment_Update_Service')) {
            return false;
        }

        $post_id = (int) get_queried_object_id();
        if ($post_id <= 0) {
            return false;
        }

        $attachment_id = (int) get_post_thumbnail_id($post_id);
        if ($attachment_id <= 0 || !Fifu_Attachment_Update_Service::is_fifu_owned($attachment_id)) {
            return false;
        }

        $featured_url = Fifu_Attachment_Update_Service::get_attachment_remote_url($attachment_id);
        if (preg_match('~^(?:https?:)?//~i', $featured_url) !== 1) {
            return false;
        }

        return $image_url === $featured_url
            || htmlspecialchars_decode($image_url, ENT_QUOTES) === $featured_url;
    }

    /**
     * Restores a previously captured URL only when its queryless form matches.
     *
     * @param mixed $image_url
     * @return mixed
     */
    private static function restore_social_image_url(string $network, $image_url) {
        if (!is_string($image_url)) {
            return $image_url;
        }

        if (isset(self::$social_image_compatibility_urls[$network][$image_url])) {
            return self::$social_image_compatibility_urls[$network][$image_url];
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
