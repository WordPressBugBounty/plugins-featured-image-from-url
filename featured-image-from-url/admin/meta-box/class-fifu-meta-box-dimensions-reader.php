<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Meta_Box_Dimensions_Reader
{
    /**
     * Reads the main image width stored in the submitted request.
     */
    public static function get_main_image_width(array $request): ?int
    {
        if (isset($request['fifu_input_url'], $request['fifu_input_image_width']) && $request['fifu_input_url']) {
            return self::sanitize_numeric_dimension($request['fifu_input_image_width']);
        }

        return null;
    }

    /**
     * Reads the main image height stored in the submitted request.
     */
    public static function get_main_image_height(array $request): ?int
    {
        if (isset($request['fifu_input_url'], $request['fifu_input_image_height']) && $request['fifu_input_url']) {
            return self::sanitize_numeric_dimension($request['fifu_input_image_height']);
        }

        return null;
    }

    /**
     * Sanitizes a numeric dimension value, returning null when it is empty.
     */
    private static function sanitize_numeric_dimension($value): ?int
    {
        $value = wp_strip_all_tags($value);
        if ($value === '') {
            return null;
        }
        return (int) $value;
    }

}
