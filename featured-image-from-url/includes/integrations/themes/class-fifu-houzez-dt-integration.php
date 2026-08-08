<?php

declare(strict_types=1);

/**
 * Integration layer for the Houzez theme DataTransport hooks.
 */
class Fifu_Houzez_Dt_Integration {

    /**
     * Register the DataTransport hooks only when the theme and option are active.
     */
    public static function register_hooks(): void {
        if (!class_exists('Fifu_Theme_Detector')) {
            return;
        }

        if (!Fifu_Theme_Detector::is_houzez_active()) {
            return;
        }

        if (!Fifu_Options_Utils::is_on('fifu_houzez_api')) {
            return;
        }

        add_action('dt_push_post_media', [self::class, 'push_post_media'], 10, 4);
        add_action('dt_prepared_meta', [self::class, 'prepared_meta'], 10, 2);
    }

    /**
     * Hook that adjusts metadata prepared by DataTransport.
     */
    public static function prepared_meta(array $prepared_meta, int $postId): array {
        if (isset($prepared_meta['_thumbnail_id']) || isset($prepared_meta['fave_property_images'])) {
            $prepared_meta['fifu_houzez_urls'] = [];
        }

        if (isset($prepared_meta['_thumbnail_id'])) {
            $thumbnailId = $prepared_meta['_thumbnail_id'][0] ?? null;
            if ($thumbnailId) {
                $thumbnailUrl = wp_get_attachment_url($thumbnailId);
                if ($thumbnailUrl) {
                    $prepared_meta['fifu_houzez_urls'][] = $thumbnailUrl;
                }
            }
        }

        if (isset($prepared_meta['fave_property_images'])) {
            foreach ($prepared_meta['fave_property_images'] as $imageId) {
                $imageUrl = wp_get_attachment_url($imageId);
                if ($imageUrl) {
                    $prepared_meta['fifu_houzez_urls'][] = $imageUrl;
                }
            }
        }

        return $prepared_meta;
    }

    /**
     * Hook that intercepts media pushing during cloning or imports.
     */
    public static function push_post_media($new_post_id, $post_media, $post_id, $args): bool {
        return false;
    }
}
