<?php

defined('ABSPATH') || exit;

/**
 * Integrates FIFU logic with Rank Math filters.
 */
class Fifu_Rank_Math_Integration {

    /**
     * Prevents Rank Math from stripping query params on Facebook OpenGraph images.
     */
    public static function filter_facebook_image(string $image_url): string {
        if (Fifu_Options_Utils::is_on('fifu_photon') && Fifu_Image_Url_Utils::is_remote_image_url($image_url)) {
            return str_replace('https://', 'http://', $image_url);
        }
        return $image_url;
    }

    /**
     * Prevents Rank Math from stripping query params on Twitter card images.
     */
    public static function filter_twitter_image(string $image_url): string {
        if (Fifu_Options_Utils::is_on('fifu_photon') && Fifu_Image_Url_Utils::is_remote_image_url($image_url)) {
            return str_replace('https://', 'http://', $image_url);
        }
        return $image_url;
    }

    /**
     * Keeps Rank Math sitemap caching enabled in the free build.
     */
    public static function filter_sitemap_caching(bool $enabled): bool {
        return true;
    }

    /**
     * Leaves Rank Math sitemap image URLs unchanged in the free build.
     */
    public static function filter_sitemap_xml_img_src(string $src, $post): string {
        return $src;
    }
}
