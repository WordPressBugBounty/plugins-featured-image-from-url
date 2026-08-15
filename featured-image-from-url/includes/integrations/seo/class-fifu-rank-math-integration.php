<?php

defined('ABSPATH') || exit;

/**
 * Integrates FIFU logic with Rank Math filters.
 */
class Fifu_Rank_Math_Integration {

    /**
     * Preserves the Facebook OpenGraph image URL provided by Rank Math.
     *
     * @param mixed $image_url
     * @return mixed
     */
    public static function filter_facebook_image($image_url) {
        return $image_url;
    }

    /**
     * Preserves the Twitter card image URL provided by Rank Math.
     *
     * @param mixed $image_url
     * @return mixed
     */
    public static function filter_twitter_image($image_url) {
        return $image_url;
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
