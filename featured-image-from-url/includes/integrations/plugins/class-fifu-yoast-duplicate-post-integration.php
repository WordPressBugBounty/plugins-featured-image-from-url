<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bridges integrations with Yoast Duplicate Post plugin.
 */
final class Fifu_Yoast_Duplicate_Post_Integration
{
    /**
     * Registers hooks for Yoast Duplicate Post metadata preservation.
     */
    public static function register_hooks(): void
    {
        add_filter(
            'duplicate_post_meta_keys_filter',
            [self::class, 'filter_meta_keys']
        );

        add_action(
            'duplicate_post_after_duplicated',
            [self::class, 'after_duplicated'],
            20,
            2
        );
    }

    /**
     * Normalizes duplicated FIFU featured images into independent DB2 state.
     */
    public static function after_duplicated(
        $duplicate_post_id,
        $source_post
    ): void {
        $duplicate_post_id = is_numeric($duplicate_post_id)
            ? (int) $duplicate_post_id
            : 0;

        if (
            $duplicate_post_id <= 0
            || !($source_post instanceof \WP_Post)
        ) {
            return;
        }

        $duplicate_post = get_post($duplicate_post_id);

        if (!$duplicate_post instanceof \WP_Post) {
            return;
        }

        $source_post_id = (int) $source_post->ID;

        if ($source_post_id <= 0) {
            return;
        }

        $url = Fifu_Post_Image_Url_Read_Service::get_image_url(
            $source_post_id
        );

        if ($url === null || trim($url) === '') {
            return;
        }

        $alt = Fifu_Post_Image_Alt_Read_Service::get_image_alt(
            $source_post_id
        );

        // Yoast may have copied the source FIFU attachment reference.
        // Detach only the duplicate's metadata; the source attachment remains intact.
        delete_post_meta(
            $duplicate_post_id,
            '_thumbnail_id'
        );

        if (
            !Fifu_Developer_Media_Service::set_image(
                $duplicate_post_id,
                $url,
                true
            )
        ) {
            return;
        }

        if (
            $alt !== null
            && trim($alt) !== ''
            && function_exists('fifu_db2_manager')
        ) {
            $manager = fifu_db2_manager();

            if (
                $manager instanceof Fifu_Db2_Manager
                && method_exists($manager, 'savePostAlt')
            ) {
                $manager->savePostAlt(
                    $duplicate_post_id,
                    'image',
                    0,
                    $alt
                );
            }
        }

        delete_post_meta(
            $duplicate_post_id,
            'fifu_image_url'
        );

        delete_post_meta(
            $duplicate_post_id,
            'fifu_image_alt'
        );
    }

    /**
     * Filters duplicate post meta keys, removing FIFU-specific data.
     *
     * @param mixed $meta_keys
     */
    public static function filter_meta_keys($meta_keys)
    {
        if (!is_array($meta_keys)) {
            return $meta_keys;
        }

        $remove_thumbnail = false;
        $thumbnail_index = null;

        foreach ($meta_keys as $index => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (Fifu_Generic_Utils::starts_with($value, 'fifu')) {
                $remove_thumbnail = true;
            } elseif ($value === '_thumbnail_id') {
                $thumbnail_index = $index;
            }
        }

        if ($remove_thumbnail && $thumbnail_index !== null) {
            unset($meta_keys[$thumbnail_index]);
        }

        return $meta_keys;
    }
}
