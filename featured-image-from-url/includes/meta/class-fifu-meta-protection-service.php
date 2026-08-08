<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles meta protection filters previously defined in admin/meta-box.php.
 */
final class Fifu_Meta_Protection_Service
{
    /**
     * Registers meta protection filters migrated from legacy hooks.
     */
    public static function register_hooks(): void
    {
        add_filter(
            'is_protected_meta',
            [self::class, 'filter_is_protected_meta'],
            10,
            3
        );
    }

    /**
     * Determines whether the provided meta key should remain hidden.
     */
    public static function filter_is_protected_meta(bool $protected, $meta_key, string $meta_type): bool
    {
        if (!is_string($meta_key)) {
            return $protected;
        }

        if ($meta_type === 'post' && strpos($meta_key, 'fifu_') === 0) {
            return true;
        }

        return $protected;
    }

    /**
     * Utility migrated from fifu_remove_from_arr_by_str().
     */
    public static function remove_by_substring(array $array, string $substring): array
    {
        $new_array = [];
        foreach ($array as $element) {
            if (strpos($element, $substring) === false) {
                $new_array[] = $element;
            }
        }
        return $new_array;
    }
}
