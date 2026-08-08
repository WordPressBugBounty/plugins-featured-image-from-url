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
    }

    /**
     * Filters duplicate post meta keys, removing FIFU-specific data.
     *
     * @param string[] $meta_keys
     *
     * @return string[]
     */
    public static function filter_meta_keys(array $meta_keys): array
    {
        $remove_thumbnail = false;
        $thumbnail_index = null;

        foreach ($meta_keys as $index => $value) {
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
