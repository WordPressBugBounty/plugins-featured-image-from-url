<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides read access to term image URL metadata.
 */
final class Fifu_Term_Image_Url_Read_Service
{
    /**
     * Returns the image URL for a term, using db2 first and falling back to termmeta.
     *
     * @param int $termId
     * @return string|null
     */
    public static function get_image_url(int $termId): ?string
    {
        if ($termId <= 0) {
            return null;
        }

        if (function_exists('fifu_db2_manager')) {
            $manager = fifu_db2_manager();
            if ($manager instanceof Fifu_Db2_Manager) {
                $mapping = $manager->getTermMapping($termId, 'image');
                if (is_array($mapping) && !empty($mapping['url'])) {
                    return (string) $mapping['url'];
                }
            }
        }

        $legacyUrl = get_term_meta($termId, 'fifu_image_url', true);
        if ($legacyUrl === '' || $legacyUrl === null) {
            return null;
        }

        return (string) $legacyUrl;
    }

}
