<?php
declare(strict_types=1);

final class Fifu_Image_Size_Service {

    /**
     * Load all detected and user-defined image sizes.
     */
    public static function load_sizes(): array {
        $result = [];
        $options = new Fifu_Options_Query_Utils();
        $detected_sizes = $options->select_by_prefix('fifu_detected_size_');
        foreach ($detected_sizes as $option) {
            $size_name = str_replace('fifu_detected_size_', '', $option->option_name);
            $unserialized_value = maybe_unserialize($option->option_value);
            $defined = get_option("fifu_defined_size_{$size_name}");
            if ($defined) {
                $unserialized_value['w'] = $defined['w'];
                $unserialized_value['h'] = $defined['h'];
                $unserialized_value['c'] = $defined['c'];
            }
            $result[$size_name] = $unserialized_value;
        }
        return $result;
    }

    /**
     * Reset all detected and user-defined image sizes.
     */
    public static function reset_sizes(): void {
        $options = new Fifu_Options_Query_Utils();
        $options->delete_by_prefix('fifu_detected_size_');
        $options->delete_by_prefix('fifu_defined_size_');
    }

    /**
     * Persist the given list of image sizes.
     *
     * @param array $sizes
     */
    public static function save_sizes(array $sizes): void {
        foreach ($sizes as $key => $value) {
            if (!is_array($value) || !$value) {
                continue;
            }

            $transformed = array(
                'w' => $value['width'] ?? 0,
                'h' => $value['height'] ?? 0,
                'c' => $value['crop'] ?? false,
            );
            update_option("fifu_defined_size_{$key}", $transformed);
        }
    }
}
