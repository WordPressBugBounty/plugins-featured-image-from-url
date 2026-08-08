<?php
declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Meta statistics helpers for FIFU.
 */
class Fifu_Meta_Stats_Utils {

    private static ?wpdb $wpdb = null;

    /**
     * Initializes the internal wpdb instance when needed.
     */
    private static function get_wpdb(): wpdb {
        if (self::$wpdb === null) {
            global $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            self::$wpdb = $wpdb;
        }
        return self::$wpdb;
    }

    /**
     * Returns the latest meta entries for a given meta key.
     *
     * Mirrors get_last().
     *
     * @param string $meta_key
     * @param int    $limit
     * @return array<object>
     */
    public static function get_last_meta_entries(string $meta_key, int $limit = 3): array {
        $wpdb = self::get_wpdb();
        $posts_table = $wpdb->posts;
        $postmeta_table = $wpdb->postmeta;
        $sql = $wpdb->prepare(
            "SELECT p.id, pm.meta_value
            FROM {$posts_table} p
            INNER JOIN {$postmeta_table} pm ON p.id = pm.post_id
            WHERE pm.meta_key = %s
            ORDER BY p.post_date DESC
            LIMIT %d",
            $meta_key,
            $limit
        );
        return $wpdb->get_results($sql);
    }

    /**
     * Returns the most recent 'fifu_image_url' value.
     *
     * Mirrors get_last_image().
     */
    public static function get_last_image_url(): ?string {
        $rows = self::get_last_image();
        if (empty($rows)) {
            return null;
        }

        $first = $rows[0];
        $metaValue = null;
        if (is_object($first) && property_exists($first, 'meta_value')) {
            $metaValue = $first->meta_value;
        } elseif (is_array($first) && array_key_exists('meta_value', $first)) {
            $metaValue = $first['meta_value'];
        }

        if ($metaValue === null) {
            return null;
        }

        return (string) $metaValue;
    }

    /**
     * Returns the latest legacy or DB2 entry for 'fifu_image_url'.
     *
     * @return array<object>
     */
    public static function get_last_image(): array {
        if (!function_exists('fifu_db2_mode')) {
            return self::get_last_image_legacy();
        }

        $mode = fifu_db2_mode();

        if ($mode === Fifu_Db2_Mode::MODE_DB2) {
            $db2 = self::get_last_image_db2();
            if ($db2 !== null) {
                return $db2;
            }
            return self::get_last_image_legacy();
        }

        if ($mode === Fifu_Db2_Mode::MODE_HYBRID) {
            $db2 = self::get_last_image_db2();
            if ($db2 !== null && !empty($db2)) {
                return $db2;
            }
            return self::get_last_image_legacy();
        }

        return self::get_last_image_legacy();
    }

    /**
     * Legacy SQL path that returns the latest postmeta entry.
     *
     * @return array<object>
     */
    private static function get_last_image_legacy(): array {
        $wpdb = self::get_wpdb();
        $postmeta_table = $wpdb->postmeta;
        $results = $wpdb->get_results("
        SELECT pm.meta_value
        FROM {$postmeta_table} pm 
        WHERE pm.meta_key = 'fifu_image_url'
        ORDER BY pm.meta_id DESC
        LIMIT 1
    ");

        if ($results === false) {
            return [];
        }

        return $results;
    }

    /**
     * DB2 SQL path that returns the latest featured image URL.
     *
     * @return array<object>|null
     */
    private static function get_last_image_db2(): ?array {
        $wpdb = self::get_wpdb();
        $map_table = $wpdb->prefix . 'fifu_map';
        $key_table = $wpdb->prefix . 'fifu_key';
        $url_table = $wpdb->prefix . 'fifu_url';

        if (!self::has_last_image_db2_tables($map_table, $key_table, $url_table)) {
            return null;
        }

        $sql = "
            SELECT u.url AS meta_value
            FROM {$map_table} m
            INNER JOIN {$key_table} k ON k.key_id = m.key_id
            INNER JOIN {$url_table} u ON u.hash = m.hash
            WHERE k.key_type = 'image'
              AND m.key_index = 0
            ORDER BY u.created_at DESC
            LIMIT 1
        ";

        $results = $wpdb->get_results($sql);
        if ($results === false) {
            return null;
        }

        return $results;
    }

    /**
     * Confirms all required DB2 tables exist for the last image query.
     *
     * @param string $map_table
     * @param string $key_table
     * @param string $url_table
     * @return bool
     */
    private static function has_last_image_db2_tables(string $map_table, string $key_table, string $url_table): bool {
        return self::table_exists($map_table)
            && self::table_exists($key_table)
            && self::table_exists($url_table);
    }

    /**
     * Checks whether a table exists in the database.
     *
     * @param string $table
     * @return bool
     */
    private static function table_exists(string $table): bool {
        $wpdb = self::get_wpdb();
        $sql = $wpdb->prepare("SHOW TABLES LIKE %s", $table);
        $result = $wpdb->get_var($sql);
        return $result !== null && $result !== false;
    }
}
