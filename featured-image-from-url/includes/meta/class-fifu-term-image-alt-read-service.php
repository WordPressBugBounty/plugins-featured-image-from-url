<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides read access to term image ALT metadata.
 */
final class Fifu_Term_Image_Alt_Read_Service
{
    /**
     * Returns the image ALT text for a term, using db2 first and falling back to termmeta.
     *
     * @param int $termId
     * @return string|null
     */
    public static function get_image_alt(int $termId): ?string
    {
        if ($termId <= 0) {
            return null;
        }

        if (function_exists('fifu_db2_manager')) {
            $manager = fifu_db2_manager();
            if ($manager instanceof Fifu_Db2_Manager) {
                $mapping = $manager->getTermAltMapping($termId, 'image');
                if (is_array($mapping)) {
                    $alt = $mapping['alt'] ?? $mapping['value'] ?? null;
                    if ($alt !== null && $alt !== '') {
                        return (string) $alt;
                    }
                }
            }
        }

        $legacyAlt = get_term_meta($termId, 'fifu_image_alt', true);
        if ($legacyAlt === '' || $legacyAlt === null) {
            return null;
        }

        return (string) $legacyAlt;
    }
}
