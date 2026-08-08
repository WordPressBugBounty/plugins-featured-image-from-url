<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Provides post image URLs for integrations that consume the featured image.
 */
class Fifu_Post_Image_Urls
{
    /**
     * Return the effective featured-image URL for a post.
     *
     * @return array<int, string>
     */
    public static function get_all(int $post_id): array
    {
        $mainImageUrl =
            Fifu_Post_Main_Image_Resolver::get_main_image_url(
                $post_id,
                true
            );

        if (!$mainImageUrl) {
            return [];
        }

        return [(string) $mainImageUrl];
    }
}
