<?php

declare(strict_types=1);

/**
 * Legacy metadata repository that mirrors previous admin/db functions.
 */
class Fifu_Legacy_Meta_Repository {

    private wpdb $wpdb;
    private string $postmeta_table;
    private string $fifu_map_table;
    private string $fifu_key_table;
    private string $fifu_url_table;
    private string $fifu_alt_table;
    private string $fifu_alt_map_table;

    public function __construct(wpdb $wpdb, string $postmeta_table) {
        $this->wpdb = $wpdb;
        $this->postmeta_table = $postmeta_table;
        $prefix = $wpdb->prefix;
        $this->fifu_map_table = $prefix . 'fifu_map';
        $this->fifu_key_table = $prefix . 'fifu_key';
        $this->fifu_url_table = $prefix . 'fifu_url';
        $this->fifu_alt_table = $prefix . 'fifu_alt';
        $this->fifu_alt_map_table = $prefix . 'fifu_alt_map';
    }

    public function get_fifu_fields_for_posts(array $postIds): array {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        if ($idsCsv === '0') {
            return [];
        }

        /*
         * Start with legacy direct fields. This must stay legacy-only because
         * process_post_meta_in_row() also uses get_legacy_fifu_fields_for_posts()
         * to decide whether legacy -> DB2 migration is needed.
         */
        $data = $this->get_legacy_fifu_fields_for_posts($postIds);

        /*
         * Fill remaining empty fields from DB2 using bulk queries scoped to this batch.
         */
        $db2Data = $this->get_db2_fifu_fields_for_posts($postIds);
        foreach ($db2Data as $postId => $fields) {
            if (!isset($data[$postId])) {
                $data[$postId] = [
                    'fifu_image_url' => '',
                    'fifu_image_alt' => '',
                ];
            }

            foreach ($fields as $key => $value) {
                if (!array_key_exists($key, $data[$postId])) {
                    continue;
                }

                if (
                    trim((string) $data[$postId][$key]) === ''
                    && trim((string) $value) !== ''
                ) {
                    $data[$postId][$key] = (string) $value;
                }
            }
        }

        return $data;
    }

    /**
     * Retrieve only legacy FIFU metadata for the provided posts.
     */
    public function get_legacy_fifu_fields_for_posts(array $postIds): array {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        if ($idsCsv === '0') {
            return [];
        }

        $results = $this->wpdb->get_results("
            SELECT post_id, meta_key, meta_value
            FROM {$this->postmeta_table}
            WHERE post_id IN ({$idsCsv})
            AND meta_key IN ('fifu_image_url', 'fifu_image_alt')
        ");

        $data = [];
        foreach (array_map('intval', explode(',', $idsCsv)) as $id) {
            $data[$id] = [
                'fifu_image_url' => '',
                'fifu_image_alt' => '',
            ];
        }

        if (!is_array($results)) {
            return $data;
        }

        foreach ($results as $row) {
            $postId = (int) $row->post_id;
            if (isset($data[$postId])) {
                $data[$postId][$row->meta_key] = $row->meta_value;
            }
        }

        return $data;
    }

    /**
     * Load main FIFU fields from DB2 in bulk for the current Image Metadata batch.
     *
     * @param array<int|string> $postIds
     * @return array<int,array<string,string>>
     */
    private function get_db2_fifu_fields_for_posts(array $postIds): array {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        if ($idsCsv === '0') {
            return [];
        }

        if (
            !$this->table_exists($this->fifu_map_table)
            || !$this->table_exists($this->fifu_key_table)
            || !$this->table_exists($this->fifu_url_table)
        ) {
            return [];
        }

        $data = [];

        $urlRows = $this->wpdb->get_results("
            SELECT
                m.post_id,
                k.key_type,
                u.url
            FROM {$this->fifu_map_table} m
            INNER JOIN {$this->fifu_key_table} k ON k.key_id = m.key_id
            INNER JOIN {$this->fifu_url_table} u ON u.hash = m.hash
            WHERE m.post_id IN ({$idsCsv})
              AND m.key_index = 0
              AND k.key_type IN ('image')
              AND u.url IS NOT NULL
              AND u.url <> ''
            ORDER BY
                m.post_id,
                CASE k.key_type
                    WHEN 'image' THEN 0
                    ELSE 1
                END
        ");

        if (is_array($urlRows)) {
            foreach ($urlRows as $row) {
                $postId = (int) $row->post_id;
                $url = trim((string) $row->url);
                if ($postId <= 0 || $url === '') {
                    continue;
                }

                if (!isset($data[$postId])) {
                    $data[$postId] = [];
                }

                switch ((string) $row->key_type) {
                    case 'image':
                        $data[$postId]['fifu_image_url'] = $url;
                        break;
                }
            }
        }

        if (
            !$this->table_exists($this->fifu_alt_map_table)
            || !$this->table_exists($this->fifu_alt_table)
        ) {
            return $data;
        }

        $altRows = $this->wpdb->get_results("
            SELECT
                m.post_id,
                a.alt
            FROM {$this->fifu_alt_map_table} m
            INNER JOIN {$this->fifu_key_table} k ON k.key_id = m.key_id
            INNER JOIN {$this->fifu_alt_table} a ON a.hash = m.hash
            WHERE m.post_id IN ({$idsCsv})
              AND m.key_index = 0
              AND k.key_type = 'image'
              AND a.alt IS NOT NULL
              AND a.alt <> ''
            ORDER BY m.post_id
        ");

        if (is_array($altRows)) {
            foreach ($altRows as $row) {
                $postId = (int) $row->post_id;
                $alt = trim((string) $row->alt);
                if ($postId <= 0 || $alt === '') {
                    continue;
                }

                if (!isset($data[$postId])) {
                    $data[$postId] = [];
                }

                $data[$postId]['fifu_image_alt'] = $alt;
            }
        }

        return $data;
    }

    /**
     * Checks whether a table exists in the database.
     *
     * @param string $table
     * @return bool
     */
    private function table_exists(string $table): bool {
        $sql = $this->wpdb->prepare("SHOW TABLES LIKE %s", $table);
        return $this->wpdb->get_var($sql) !== null;
    }

    /**
     * Return a comma-separated list of thumbnail attachment IDs for the given posts.
     */
    public function get_thumbnail_ids_csv(array $postIds): string {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        if ($idsCsv === '0') {
            return '';
        }

        $results = $this->wpdb->get_results("
            SELECT meta_value
            FROM {$this->postmeta_table}
            WHERE post_id IN ({$idsCsv})
            AND meta_key = '_thumbnail_id'
        ", ARRAY_A);

        return implode(',', array_column($results, 'meta_value'));
    }
}
