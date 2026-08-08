<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides read access to post image URL metadata.
 */
final class Fifu_Post_Image_Url_Read_Service
{
    /**
     * Returns the direct FIFU image URL for a post.
     *
     * @param int $postId
     * @return string|null
     */
    public static function get_image_url(int $postId): ?string
    {
        $db2Url = self::get_db2_image_url($postId);
        if ($db2Url !== null) {
            return $db2Url;
        }

        return self::normalize_url(get_post_meta($postId, 'fifu_image_url', true));
    }

    private static function get_db2_image_url(int $postId): ?string
    {
        if (!function_exists('fifu_db2_manager')) {
            return null;
        }

        $manager = fifu_db2_manager();
        if (!$manager instanceof Fifu_Db2_Manager) {
            return null;
        }

        $mapping = $manager->getPostMapping($postId, 'image', 0);
        if (!is_array($mapping)) {
            return null;
        }

        return self::normalize_url($mapping['url'] ?? null);
    }

    private static function normalize_url($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $url = trim((string) $value);
        return $url !== '' ? $url : null;
    }
}
