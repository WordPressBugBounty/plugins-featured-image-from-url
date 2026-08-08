<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/** Locates posts eligible for the configured default featured image. */
class Fifu_Meta_Gap_Repository
{
    private wpdb $wpdb;
    private string $postmeta_table;
    private string $posts_table;
    private string $fifu_map_table;
    private string $fifu_key_table;
    private string $fifu_sent_table;
    private string $fifu_sent_event_table;
    private string $post_types_in_clause;

    public function __construct(?wpdb $wpdb_instance = null)
    {
        $this->wpdb = $wpdb_instance ?? $GLOBALS['wpdb'];
        $this->postmeta_table = $this->wpdb->postmeta;
        $this->posts_table = $this->wpdb->posts;
        $prefix = $this->wpdb->prefix;
        $this->fifu_map_table = $prefix . 'fifu_map';
        $this->fifu_key_table = $prefix . 'fifu_key';
        $this->fifu_sent_table = $prefix . 'fifu_sent';
        $this->fifu_sent_event_table = $prefix . 'fifu_sent_event';
        $this->post_types_in_clause = $this->build_post_types_clause();
    }

    /** @return array<int, object> */
    public function get_posts_without_featured_image(string $post_types_csv): array
    {
        $default_attach_id = (int) get_option('fifu_default_attach_id');
        $safe = $this->sanitize_post_types_list($post_types_csv);
        if (!function_exists('fifu_db2_mode')) {
            return $this->get_posts_without_featured_image_legacy($safe, $default_attach_id);
        }
        $mode = fifu_db2_mode();
        if ($mode === Fifu_Db2_Mode::MODE_DB2 || $mode === Fifu_Db2_Mode::MODE_HYBRID) {
            $db2 = $this->get_posts_without_featured_image_db2($safe, $default_attach_id);
            if ($db2 !== null) {
                return $db2;
            }
        }
        return $this->get_posts_without_featured_image_legacy($safe, $default_attach_id);
    }

    private function get_posts_without_featured_image_legacy(string $post_types_csv, int $default_attach_id): array
    {
        $sql = "SELECT p.ID AS post_id, p.post_title FROM {$this->posts_table} p WHERE p.post_type IN ({$post_types_csv}) AND p.post_status IN ('publish', 'draft') AND NOT EXISTS (SELECT 1 FROM {$this->postmeta_table} pm WHERE pm.post_id = p.ID AND ((pm.meta_key = '_thumbnail_id' AND TRIM(COALESCE(pm.meta_value, '')) <> '' AND TRIM(COALESCE(pm.meta_value, '')) <> '0' AND TRIM(COALESCE(pm.meta_value, '')) <> '{$default_attach_id}') OR (pm.meta_key = 'fifu_image_url' AND TRIM(COALESCE(pm.meta_value, '')) <> ''))) ORDER BY p.ID DESC";
        $results = $this->wpdb->get_results($sql);
        return is_array($results) ? $results : [];
    }

    private function get_posts_without_featured_image_db2(string $post_types_csv, int $default_attach_id): ?array
    {
        if (!$this->has_featured_image_db2_tables() || !$this->table_exists($this->fifu_sent_table) || !$this->table_exists($this->fifu_sent_event_table)) {
            return null;
        }
        $sql = "SELECT p.ID AS post_id, p.post_title FROM {$this->posts_table} p WHERE p.post_type IN ({$post_types_csv}) AND p.post_status IN ('publish', 'draft') AND NOT EXISTS (SELECT 1 FROM {$this->postmeta_table} pm WHERE pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id' AND TRIM(COALESCE(pm.meta_value, '')) <> '' AND TRIM(COALESCE(pm.meta_value, '')) <> '0' AND TRIM(COALESCE(pm.meta_value, '')) <> '{$default_attach_id}') AND NOT EXISTS (SELECT 1 FROM {$this->postmeta_table} legacy_pm WHERE legacy_pm.post_id = p.ID AND legacy_pm.meta_key = 'fifu_image_url' AND TRIM(COALESCE(legacy_pm.meta_value, '')) <> '') AND NOT EXISTS (SELECT 1 FROM {$this->fifu_map_table} m INNER JOIN {$this->fifu_key_table} k ON k.key_id = m.key_id WHERE m.post_id = p.ID AND k.key_type = 'image' AND m.key_index = 0) ORDER BY p.ID DESC";
        $results = $this->wpdb->get_results($sql);
        return $results === false ? null : $results;
    }

    private function build_post_types_clause(): string
    {
        $raw = (array) Fifu_Post_Type_Utils::get_post_types();
        $registered = get_post_types([], 'names');
        $safe = [];
        foreach ($raw as $post_type) {
            $post_type = sanitize_key($post_type);
            if ($post_type !== '' && isset($registered[$post_type])) {
                $safe[] = $post_type;
            }
        }
        $safe = array_values(array_unique($safe));
        return $safe === [] ? "''" : "'" . implode("','", $safe) . "'";
    }

    private function has_featured_image_db2_tables(): bool
    {
        return $this->table_exists($this->fifu_map_table) && $this->table_exists($this->fifu_key_table);
    }

    private function table_exists(string $table): bool
    {
        $result = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $result !== null && $result !== false;
    }

    private function sanitize_post_types_list(string $post_types): string
    {
        return Fifu_Db2_Sql_Helper::sanitize_post_types_list($post_types, $this->post_types_in_clause);
    }
}
