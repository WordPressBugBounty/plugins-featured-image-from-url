<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides read access to post image ALT metadata.
 */
final class Fifu_Post_Image_Alt_Read_Service
{
    /**
     * Returns the featured image ALT text for a post, using db2 first and falling back to postmeta.
     *
     * @param int $postId
     * @return string|null
     */
    public static function get_image_alt(int $postId): ?string
    {
        if (function_exists('fifu_db2_manager')) {
            $manager = fifu_db2_manager();
            if ($manager instanceof Fifu_Db2_Manager) {
                $mapping = $manager->getPostAltMapping($postId, 'image', 0);
                if (is_array($mapping)) {
                    $alt = $mapping['alt'] ?? $mapping['value'] ?? null;
                    if ($alt !== null && trim((string) $alt) !== '') {
                        return (string) $alt;
                    }
                }
            }
        }

        $legacyAlt = get_post_meta($postId, 'fifu_image_alt', true);
        if ($legacyAlt === '' || $legacyAlt === null || trim((string) $legacyAlt) === '') {
            return null;
        }

        return (string) $legacyAlt;
    }
}
