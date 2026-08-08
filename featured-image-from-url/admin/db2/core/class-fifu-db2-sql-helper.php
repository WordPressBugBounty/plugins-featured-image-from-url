<?php
declare(strict_types=1);

if (class_exists('Fifu_Db2_Sql_Helper', false)) {
    return;
}

/**
 * Provides reusable SQL helpers for the db2 layer.
 */
class Fifu_Db2_Sql_Helper {
    /**
     * Returns the post type list formatted for an SQL IN clause.
     *
     * Relies on `Fifu_Post_Type_Utils::get_post_types()` and WordPress' `get_post_types()` fallback.
     *
     * @return string
     */
    public static function get_types_for_in_clause(): string {
        $raw = (array) Fifu_Post_Type_Utils::get_post_types();
        $registered = get_post_types([], 'names');
        $safe = [];

        foreach ($raw as $pt) {
            $pt = sanitize_key($pt);
            if ($pt !== '' && isset($registered[$pt])) {
                $safe[] = $pt;
            }
        }

        $safe = array_values(array_unique($safe));
        return implode("','", $safe);
    }

    /**
     * Sanitizes a CSV of IDs and optionally allows zeros.
     *
     * @param string|int|array<mixed> $ids
     * @param bool $allow_zero
     * @return string
     */
    public static function sanitize_ids_csv($ids, bool $allow_zero = false): string {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        } elseif (is_int($ids)) {
            $ids = [$ids];
        } elseif (!is_array($ids)) {
            $ids = [];
        }

        $set = [];
        foreach ($ids as $id) {
            if (is_int($id)) {
                $n = $id;
            } elseif (is_string($id)) {
                $id = trim($id);
                if ($id === '' || !ctype_digit($id)) {
                    continue;
                }
                $n = (int) $id;
            } else {
                continue;
            }

            if ($n > 0 || ($allow_zero && $n === 0)) {
                $set[$n] = true;
            }
        }

        if (!$set) {
            return '0';
        }

        return implode(',', array_keys($set));
    }

    /**
     * Sanitizes a list of post types, falling back to a provided default.
     *
     * The `$fallbackTypes` argument replaces the legacy `$this->types` fallback.
     *
     * @param string|array<mixed> $post_types
     * @param string|null $fallbackTypes
     * @return string
     */
    public static function sanitize_post_types_list($post_types, ?string $fallbackTypes = null): string {
        if (is_string($post_types)) {
            $post_types = explode(',', str_replace(['"', "'"], '', $post_types));
        } elseif (!is_array($post_types)) {
            $post_types = [];
        }

        $registered = array_flip(get_post_types([], 'names'));
        $set = [];
        foreach ($post_types as $pt) {
            $pt = sanitize_key(trim((string) $pt));
            if ($pt === '' || !isset($registered[$pt])) {
                continue;
            }
            $set[$pt] = true;
        }

        if (!$set) {
            if ($fallbackTypes !== null && $fallbackTypes !== '') {
                return $fallbackTypes;
            }
            return "''";
        }

        $items = array_keys($set);
        return "'" . implode("','", $items) . "'";
    }

    /**
     * Builds an SQL IN clause components array from a CSV stored in an option.
     *
     * @param string $base_key
     * @param string $option_name
     * @param bool $include_base_key Whether to prepend the base key even if it is empty.
     * @return array
     */
    public static function build_in_from_option_csv(string $base_key, string $option_name, bool $include_base_key = true): array {
        $field = (string) get_option($option_name);

        $keys = [];
        if ($include_base_key) {
            $keys[] = $base_key;
        }

        if ($field !== '') {
            foreach (explode(',', $field) as $k) {
                $k = trim($k);
                if ($k !== '') {
                    $keys[] = $k;
                }
            }
        }
        $keys = array_values(array_unique($keys));

        $in = implode(',', array_fill(0, count($keys), '%s'));
        return [$in, $keys];
    }
}
