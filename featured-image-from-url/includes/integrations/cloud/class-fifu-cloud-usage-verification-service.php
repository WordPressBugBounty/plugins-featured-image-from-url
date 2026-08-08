<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Service for verifying CDN usage by tracking hex IDs stored in meta values.
 */
class Fifu_Cloud_Usage_Verification_Service
{

    /**
     * Find all meta values that contain any of the given hex ids in FIFU CDN URLs.
     *
     * @param string[] $hexIds
     * @return string[] List of matching meta values.
     */
    public static function find_used_hex_ids(array $hexIds): array
    {
        $hexIds = array_values(
            array_filter(
                array_map('strval', $hexIds),
                static function ($value) {
                    return $value !== '';
                }
            )
        );

        if (empty($hexIds)) {
            return [];
        }

        global $wpdb;

        $allUrls = self::usage_verification_su_fetch_urls_legacy($wpdb);

        $db2Urls = self::usage_verification_su_fetch_urls_db2($wpdb);
        if (is_array($db2Urls) && $db2Urls !== []) {
            $allUrls = array_merge($allUrls, $db2Urls);
            $allUrls = array_values(array_unique($allUrls));
        }

        $filtered = [];
        foreach ($allUrls as $metaValue) {
            if (!is_string($metaValue)) {
                continue;
            }

            $dashSplit = explode('-', $metaValue);
            $firstPart = $dashSplit[0] ?? '';
            $slashSplit = explode('/', $firstPart);
            $hexId = end($slashSplit);

            if ($hexId !== false && in_array($hexId, $hexIds, true)) {
                $filtered[] = $metaValue;
            }
        }

        return $filtered;
    }

    /**
     * Legacy URL fetch from postmeta/termmeta.
     *
     * @param wpdb $wpdb
     * @return string[]
     */
    private static function usage_verification_su_fetch_urls_legacy(wpdb $wpdb): array
    {
        $like = $wpdb->esc_like('https://cdn.fifu.app/') . '%';

        $postmetaResults = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT meta_value
                FROM {$wpdb->postmeta}
                WHERE meta_key LIKE 'fifu_%'
                AND meta_value LIKE %s",
                $like
            )
        );

        $termmetaResults = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT meta_value
                FROM {$wpdb->termmeta}
                WHERE meta_key LIKE 'fifu_%'
                AND meta_value LIKE %s",
                $like
            )
        );

        $postmetaResults = is_array($postmetaResults) ? $postmetaResults : [];
        $termmetaResults = is_array($termmetaResults) ? $termmetaResults : [];

        return array_merge($postmetaResults, $termmetaResults);
    }

    /**
     * Fetch URLs from DB2 tables when available.
     *
     * @param wpdb $wpdb
     * @return string[]|null
     */
    private static function usage_verification_su_fetch_urls_db2(wpdb $wpdb): ?array
    {
        if (!function_exists('fifu_db2_mode')) {
            return null;
        }

        $mode = fifu_db2_mode();
        if ($mode !== Fifu_Db2_Mode::MODE_DB2 && $mode !== Fifu_Db2_Mode::MODE_HYBRID) {
            return null;
        }

        if (!self::has_speedup_db2_tables($wpdb)) {
            return null;
        }

        $prefix = $wpdb->prefix;
        $mapTable = $prefix . 'fifu_map';
        $termMapTable = $prefix . 'fifu_term_map';
        $keyTable = $prefix . 'fifu_key';
        $urlTable = $prefix . 'fifu_url';
        $posts = $prefix . 'posts';
        $terms = $prefix . 'terms';

        $sql = "
            SELECT u.url
            FROM {$mapTable} m
            INNER JOIN {$keyTable} k ON k.key_id = m.key_id
            INNER JOIN {$urlTable} u ON u.hash = m.hash
            INNER JOIN {$posts} p ON p.ID = m.post_id
            WHERE u.url LIKE 'https://cdn.fifu.app/%'
              AND k.key_type = 'image'
        ";

        if (class_exists('WooCommerce') && self::has_term_speedup_db2_tables($wpdb)) {
            $sql .= "
                UNION
                SELECT u.url
                FROM {$termMapTable} tm
                INNER JOIN {$keyTable} k ON k.key_id = tm.key_id
                INNER JOIN {$urlTable} u ON u.hash = tm.hash
                INNER JOIN {$terms} t ON t.term_id = tm.term_id
                WHERE u.url LIKE 'https://cdn.fifu.app/%'
                  AND k.key_type IN ('image')
            ";
        }

        $sql = "SELECT DISTINCT url FROM ({$sql}) AS x";

        $results = $wpdb->get_col($sql);
        if ($results === false || $results === null) {
            return null;
        }

        return $results;
    }

    /**
     * Checks the core DB2 tables for posts.
     *
     * @param wpdb $wpdb
     * @return bool
     */
    private static function has_speedup_db2_tables(wpdb $wpdb): bool
    {
        $prefix = $wpdb->prefix;
        $tables = [
            $prefix . 'fifu_map',
            $prefix . 'fifu_key',
            $prefix . 'fifu_url',
        ];

        foreach ($tables as $table) {
            $sql = $wpdb->prepare("SHOW TABLES LIKE %s", $table);
            if ($wpdb->get_var($sql) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks the DB2 tables used for term media.
     *
     * @param wpdb $wpdb
     * @return bool
     */
    private static function has_term_speedup_db2_tables(wpdb $wpdb): bool
    {
        $prefix = $wpdb->prefix;
        $table = $prefix . 'fifu_term_map';
        $sql = $wpdb->prepare("SHOW TABLES LIKE %s", $table);
        return $wpdb->get_var($sql) !== null;
    }
}
