<?php
declare(strict_types=1);

/**
 * Encapsulates heavy db and thumbnail queries for the speed-up UI.
 */
class Fifu_Db2_Speed_Up_Repository {
    private const DB2_MANAGED_LEGACY_KEYS = array(
        'fifu_image_url',
    );

    private wpdb $wpdb;
    private string $posts;
    private string $postmeta;
    private string $terms;
    private string $termmeta;
    private string $termTaxonomy;
    private string $termRelationships;
    private string $fifu_term_map_table;
    private string $fifu_map_table;
    private string $fifu_key_table;
    private string $fifu_url_table;
    private string $typesForInClause;

    /**
     * Sets up the repository with the WordPress database connection.
     *
     * @param wpdb $wpdb
     */
    public function __construct( wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $prefix = $wpdb->prefix;
        $this->posts = $prefix . 'posts';
        $this->postmeta = $prefix . 'postmeta';
        $this->terms = $prefix . 'terms';
        $this->termmeta = $prefix . 'termmeta';
        $this->termTaxonomy = $prefix . 'term_taxonomy';
        $this->termRelationships = $prefix . 'term_relationships';
        $this->fifu_term_map_table = $prefix . 'fifu_term_map';
        $this->fifu_map_table = $prefix . 'fifu_map';
        $this->fifu_key_table = $prefix . 'fifu_key';
        $this->fifu_url_table = $prefix . 'fifu_url';
        $this->typesForInClause = Fifu_Db2_Sql_Helper::get_types_for_in_clause();
    }

    /**
     * Provides direct access to the WordPress database object for transaction handling.
     *
     * @return wpdb
     */
    public function get_wpdb(): wpdb {
        return $this->wpdb;
    }

    /**
     * Builds the storage URL for an SU bucket/storage pair.
     *
     * @param string|int $bucket_id
     * @param string $storage_id
     * @return string
     */
    private function build_su_url(string|int $bucket_id, string $storage_id): string {
        return 'https://cdn.fifu.app/' . $bucket_id . '/' . $storage_id;
    }

    private function resolve_internal_attachment_url(int $attachmentId): string
    {
        if ($attachmentId <= 0) {
            return '';
        }

        $attachedFile = trim((string) get_post_meta($attachmentId, '_wp_attached_file', true));
        if ($attachedFile !== '' && preg_match('#^https?://#i', $attachedFile)) {
            return $attachedFile;
        }

        $url = function_exists('wp_get_attachment_url') ? wp_get_attachment_url($attachmentId) : false;
        return is_string($url) ? trim($url) : '';
    }

    private function hydrate_internal_attachment_rows(array $rows): array {
        $hydrated = [];

        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }

            $attachmentId = (int) ($row->thumbnail_id ?? $row->att_id ?? 0);
            if ($attachmentId <= 0) {
                continue;
            }

            $realUrl = $this->resolve_internal_attachment_url($attachmentId);
            if ($realUrl === '') {
                continue;
            }

            $row->url = $realUrl;
            $hydrated[] = $row;
        }

        return $hydrated;
    }

    public function get_posts_with_internal_featured_image(
        int $page,
        ?string $type,
        ?string $keyword
    ): array {
        $page = max(0, $page);
        $type = $type ?? '';
        $keyword = $keyword ?? '';
        $start = $page * 1000;

        $postFilter = '';

        if ($keyword !== '') {
            if ($type === 'title') {
                $like = '%' . $this->wpdb->esc_like($keyword) . '%';
                $postFilter = $this->wpdb->prepare(
                    'AND p.post_title LIKE %s',
                    $like
                );
            } elseif ($type === 'postid') {
                $postFilter = $this->wpdb->prepare(
                    'AND pm.post_id = %d',
                    (int) $keyword
                );
            }
        }

        $authorClause = $this->wpdb->prepare(
            'AND att.post_author <> %d',
            $this->get_author()
        );

        $sql = "
            (
                SELECT
                    pm.post_id,
                    att.guid AS url,
                    p.post_name,
                    p.post_title,
                    p.post_date,
                    att.ID AS thumbnail_id,
                    (
                        SELECT pm2.meta_value
                        FROM {$this->postmeta} pm2
                        WHERE pm2.post_id = pm.post_id
                          AND pm2.meta_key = '_product_image_gallery'
                        LIMIT 1
                    ) AS gallery_ids,
                    FALSE AS category
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p
                    ON pm.post_id = p.ID
                INNER JOIN {$this->posts} att
                    ON pm.meta_key = '_thumbnail_id'
                   AND pm.meta_value = att.ID
                   {$authorClause}
                WHERE p.post_title <> ''
                  AND p.post_type <> 'attachment'
                  AND p.post_status <> 'trash'
                  {$postFilter}
            )
        ";

        if (class_exists('WooCommerce')) {
            $termFilter = '';

            if ($keyword !== '') {
                if ($type === 'title') {
                    $like = '%' . $this->wpdb->esc_like($keyword) . '%';
                    $termFilter = $this->wpdb->prepare(
                        'AND t.name LIKE %s',
                        $like
                    );
                } elseif ($type === 'postid') {
                    $termFilter = $this->wpdb->prepare(
                        'AND tm.term_id = %d',
                        (int) $keyword
                    );
                }
            }

            $sql .= "
                UNION
                (
                    SELECT
                        tm.term_id AS post_id,
                        att.guid AS url,
                        NULL AS post_name,
                        t.name AS post_title,
                        NULL AS post_date,
                        att.ID AS thumbnail_id,
                        NULL AS gallery_ids,
                        TRUE AS category
                    FROM {$this->termmeta} tm
                    INNER JOIN {$this->terms} t
                        ON tm.term_id = t.term_id
                    INNER JOIN {$this->posts} att
                        ON tm.meta_key = 'thumbnail_id'
                       AND tm.meta_value = att.ID
                       {$authorClause}
                    WHERE 1 = 1
                      {$termFilter}
                )
            ";
        }

        $sql .= $this->wpdb->prepare(
            "
            ORDER BY post_id DESC
            LIMIT %d,1000
            ",
            $start
        );

        $rows = $this->wpdb->get_results($sql);

        if (!is_array($rows) || $rows === []) {
            return [];
        }

        return $this->hydrate_internal_attachment_rows($rows);
    }

    /**
     * Lists all URLs for the admin speed-up table.
     *
     * @param int $page
     * @param string|null $type
     * @param string|null $keyword
     * @return array
     */
    public function get_all_urls(int $page, ?string $type, ?string $keyword): array {
        $page = max(0, $page);

        if (!function_exists('fifu_db2_mode')) {
            return $this->get_all_urls_legacy($page, $type, $keyword);
        }

        $mode = fifu_db2_mode();

        if ($mode === Fifu_Db2_Mode::MODE_DB2) {
            $db2Rows = $this->get_all_urls_db2($page, $type, $keyword);
            if ($db2Rows !== null) {
                return $db2Rows;
            }

            return $this->get_all_urls_legacy($page, $type, $keyword);
        }

        if ($mode === Fifu_Db2_Mode::MODE_LEGACY || $mode === Fifu_Db2_Mode::MODE_HYBRID) {
            return $this->get_all_urls_hybrid($page, $type, $keyword);
        }

        return $this->get_all_urls_legacy($page, $type, $keyword);
    }

    private function get_all_urls_legacy(int $page, ?string $type, ?string $keyword): array {
        $page = max(0, $page);
        $start = $page * 1000;

        $filter_posts = '';
        if ($keyword) {
            $like = '%' . $this->wpdb->esc_like($keyword) . '%';
            if ($type === 'title') {
                $filter_posts = $this->wpdb->prepare('AND p.post_title LIKE %s', $like);
            } elseif ($type === 'url') {
                $filter_posts = $this->wpdb->prepare('AND pm.meta_value LIKE %s', $like);
            }
        }

        $sql = "
            (
                SELECT pm.meta_id, pm.post_id, pm.meta_value AS url, pm.meta_key, p.post_name, p.post_title, p.post_date, false AS category
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id {$filter_posts}
                WHERE pm.meta_key = 'fifu_image_url'
                AND pm.meta_value NOT LIKE '%https://cdn.fifu.app/%'
                AND pm.meta_value NOT LIKE 'http://localhost/%'
                AND p.post_status <> 'trash'
            )
        ";

        if (class_exists('WooCommerce')) {
            $filter_terms = '';

            if ($keyword) {
                $like = '%' . $this->wpdb->esc_like($keyword) . '%';

                if ($type === 'title') {
                    $filter_terms = $this->wpdb->prepare(
                        'AND t.name LIKE %s',
                        $like
                    );
                } elseif ($type === 'url') {
                    $filter_terms = $this->wpdb->prepare(
                        'AND tm.meta_value LIKE %s',
                        $like
                    );
                }
            }

            $sql .= "
                UNION
                (
                    SELECT
                        tm.meta_id,
                        tm.term_id AS post_id,
                        tm.meta_value AS url,
                        tm.meta_key,
                        NULL AS post_name,
                        t.name AS post_title,
                        NULL AS post_date,
                        TRUE AS category
                    FROM {$this->termmeta} tm
                    INNER JOIN {$this->terms} t
                        ON tm.term_id = t.term_id {$filter_terms}
                    WHERE tm.meta_key = 'fifu_image_url'
                      AND tm.meta_value NOT LIKE '%https://cdn.fifu.app/%'
                      AND tm.meta_value NOT LIKE 'http://localhost/%'
                )
            ";
        }

        $sql .= "
            ORDER BY post_id DESC
            LIMIT {$start},1000
        ";

        $rows = $this->wpdb->get_results($sql);
        return is_array($rows) ? $rows : [];
    }

    private function get_all_urls_db2(int $page, ?string $type, ?string $keyword): ?array {
        if (!$this->has_speedup_db2_tables()) {
            return null;
        }

        $page = max(0, $page);
        $start = $page * 1000;
        $includeTerms = class_exists('WooCommerce')
            && $this->has_term_speedup_db2_tables();
        $filter_posts = '';
        if ($keyword) {
            $like = '%' . $this->wpdb->esc_like($keyword) . '%';
            if ($type === 'title') {
                $filter_posts = $this->wpdb->prepare('AND p.post_title LIKE %s', $like);
            } elseif ($type === 'url') {
                $filter_posts = $this->wpdb->prepare('AND u.url LIKE %s', $like);
            }
        }

        $sql = "
            (
                SELECT
                    m.id AS meta_id,
                    m.post_id,
                    u.url AS url,
                    'fifu_image_url' AS meta_key,
                    p.post_name,
                    p.post_title,
                    p.post_date,
                    FALSE AS category
                FROM {$this->fifu_map_table} m
                INNER JOIN {$this->fifu_key_table} k ON k.key_id = m.key_id
                INNER JOIN {$this->fifu_url_table} u ON u.hash = m.hash
                INNER JOIN {$this->posts} p ON p.ID = m.post_id {$filter_posts}
                WHERE k.key_type = 'image'
                  AND m.key_index = 0
                  AND u.url NOT LIKE '%https://cdn.fifu.app/%'
                  AND u.url NOT LIKE 'http://localhost/%'
                  AND p.post_status <> 'trash'
            )
        ";

        if ($includeTerms) {
            $filter_terms = '';

            if ($keyword) {
                $like = '%' . $this->wpdb->esc_like($keyword) . '%';

                if ($type === 'title') {
                    $filter_terms = $this->wpdb->prepare(
                        'AND t.name LIKE %s',
                        $like
                    );
                } elseif ($type === 'url') {
                    $filter_terms = $this->wpdb->prepare(
                        'AND u.url LIKE %s',
                        $like
                    );
                }
            }

            $sql .= "
                UNION
                (
                    SELECT
                        tm.id AS meta_id,
                        tm.term_id AS post_id,
                        u.url AS url,
                        'fifu_image_url' AS meta_key,
                        NULL AS post_name,
                        t.name AS post_title,
                        NULL AS post_date,
                        TRUE AS category
                    FROM {$this->fifu_term_map_table} tm
                    INNER JOIN {$this->fifu_key_table} k
                        ON k.key_id = tm.key_id
                    INNER JOIN {$this->fifu_url_table} u
                        ON u.hash = tm.hash
                    INNER JOIN {$this->terms} t
                        ON t.term_id = tm.term_id {$filter_terms}
                    WHERE k.key_type = 'image'
                      AND u.url NOT LIKE '%https://cdn.fifu.app/%'
                      AND u.url NOT LIKE 'http://localhost/%'
                )
            ";
        }

        $sql .= $this->wpdb->prepare(
            "
            ORDER BY post_id DESC
            LIMIT %d,1000
            ",
            $start
        );

        $rows = $this->wpdb->get_results($sql);
        if ($rows === false) {
            return null;
        }

        return $rows;
    }

    private function get_all_urls_hybrid(int $page, ?string $type, ?string $keyword): array {
        $page = max(0, $page);
        $offset = $page * 1000;
        $limit = 1000;
        $needed = $offset + $limit;
        $sourcePage = 0;
        $mergedMap = [];
        $legacyDone = false;
        $db2Done = false;

        while (count($mergedMap) < $needed && (!$legacyDone || !$db2Done)) {
            if (!$db2Done) {
                $db2Batch = $this->get_all_urls_db2($sourcePage, $type, $keyword);
                if ($db2Batch === null) {
                    $db2Done = true;
                } else {
                    $this->merge_upload_rows($mergedMap, $db2Batch, true);
                    if (count($db2Batch) < $limit) {
                        $db2Done = true;
                    }
                }
            }

            if (!$legacyDone) {
                $legacyBatch = $this->get_all_urls_legacy($sourcePage, $type, $keyword);
                $this->merge_upload_rows($mergedMap, $legacyBatch, false);
                if (count($legacyBatch) < $limit) {
                    $legacyDone = true;
                }
            }

            $sourcePage++;
        }

        $rows = array_values($mergedMap);
        usort($rows, static function ($left, $right): int {
            $postCompare = (int) ($right->post_id ?? 0) <=> (int) ($left->post_id ?? 0);
            if ($postCompare !== 0) {
                return $postCompare;
            }

            $categoryCompare = (int) ($left->category ?? 0) <=> (int) ($right->category ?? 0);
            if ($categoryCompare !== 0) {
                return $categoryCompare;
            }

            $metaKeyCompare = strcmp((string) ($left->meta_key ?? ''), (string) ($right->meta_key ?? ''));
            if ($metaKeyCompare !== 0) {
                return $metaKeyCompare;
            }

            return (int) ($right->meta_id ?? 0) <=> (int) ($left->meta_id ?? 0);
        });

        return array_slice($rows, $offset, $limit);
    }

    private function merge_upload_rows(array &$mergedMap, array $rows, bool $overwrite): void {
        foreach ($rows as $row) {
            $identityKey = $this->build_upload_row_identity_key($row);
            if ($identityKey === null) {
                continue;
            }

            if ($overwrite || !isset($mergedMap[$identityKey])) {
                $mergedMap[$identityKey] = $row;
            }
        }
    }

    private function build_upload_row_identity_key($row): ?string {
        if (!is_object($row) && !is_array($row)) {
            return null;
        }

        $category = (int) (!empty(is_array($row) ? ($row['category'] ?? 0) : ($row->category ?? 0)));
        $postId = (int) (is_array($row) ? ($row['post_id'] ?? 0) : ($row->post_id ?? 0));
        $metaKey = trim((string) (is_array($row) ? ($row['meta_key'] ?? '') : ($row->meta_key ?? '')));

        if ($postId <= 0 || $metaKey === '') {
            return null;
        }

        return "{$category}:{$postId}:{$metaKey}";
    }

    private function has_speedup_db2_tables(): bool {
        return $this->table_exists($this->fifu_map_table)
            && $this->table_exists($this->fifu_key_table)
            && $this->table_exists($this->fifu_url_table);
    }

    private function has_term_speedup_db2_tables(): bool {
        return $this->has_speedup_db2_tables()
            && $this->table_exists($this->fifu_term_map_table)
            && $this->table_exists($this->terms);
    }

    private function table_exists(string $table): bool {
        $sql = $this->wpdb->prepare("SHOW TABLES LIKE %s", $table);
        $result = $this->wpdb->get_var($sql);
        return $result !== null;
    }

    /**
     * Returns all stored hex IDs.
     *
     * @return array
     */
    public function get_all_hex_ids(): array {
        if (!function_exists('fifu_db2_mode')) {
            return $this->get_all_hex_ids_legacy();
        }

        $mode = fifu_db2_mode();

        if ($mode === Fifu_Db2_Mode::MODE_DB2 || $mode === Fifu_Db2_Mode::MODE_HYBRID) {
            $db2Rows = $this->get_all_hex_ids_db2();
            if ($db2Rows !== null) {
                return $db2Rows;
            }
        }

        return $this->get_all_hex_ids_legacy();
    }

    private function get_all_hex_ids_legacy(): array {
        $sql = "
            (
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, '/', -1), '-', 1) AS hex_id
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id
                WHERE pm.meta_key = 'fifu_image_url'
                AND pm.meta_value LIKE '%https://cdn.fifu.app/%'
            )
        ";

        if (class_exists('WooCommerce')) {
            $sql .= "
                UNION
                (
                    SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, '/', -1), '-', 1) AS hex_id
                    FROM {$this->termmeta} tm
                    INNER JOIN {$this->terms} t ON tm.term_id = t.term_id
                    WHERE tm.meta_key IN ('fifu_image_url', 'fifu_image_url')
                    AND tm.meta_value LIKE '%https://cdn.fifu.app/%'
                )
            ";
        }

        $sql .= "
            ORDER BY hex_id DESC
        ";

        $hexIds = $this->wpdb->get_col($sql);
        return $hexIds ?: [];
    }

    private function get_all_hex_ids_db2(): ?array {
        if (!$this->has_speedup_db2_tables()) {
            return null;
        }

        if (class_exists('WooCommerce') && !$this->has_term_speedup_db2_tables()) {
            return null;
        }

        $sql = "
            (
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(u.url, '/', -1), '-', 1) AS hex_id
                FROM {$this->fifu_map_table} m
                INNER JOIN {$this->fifu_key_table} k ON k.key_id = m.key_id
                INNER JOIN {$this->fifu_url_table} u ON u.hash = m.hash
                INNER JOIN {$this->posts} p ON p.ID = m.post_id
                WHERE k.key_type = 'image'
                  AND m.key_index = 0
                  AND u.url LIKE '%https://cdn.fifu.app/%'
            )
        ";

        if (class_exists('WooCommerce')) {
            $sql .= "
                UNION
                (
                    SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(u.url, '/', -1), '-', 1) AS hex_id
                    FROM {$this->fifu_term_map_table} tm
                    INNER JOIN {$this->fifu_key_table} k ON k.key_id = tm.key_id
                    INNER JOIN {$this->fifu_url_table} u ON u.hash = tm.hash
                    INNER JOIN {$this->terms} t ON t.term_id = tm.term_id
                    WHERE k.key_type = 'image'
                      AND u.url LIKE '%https://cdn.fifu.app/%'
                )
            ";
        }

        $sql .= "
            ORDER BY hex_id DESC
        ";

        $hexIds = $this->wpdb->get_col($sql);
        if ($hexIds === false) {
            return null;
        }

        return $hexIds;
    }

    /**
     * Retrieves posts that have external storage entries.
     *
     * @param array $storage_ids
     * @return array
     */
    public function get_posts_su(array $storage_ids): array {
        if (!function_exists('fifu_db2_mode')) {
            return $this->get_posts_su_legacy($storage_ids);
        }

        $mode = fifu_db2_mode();

        if ($mode === Fifu_Db2_Mode::MODE_DB2) {
            $db2Rows = $this->get_posts_su_db2($storage_ids);
            if ($db2Rows !== null) {
                return $db2Rows;
            }

            return $this->get_posts_su_legacy($storage_ids);
        }

        if ($mode === Fifu_Db2_Mode::MODE_LEGACY || $mode === Fifu_Db2_Mode::MODE_HYBRID) {
            return $this->get_posts_su_hybrid($storage_ids);
        }

        return $this->get_posts_su_legacy($storage_ids);
    }

    /**
     * Returns only post/product IDs that reference the provided Cloud storage IDs.
     *
     * This is intentionally post-only and must not inspect term/termmeta/term DB2
     * tables. It is used by Cloud preflight migration before delete, where category
     * behavior must remain untouched.
     *
     * @param array $storage_ids
     * @return int[]
     */
    public function get_post_ids_su_for_cloud_preflight(array $storage_ids): array {
        $ids = $this->sanitize_storage_ids($storage_ids);
        if ($ids === []) {
            return [];
        }

        $postIds = [];
        $placeholders = implode(',', array_fill(0, count($ids), '%s'));

        $filterPostImage = $this->wpdb->prepare(
            "AND SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, '/', 5), '/', -1) IN ($placeholders)",
            $ids
        );

        $filterPostVideo = $this->wpdb->prepare(
            "AND SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1) IN ($placeholders)",
            $ids
        );

        $legacySql = "
            (
                SELECT DISTINCT pm.post_id
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = 'fifu_image_url'
                  AND pm.meta_value LIKE 'https://cdn.fifu.app/%'
                  {$filterPostImage}
            )
            UNION
            (
                SELECT DISTINCT pm.post_id
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = 'fifu_image_url'
                  AND pm.meta_value LIKE '%https://cdn.fifu.app/%'
                  {$filterPostVideo}
            )
        ";

        $legacyPostIds = $this->wpdb->get_col($legacySql);
        if (is_array($legacyPostIds)) {
            foreach ($legacyPostIds as $postId) {
                $postId = (int) $postId;
                if ($postId > 0) {
                    $postIds[$postId] = true;
                }
            }
        }

        if ($this->has_speedup_db2_tables()) {
            $exprImg = "SUBSTRING_INDEX(SUBSTRING_INDEX(u.url, '/', 5), '/', -1)";
            $exprVid = "SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(u.url, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1)";

            $filterDb2PostImage = $this->wpdb->prepare(
                "AND {$exprImg} IN ($placeholders)",
                $ids
            );

            $filterDb2PostVideo = $this->wpdb->prepare(
                "AND {$exprVid} IN ($placeholders)",
                $ids
            );

            $db2Sql = "
                (
                    SELECT DISTINCT m.post_id
                    FROM {$this->fifu_map_table} m
                    INNER JOIN {$this->fifu_key_table} k ON k.key_id = m.key_id
                    INNER JOIN {$this->fifu_url_table} u ON u.hash = m.hash
                    INNER JOIN {$this->posts} p ON p.ID = m.post_id
                    WHERE k.key_type = 'image'
                      AND m.key_index = 0
                      AND u.url LIKE 'https://cdn.fifu.app/%'
                      {$filterDb2PostImage}
                )
                UNION
                (
                    SELECT DISTINCT m.post_id
                    FROM {$this->fifu_map_table} m
                    INNER JOIN {$this->fifu_key_table} k ON k.key_id = m.key_id
                    INNER JOIN {$this->fifu_url_table} u ON u.hash = m.hash
                    INNER JOIN {$this->posts} p ON p.ID = m.post_id
                    WHERE k.key_type = 'image'
                      AND m.key_index = 0
                      AND u.url LIKE '%https://cdn.fifu.app/%'
                      {$filterDb2PostVideo}
                )
            ";

            $db2PostIds = $this->wpdb->get_col($db2Sql);
            if (is_array($db2PostIds)) {
                foreach ($db2PostIds as $postId) {
                    $postId = (int) $postId;
                    if ($postId > 0) {
                        $postIds[$postId] = true;
                    }
                }
            }
        }

        return array_keys($postIds);
    }

    public function get_term_ids_su_for_cloud_preflight(array $storage_ids): array
    {
        $storage_ids = $this->sanitize_storage_ids($storage_ids);
        if ($storage_ids === []) {
            return [];
        }

        $termIds = [];
        $placeholders = implode(',', array_fill(0, count($storage_ids), '%s'));
        $legacy = $this->wpdb->prepare(
            "SELECT DISTINCT tm.term_id FROM {$this->termmeta} tm INNER JOIN {$this->terms} t ON tm.term_id = t.term_id
             WHERE tm.meta_key = 'fifu_image_url' AND (
                 tm.meta_value LIKE '%%https://cdn.fifu.app/%%'
                 OR tm.meta_value LIKE '%%fifu-thumb=%%'
             ) AND (
                 SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, '/', 5), '/', -1) IN ($placeholders)
                 OR SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1) IN ($placeholders)
             )",
            ...array_merge($storage_ids, $storage_ids)
        );
        foreach ((array) $this->wpdb->get_col($legacy) as $termId) {
            if ((int) $termId > 0) {
                $termIds[(int) $termId] = true;
            }
        }

        if ($this->has_term_speedup_db2_tables()) {
            $db2 = $this->wpdb->prepare(
                "SELECT DISTINCT tm.term_id FROM {$this->fifu_term_map_table} tm
                 INNER JOIN {$this->fifu_key_table} k ON k.key_id = tm.key_id
                 INNER JOIN {$this->fifu_url_table} u ON u.hash = tm.hash
                 INNER JOIN {$this->terms} t ON t.term_id = tm.term_id
                 WHERE k.key_type = 'image' AND (
                     u.url LIKE '%%https://cdn.fifu.app/%%' OR u.url LIKE '%%fifu-thumb=%%'
                 ) AND (
                     SUBSTRING_INDEX(SUBSTRING_INDEX(u.url, '/', 5), '/', -1) IN ($placeholders)
                     OR SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(u.url, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1) IN ($placeholders)
                 )",
                ...array_merge($storage_ids, $storage_ids)
            );
            foreach ((array) $this->wpdb->get_col($db2) as $termId) {
                if ((int) $termId > 0) {
                    $termIds[(int) $termId] = true;
                }
            }
        }

        return array_keys($termIds);
    }

    private function get_posts_su_hybrid(array $storage_ids): array {
        $mergedMap = [];
        $db2Rows = $this->get_posts_su_db2($storage_ids);
        if ($db2Rows !== null) {
            $this->merge_posts_su_rows($mergedMap, $db2Rows, true);
        }

        $legacyRows = $this->get_posts_su_legacy($storage_ids);
        $this->merge_posts_su_rows($mergedMap, $legacyRows, false);

        $rows = array_values($mergedMap);
        usort($rows, static function ($left, $right): int {
            $postCompare = (int) ($right->post_id ?? 0) <=> (int) ($left->post_id ?? 0);
            if ($postCompare !== 0) {
                return $postCompare;
            }

            $categoryCompare = (int) ($left->category ?? 0) <=> (int) ($right->category ?? 0);
            if ($categoryCompare !== 0) {
                return $categoryCompare;
            }

            $metaKeyCompare = strcmp((string) ($left->meta_key ?? ''), (string) ($right->meta_key ?? ''));
            if ($metaKeyCompare !== 0) {
                return $metaKeyCompare;
            }

            return (int) ($right->meta_id ?? 0) <=> (int) ($left->meta_id ?? 0);
        });

        return $rows;
    }

    private function merge_posts_su_rows(array &$mergedMap, array $rows, bool $overwrite): void {
        foreach ($rows as $row) {
            $identityKey = $this->build_upload_row_identity_key($row);
            if ($identityKey === null) {
                continue;
            }

            if ($overwrite || !isset($mergedMap[$identityKey])) {
                $mergedMap[$identityKey] = $row;
            }
        }
    }

    private function get_posts_su_legacy(array $storage_ids): array {
        $filter_post_image = '';
        $filter_term_image = '';
        $filter_post_video = '';
        $filter_term_video = '';

        $ids = $this->sanitize_storage_ids($storage_ids);
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '%s'));
            $filter_post_image = $this->wpdb->prepare(
                "AND SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, '/', 5), '/', -1) IN ($placeholders)",
                $ids
            );
            $filter_term_image = $this->wpdb->prepare(
                "AND SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, '/', 5), '/', -1) IN ($placeholders)",
                $ids
            );
            $filter_post_video = $this->wpdb->prepare(
                "AND SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1) IN ($placeholders)",
                $ids
            );
            $filter_term_video = $this->wpdb->prepare(
                "AND SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1) IN ($placeholders)",
                $ids
            );
        }

        $sql = "
            (
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, '/', 5), '/', -1) AS storage_id, 
                    p.post_title, 
                    p.post_date, 
                    pm.meta_id, 
                    pm.post_id, 
                    pm.meta_key, 
                    false AS category
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id
                WHERE pm.meta_key = 'fifu_image_url'
                AND pm.meta_value LIKE 'https://cdn.fifu.app/%'
                {$filter_post_image}
            )
            UNION
            (
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1) AS storage_id, 
                    p.post_title, 
                    p.post_date, 
                    pm.meta_id, 
                    pm.post_id, 
                    pm.meta_key, 
                    false AS category
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id
                WHERE pm.meta_key = 'fifu_image_url'
                AND pm.meta_value LIKE '%https://cdn.fifu.app/%'
                {$filter_post_video}
            )
        ";

        $sql .= "
            UNION
            (
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, '/', 5), '/', -1) AS storage_id, 
                    t.name AS post_title, 
                    NULL AS post_date, 
                    tm.meta_id, 
                    tm.term_id AS post_id, 
                    tm.meta_key, 
                    true AS category
                FROM {$this->termmeta} tm
                INNER JOIN {$this->terms} t ON tm.term_id = t.term_id
                WHERE tm.meta_key = 'fifu_image_url'
                AND tm.meta_value LIKE 'https://cdn.fifu.app/%'
                {$filter_term_image}
            )
            UNION
            (
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1) AS storage_id,
                    t.name AS post_title, 
                    NULL AS post_date, 
                    tm.meta_id, 
                    tm.term_id AS post_id, 
                    tm.meta_key, 
                    true AS category
                FROM {$this->termmeta} tm
                INNER JOIN {$this->terms} t ON tm.term_id = t.term_id
                WHERE tm.meta_key = 'fifu_image_url'
                AND tm.meta_value LIKE '%https://cdn.fifu.app/%'
                {$filter_term_video}
            )
        ";

        $rows = $this->wpdb->get_results($sql);
        return $rows ?: [];
    }

    private function get_posts_su_db2(array $storage_ids): ?array {
        if (!$this->has_speedup_db2_tables()) {
            return null;
        }

        $includeTermRows = $this->has_term_speedup_db2_tables();

        $exprImg = "SUBSTRING_INDEX(SUBSTRING_INDEX(u.url, '/', 5), '/', -1)";
        $exprVid = "SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(u.url, 'fifu-thumb=', 5), 'fifu-thumb=', -1), '/', 5), '/', -1)";
        $filterPostImage = '';
        $filterTermImage = '';
        $filterPostVideo = '';
        $filterTermVideo = '';

        $ids = $this->sanitize_storage_ids($storage_ids);
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '%s'));
            $filterPostImage = $this->wpdb->prepare("AND {$exprImg} IN ($placeholders)", $ids);
            $filterTermImage = $this->wpdb->prepare("AND {$exprImg} IN ($placeholders)", $ids);
            $filterPostVideo = $this->wpdb->prepare("AND {$exprVid} IN ($placeholders)", $ids);
            $filterTermVideo = $this->wpdb->prepare("AND {$exprVid} IN ($placeholders)", $ids);
        }

        $sql = "
            (
                SELECT {$exprImg} AS storage_id,
                    p.post_title,
                    p.post_date,
                    m.id AS meta_id,
                    m.post_id,
                    'fifu_image_url' AS meta_key,
                    false AS category
                FROM {$this->fifu_map_table} m
                INNER JOIN {$this->fifu_key_table} k ON k.key_id = m.key_id
                INNER JOIN {$this->fifu_url_table} u ON u.hash = m.hash
                INNER JOIN {$this->posts} p ON p.ID = m.post_id
                WHERE k.key_type = 'image'
                  AND m.key_index = 0
                  AND u.url LIKE 'https://cdn.fifu.app/%'
                  {$filterPostImage}
            )
            UNION
            (
                    SELECT {$exprVid} AS storage_id,
                        p.post_title,
                        p.post_date,
                        m.id AS meta_id,
                        m.post_id,
                        'fifu_image_url' AS meta_key,
                        false AS category
                FROM {$this->fifu_map_table} m
                INNER JOIN {$this->fifu_key_table} k ON k.key_id = m.key_id
                INNER JOIN {$this->fifu_url_table} u ON u.hash = m.hash
                INNER JOIN {$this->posts} p ON p.ID = m.post_id
                WHERE k.key_type = 'image'
                  AND m.key_index = 0
                  AND u.url LIKE '%https://cdn.fifu.app/%'
                  {$filterPostVideo}
            )
        ";

        if ($includeTermRows) {
            $sql .= "
                UNION
                (
                    SELECT {$exprImg} AS storage_id,
                        t.name AS post_title,
                        NULL AS post_date,
                        tm.id AS meta_id,
                        tm.term_id AS post_id,
                        'fifu_image_url' AS meta_key,
                        true AS category
                    FROM {$this->fifu_term_map_table} tm
                    INNER JOIN {$this->fifu_key_table} k ON k.key_id = tm.key_id
                    INNER JOIN {$this->fifu_url_table} u ON u.hash = tm.hash
                    INNER JOIN {$this->terms} t ON t.term_id = tm.term_id
                    WHERE k.key_type = 'image'
                      AND u.url LIKE 'https://cdn.fifu.app/%'
                      {$filterTermImage}
                )
                UNION
                (
                    SELECT {$exprVid} AS storage_id,
                        t.name AS post_title,
                        NULL AS post_date,
                        tm.id AS meta_id,
                        tm.term_id AS post_id,
                        'fifu_image_url' AS meta_key,
                        true AS category
                    FROM {$this->fifu_term_map_table} tm
                    INNER JOIN {$this->fifu_key_table} k ON k.key_id = tm.key_id
                    INNER JOIN {$this->fifu_url_table} u ON u.hash = tm.hash
                    INNER JOIN {$this->terms} t ON t.term_id = tm.term_id
                    WHERE k.key_type = 'image'
                      AND u.url LIKE '%https://cdn.fifu.app/%'
                      {$filterTermVideo}
                )
            ";
        }

        $results = $this->wpdb->get_results($sql);
        if ($results === false) {
            return null;
        }

        return $results;
    }

    private function sanitize_storage_ids(array $storage_ids): array {
        $ids = array_values(
            array_filter(
                array_map('strval', $storage_ids),
                static function ($value) {
                    return $value !== '';
                }
            )
        );
        return $ids;
    }

    /**
     * Applies custom field updates for the speed-up bucket view.
     *
     * @param string|int $bucket_id
     * @param array $thumbnails
     * @param bool $isCategory
     * @return int
     */
    public function speed_up_custom_fields(string|int $bucket_id, array $thumbnails, bool $isCategory): int {
        $table = $isCategory ? $this->termmeta : $this->postmeta;

        if (empty($thumbnails)) {
            return 0;
        }

        $legacyBridge = null;
        if (
            function_exists('fifu_db2_legacy_write_bridge')
            && class_exists('Fifu_Db2_Legacy_Write_Bridge')
        ) {
            $bridgeCandidate = fifu_db2_legacy_write_bridge();
            if ($bridgeCandidate instanceof Fifu_Db2_Legacy_Write_Bridge) {
                $legacyBridge = $bridgeCandidate;
            }
        }

        $metaIds = [];
        foreach ($thumbnails as $thumbnail) {
            $metaIds[] = (int) ($thumbnail->meta_id ?? 0);
        }
        $metaIds = array_values(array_unique($metaIds));

        $metaById = [];
        if (!empty($metaIds)) {
            $objectColumn = $isCategory ? 'term_id' : 'post_id';
            $placeholders = implode(',', array_fill(0, count($metaIds), '%d'));
            $sql = "
                SELECT meta_id, {$objectColumn} AS object_id, meta_key
                FROM {$table}
                WHERE meta_id IN ({$placeholders})
            ";
            $query = $this->wpdb->prepare($sql, ...$metaIds);
            $metaRows = $this->wpdb->get_results($query);
            if (is_array($metaRows)) {
                foreach ($metaRows as $row) {
                    $id = (int) ($row->meta_id ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $metaById[$id] = [
                        'object_id' => (int) ($row->object_id ?? 0),
                        'meta_key' => (string) ($row->meta_key ?? ''),
                    ];
                }
            }
        }

        $db2Rows = [];

        foreach ($thumbnails as $thumbnail) {
            $su_url = $this->build_su_url(
                $bucket_id,
                (string) $thumbnail->storage_id
            );

            $target = $this->resolve_speed_up_custom_field_target($thumbnail, $metaById, $isCategory);
            if ($target === null) {
                continue;
            }

            $metaId = (int) ($thumbnail->meta_id ?? 0);
            $metaKey = $target['meta_key'];
            if ($metaKey !== 'fifu_image_url') {
                continue;
            }
            $objectId = $target['object_id'];
            $isManagedKey = $this->is_db2_managed_fifu_key($metaKey);
            $isDb2Row = $isManagedKey && $objectId > 0 && $legacyBridge !== null;

            if ($isDb2Row) {
                $db2Rows[] = [
                    'meta_id' => $metaId,
                    'object_id' => $objectId,
                    'meta_key' => $metaKey,
                    'meta_value' => $su_url,
                ];
                continue;
            }

        }

        if (
            $legacyBridge
            && !empty($db2Rows)
        ) {
            foreach ($db2Rows as $row) {
                $metaKey = (string) ($row['meta_key'] ?? '');
                $metaValue = (string) ($row['meta_value'] ?? '');
                $objectId = (int) ($row['object_id'] ?? 0);

                if ($metaKey === '' || $metaValue === '') {
                    continue;
                }

                $ok = $isCategory
                    ? $legacyBridge->handle_term_meta_change($objectId, $metaKey, $metaValue)
                    : $legacyBridge->handle_post_meta_change($objectId, $metaKey, $metaValue);

                if ($ok !== true) {
                    continue;
                }

                if ($isCategory) {
                    $this->wpdb->delete(
                        $this->termmeta,
                        ['term_id' => $objectId, 'meta_key' => $metaKey],
                        ['%d', '%s']
                    );
                    clean_term_cache($objectId);
                    wp_cache_delete($objectId, 'term_meta');

                    $this->migrate_paired_legacy_term_image_alt_after_speed_up(
                        $legacyBridge,
                        $objectId,
                        $metaKey
                    );
                    continue;
                }

                $this->wpdb->delete(
                    $this->postmeta,
                    ['post_id' => $objectId, 'meta_key' => $metaKey],
                    ['%d', '%s']
                );
                clean_post_cache($objectId);
                wp_cache_delete($objectId, 'post_meta');

                $this->migrate_paired_legacy_post_image_alt_after_speed_up(
                    $legacyBridge,
                    $objectId,
                    $metaKey
                );
            }
        }

        return 0;
    }

    /**
     * Migrates the paired legacy post ALT row after a successful featured image URL write.
     */
    private function migrate_paired_legacy_post_image_alt_after_speed_up(
        object $legacyBridge,
        int $postId,
        string $imageMetaKey
    ): void {
        if ($postId <= 0 || $imageMetaKey !== 'fifu_image_url') {
            return;
        }

        if (!method_exists($legacyBridge, 'handle_post_meta_change')) {
            return;
        }

        $altMetaKey = 'fifu_image_alt';
        $legacyAlt = $this->get_legacy_post_meta_value_for_speed_up($postId, $altMetaKey);
        if ($legacyAlt === null) {
            return;
        }

        $ok = $legacyBridge->handle_post_meta_change($postId, $altMetaKey, $legacyAlt);
        if ($ok !== true) {
            return;
        }

        $this->wpdb->delete(
            $this->postmeta,
            ['post_id' => $postId, 'meta_key' => $altMetaKey],
            ['%d', '%s']
        );
        clean_post_cache($postId);
        wp_cache_delete($postId, 'post_meta');
    }

    /**
     * Migrates the paired legacy term ALT row after a successful category URL write.
     */
    private function migrate_paired_legacy_term_image_alt_after_speed_up(
        object $legacyBridge,
        int $termId,
        string $imageMetaKey
    ): void {
        if ($termId <= 0 || $imageMetaKey !== 'fifu_image_url') {
            return;
        }

        if (!method_exists($legacyBridge, 'handle_term_meta_change')) {
            return;
        }

        $altMetaKey = 'fifu_image_alt';
        $legacyAlt = $this->get_legacy_term_meta_value_for_speed_up($termId, $altMetaKey);
        if ($legacyAlt === null) {
            return;
        }

        $ok = $legacyBridge->handle_term_meta_change($termId, $altMetaKey, $legacyAlt);
        if ($ok !== true) {
            return;
        }

        $this->wpdb->delete(
            $this->termmeta,
            ['term_id' => $termId, 'meta_key' => $altMetaKey],
            ['%d', '%s']
        );
        clean_term_cache($termId);
        wp_cache_delete($termId, 'term_meta');
    }

    /**
     * Returns the legacy post meta value when the row exists, otherwise null.
     *
     * get_post_meta(..., true) cannot reliably distinguish a missing row from an
     * existing empty row, so use the postmeta table directly. Existing empty ALT
     * rows are still passed to the bridge so DB2 can confirm/delete the logical ALT
     * state before the legacy row is removed.
     */
    private function get_legacy_post_meta_value_for_speed_up(int $postId, string $metaKey): ?string
    {
        if ($postId <= 0 || $metaKey === '') {
            return null;
        }

        $value = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT meta_value FROM {$this->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
            $postId,
            $metaKey
        ));

        return $value === null || $value === false ? null : (string) $value;
    }

    /**
     * Returns the legacy term meta value when the row exists, otherwise null.
     */
    private function get_legacy_term_meta_value_for_speed_up(int $termId, string $metaKey): ?string
    {
        if ($termId <= 0 || $metaKey === '') {
            return null;
        }

        $value = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT meta_value FROM {$this->termmeta} WHERE term_id = %d AND meta_key = %s LIMIT 1",
            $termId,
            $metaKey
        ));

        return $value === null || $value === false ? null : (string) $value;
    }

    /**
     * Resolves the target object/meta pair for a speed-up write-back row.
     *
     * @param object $thumbnail
     * @param array $metaById
     * @param bool $isCategory
     * @return array|null
     */
    private function resolve_speed_up_custom_field_target(object $thumbnail, array $metaById, bool $isCategory): ?array {
        $metaKey = trim((string) ($thumbnail->meta_key ?? ''));
        $objectId = $isCategory
            ? (int) ($thumbnail->term_id ?? 0)
            : (int) ($thumbnail->post_id ?? 0);

        if ($isCategory && $objectId <= 0) {
            $objectId = (int) ($thumbnail->post_id ?? 0);
        }

        /*
         * DB2 Cloud rows carry an opaque numeric meta_id whose namespace is not
         * wp_postmeta/termmeta. For DB2-managed FIFU keys, the explicit object
         * identity from the Cloud row is therefore authoritative.
         *
         * Looking up meta_id first can accidentally match an unrelated WordPress
         * metadata row and either skip the correct write or redirect it to another
         * object.
         */
        if (
            $metaKey !== ''
            && $objectId > 0
            && $this->is_db2_managed_fifu_key($metaKey)
        ) {
            return [
                'object_id' => $objectId,
                'meta_key' => $metaKey,
                'resolved_via_meta_table' => false,
            ];
        }

        $metaId = (int) ($thumbnail->meta_id ?? 0);

        if ($metaId > 0 && isset($metaById[$metaId])) {
            $metaInfo = $metaById[$metaId];
            $resolvedMetaKey = trim((string) ($metaInfo['meta_key'] ?? ''));
            $resolvedObjectId = (int) ($metaInfo['object_id'] ?? 0);

            if ($resolvedMetaKey === '' || $resolvedObjectId <= 0) {
                return null;
            }

            return [
                'object_id' => $resolvedObjectId,
                'meta_key' => $resolvedMetaKey,
                'resolved_via_meta_table' => true,
            ];
        }

        if ($metaKey === '' || $objectId <= 0) {
            return null;
        }

        return [
            'object_id' => $objectId,
            'meta_key' => $metaKey,
            'resolved_via_meta_table' => false,
        ];
    }

    /**
     * Migrates the legacy featured image URL and paired ALT to DB2 before a FIFU Cloud operation.
     *
     * This preflight does not migrate complete post media, galleries, lists, sliders, videos, or indexed media.
     *
     * @param int[] $postIds
     * @return void
     */
    public function migrate_posts_legacy_featured_image_state_before_cloud_operation(array $postIds): void
    {
        $postIds = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $postIds),
                    static fn (int $postId): bool => $postId > 0
                )
            )
        );

        if ($postIds === []) {
            return;
        }

        if (
            !function_exists('fifu_db2_legacy_write_bridge')
            || !class_exists('Fifu_Db2_Legacy_Write_Bridge')
        ) {
            return;
        }

        $bridge = fifu_db2_legacy_write_bridge();

        if (!$bridge instanceof Fifu_Db2_Legacy_Write_Bridge) {
            return;
        }

        $this->sync_featured_image_meta_group_before_cloud_operation($bridge, $postIds);
    }

    /**
     * Loads selected legacy postmeta rows for featured-image migration.
     *
     * @param array $postIds
     * @param array $metaKeys
     * @return array
     */
    private function get_post_list_meta_rows(array $postIds, array $metaKeys): array {
        if (empty($postIds) || empty($metaKeys)) {
            return [];
        }

        $postPlaceholders = implode(',', array_fill(0, count($postIds), '%d'));
        $keyPlaceholders = implode(',', array_fill(0, count($metaKeys), '%s'));
        $query = $this->wpdb->prepare(
            "
                SELECT post_id, meta_key, meta_value
                FROM {$this->postmeta}
                WHERE post_id IN ({$postPlaceholders})
                AND meta_key IN ({$keyPlaceholders})
            ",
            ...array_merge($postIds, $metaKeys)
        );

        $rows = $this->wpdb->get_results($query);
        if (!is_array($rows)) {
            return [];
        }

        $rowsByPostId = [];
        foreach ($rows as $row) {
            $postId = (int) ($row->post_id ?? 0);
            $metaKey = (string) ($row->meta_key ?? '');
            if ($postId <= 0 || $metaKey === '') {
                continue;
            }
            $rowsByPostId[$postId][$metaKey] = [
                'meta_value' => (string) ($row->meta_value ?? ''),
            ];
        }

        return $rowsByPostId;
    }

    /**
     * Migrates simple featured image legacy state before Cloud operations.
     *
     * This covers posts that only have fifu_image_url + fifu_image_alt and no
     * fifu_list_url. It writes both through the DB2 legacy bridge and deletes
     * legacy postmeta only after successful DB2 writes.
     *
     * @param object $legacyBridge
     * @param int[]  $postIds
     * @return void
     */
    private function sync_featured_image_meta_group_before_cloud_operation(object $legacyBridge, array $postIds): void
    {
        if (!method_exists($legacyBridge, 'handle_post_meta_change')) {
            return;
        }

        $rowsByPostId = $this->get_post_list_meta_rows($postIds, [
            'fifu_image_url',
            'fifu_image_alt',
        ]);

        if ($rowsByPostId === []) {
            return;
        }

        foreach ($rowsByPostId as $postId => $rows) {
            $postId = (int) $postId;
            if ($postId <= 0) {
                continue;
            }

            $imageUrl = trim((string) ($rows['fifu_image_url']['meta_value'] ?? ''));
            if ($imageUrl === '') {
                continue;
            }

            $urlOk = $legacyBridge->handle_post_meta_change($postId, 'fifu_image_url', $imageUrl);
            if ($urlOk !== true) {
                continue;
            }

            $this->wpdb->delete(
                $this->postmeta,
                ['post_id' => $postId, 'meta_key' => 'fifu_image_url'],
                ['%d', '%s']
            );

            if (array_key_exists('fifu_image_alt', $rows)) {
                $imageAlt = (string) ($rows['fifu_image_alt']['meta_value'] ?? '');
                $altOk = $legacyBridge->handle_post_meta_change($postId, 'fifu_image_alt', $imageAlt);

                if ($altOk === true) {
                    $this->wpdb->delete(
                        $this->postmeta,
                        ['post_id' => $postId, 'meta_key' => 'fifu_image_alt'],
                        ['%d', '%s']
                    );
                }
            }

            clean_post_cache($postId);
            wp_cache_delete($postId, 'post_meta');
        }
    }

    public function migrate_terms_legacy_media_state_before_cloud_delete(array $termIds): void
    {
        $termIds = array_values(array_unique(array_filter(array_map('intval', $termIds), static fn($id): bool => $id > 0)));
        if ($termIds === [] || !function_exists('fifu_db2_legacy_write_bridge')) {
            return;
        }

        $bridge = fifu_db2_legacy_write_bridge();
        if (!$bridge || !method_exists($bridge, 'handle_term_meta_change')) {
            return;
        }

        foreach ($termIds as $termId) {
            $deletedLegacyMeta = false;
            $url = $this->get_legacy_term_meta_value_for_speed_up($termId, 'fifu_image_url');

            if ($url !== null && trim($url) !== '') {
                if ($bridge->handle_term_meta_change($termId, 'fifu_image_url', $url) !== true) {
                    continue;
                }

                $this->wpdb->delete($this->termmeta, ['term_id' => $termId, 'meta_key' => 'fifu_image_url'], ['%d', '%s']);
                $deletedLegacyMeta = true;
            }

            $alt = $this->get_legacy_term_meta_value_for_speed_up($termId, 'fifu_image_alt');
            if ($alt !== null && $bridge->handle_term_meta_change($termId, 'fifu_image_alt', $alt) === true) {
                $this->wpdb->delete($this->termmeta, ['term_id' => $termId, 'meta_key' => 'fifu_image_alt'], ['%d', '%s']);
                $deletedLegacyMeta = true;
            }

            if (!$deletedLegacyMeta) {
                continue;
            }

            clean_term_cache($termId);
            wp_cache_delete($termId, 'term_meta');
        }
    }
    /**
     * Reverts custom field URLs back to their original sources after speed up operations.
     *
     * @param array $thumbnails
     * @param array $urls
     * @param array $videoUrls
     * @param bool $isCategory
     * @return int|null
     */
    public function revert_custom_fields(
        array $thumbnails,
        array $urls,
        array $videoUrls,
        bool $isCategory
    ): ?int {
        $table = $isCategory ? $this->termmeta : $this->postmeta;

        $legacyBridge = null;
        if (
            function_exists('fifu_db2_legacy_write_bridge')
            && class_exists('Fifu_Db2_Legacy_Write_Bridge')
        ) {
            $bridgeCandidate = fifu_db2_legacy_write_bridge();
            if ($bridgeCandidate instanceof Fifu_Db2_Legacy_Write_Bridge) {
                $legacyBridge = $bridgeCandidate;
            }
        }

        if (empty($thumbnails)) {
            return null;
        }

        $result = 0;
        // Avoid unsafe legacy updates by meta_id; meta_id is not scoped to FIFU keys.
        foreach ($thumbnails as $thumbnail) {
            $video_url = isset($videoUrls[$thumbnail->storage_id]) ? $videoUrls[$thumbnail->storage_id] : null;
            $meta_value = $video_url ? $video_url : (isset($urls[$thumbnail->storage_id]) ? $urls[$thumbnail->storage_id] : '');
            $meta_key = (string) ($thumbnail->meta_key ?? '');

            if ($meta_key !== 'fifu_image_url') {
                continue;
            }

            if (!$this->is_db2_managed_fifu_key($meta_key)) {
                continue;
            }

            $objectId = $isCategory ? (int) ($thumbnail->term_id ?? 0) : (int) ($thumbnail->post_id ?? 0);
            if ($isCategory && $objectId <= 0) {
                $objectId = (int) ($thumbnail->post_id ?? 0);
            }

            if ($objectId <= 0) {
                continue;
            }

            if ($legacyBridge) {
                $ok = $isCategory
                    ? $legacyBridge->handle_term_meta_change($objectId, $meta_key, $meta_value)
                    : $legacyBridge->handle_post_meta_change($objectId, $meta_key, $meta_value);

                if ($ok === true) {
                    if ($isCategory) {
                        $this->wpdb->delete(
                            $this->termmeta,
                            ['term_id' => $objectId, 'meta_key' => $meta_key],
                            ['%d', '%s']
                        );
                        clean_term_cache($objectId);
                        wp_cache_delete($objectId, 'term_meta');
                    } else {
                        $this->wpdb->delete(
                            $this->postmeta,
                            ['post_id' => $objectId, 'meta_key' => $meta_key],
                            ['%d', '%s']
                        );
                        clean_post_cache($objectId);
                        wp_cache_delete($objectId, 'post_meta');
                    }

                    $result++;
                    continue;
                }
            }

            if ($isCategory) {
                update_term_meta($objectId, $meta_key, $meta_value);
            } else {
                update_post_meta($objectId, $meta_key, $meta_value);
            }

            $result++;
        }

        return $result;
    }

    /**
     * Restores attachment post content filtered values after a speed up rollback.
     *
     * @param array $urls
     * @param array $thumbnails
     * @param array $attachmentIdsMap
     * @return array
     */
    public function revert_attachments(
        array $urls,
        array $videoUrls,
        array $thumbnails,
        array $attachmentIdsMap
    ): array {
        if ($urls === null || !is_array($urls)) {
            $urls = [];
        }
        if ($videoUrls === null || !is_array($videoUrls)) {
            $videoUrls = [];
        }
        if ($thumbnails === null || !is_array($thumbnails)) {
            $thumbnails = [];
        }
        if ($attachmentIdsMap === null || !is_array($attachmentIdsMap)) {
            $attachmentIdsMap = [];
        }

        $count = 0;
        $query = "
            INSERT INTO {$this->posts} (id, post_content_filtered) VALUES ";

        foreach ($thumbnails as $thumbnail) {
            if (!isset($attachmentIdsMap[$thumbnail->meta_id])) {
                continue;
            }

            if ($count++ !== 0) {
                $query .= ", ";
            }

            $url = $this->resolve_original_attachment_url_for_thumbnail($thumbnail, $urls);
            $query .= $this->wpdb->prepare("(%d, %s)", (int) $attachmentIdsMap[$thumbnail->meta_id], $url);
        }

        if ($count === 0) {
            return [];
        }

        $query .= "ON DUPLICATE KEY UPDATE post_content_filtered=VALUES(post_content_filtered)";
        $results = $this->wpdb->get_results($query);
        return $results ?: [];
    }

    /**
     * Restores attachment metadata values after a speed up rollback.
     *
     * @param array $urls
     * @param array $thumbnails
     * @param array $metaIdsMap
     * @return array
     */
    public function revert_attachments_meta(
        array $urls,
        array $videoUrls,
        array $thumbnails,
        array $metaIdsMap
    ): array {
        $attachmentMetaIds = [];
        $metaValueById = [];
        foreach ($thumbnails as $thumbnail) {
            if (!isset($metaIdsMap[$thumbnail->meta_id])) {
                continue;
            }

            $metaId = (int) $metaIdsMap[$thumbnail->meta_id];
            if ($metaId <= 0) {
                continue;
            }

            $url = $this->resolve_original_attachment_url_for_thumbnail($thumbnail, $urls);
            $attachmentMetaIds[] = $metaId;
            $metaValueById[$metaId] = $url;
        }

        if (empty($attachmentMetaIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($attachmentMetaIds), '%d'));
        $sql = "
            SELECT meta_id, post_id, meta_key
            FROM {$this->postmeta}
            WHERE meta_id IN ({$placeholders})
            AND meta_key = %s
        ";
        $params = array_merge($attachmentMetaIds, ['_wp_attached_file']);
        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $params));

        if ($rows === null) {
            return [];
        }

        $updated = [];
        foreach ($rows as $row) {
            $metaId = (int) ($row->meta_id ?? 0);
            $postId = (int) ($row->post_id ?? 0);
            $metaValue = $metaValueById[$metaId] ?? null;
            $metaKey = (string) ($row->meta_key ?? '');

            if ($metaId <= 0 || $postId <= 0 || $metaValue === null || $metaKey === '') {
                continue;
            }

            if (strpos($metaKey, 'fifu_') === 0) {
                continue;
            }

            // Avoid unsafe legacy updates by meta_id; use (post_id, meta_key) scoping.
            update_post_meta($postId, $metaKey, $metaValue);
            $updated[] = $postId;
        }

        return $updated;
    }

    /**
     * Resolve the original thumbnail URL for attachment-backed records.
     *
     * Attachments store the rendered thumbnail source, not the raw video URL.
     *
     * @param object $thumbnail
     * @param array $urls
     * @return string
     */
    private function resolve_original_attachment_url_for_thumbnail(object $thumbnail, array $urls): string
    {
        $storageId = (string) ($thumbnail->storage_id ?? '');
        if ($storageId === '') {
            return '';
        }

        return (string) ($urls[$storageId] ?? '');
    }

    /**
     * Collects attachment IDs for the provided thumbnail mappings.
     *
     * @param array $thumbnails
     * @param bool $isCategory
     * @return array
     */
    public function get_thumbnail_ids(array $thumbnails, bool $isCategory): array {
        $ids_list = [];
        foreach ($thumbnails as $thumbnail) {
            $ids_list[] = (int) $thumbnail->post_id;
        }

        $ids = Fifu_Db2_Sql_Helper::sanitize_ids_csv($ids_list);

        if ($isCategory) {
            $result = $this->wpdb->get_results("
                SELECT term_id AS post_id, meta_value AS att_id
                FROM {$this->termmeta} 
                WHERE term_id IN ({$ids}) 
                AND meta_key = 'thumbnail_id'
            ");
        } else {
            $result = $this->wpdb->get_results("
                SELECT post_id, meta_value AS att_id
                FROM {$this->postmeta} 
                WHERE post_id IN ({$ids}) 
                AND meta_key = '_thumbnail_id'
            ");
        }

        $featured_map = [];
        if (is_array($result)) {
            foreach ($result as $res) {
                $featured_map[$res->post_id] = $res->att_id;
            }
        }

        $map = [];
        foreach ($thumbnails as $thumbnail) {
            if (isset($featured_map[$thumbnail->post_id])) {
                $map[$thumbnail->meta_id] = $featured_map[$thumbnail->post_id];
            }
        }

        return $map;
    }

    /**
     * Updates attachments for a storage bucket.
     *
     * @param string|int $bucket_id
     * @param array $thumbnails
     * @param array $att_ids_map
     * @return array
     */
    public function speed_up_attachments(string|int $bucket_id, array $thumbnails, array $att_ids_map): array {
        $count = 0;
        $query = "
        INSERT INTO {$this->posts} (id, post_content_filtered) VALUES ";

        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) {
                continue;
            }

            $su_url = $this->build_su_url($bucket_id, $thumbnail->storage_id);

            if ($count++ !== 0) {
                $query .= ", ";
            }

            $query .= $this->wpdb->prepare("(%d, %s)", $att_ids_map[$thumbnail->meta_id], $su_url) . " ";
        }

        $query .= "ON DUPLICATE KEY UPDATE post_content_filtered=VALUES(post_content_filtered)";
        $result = $this->wpdb->get_results($query);
        return $result ?: [];
    }

    /**
     * Collects metadata IDs for the provided attachments.
     *
     * @param array $thumbnails
     * @param array $att_ids_map
     * @return array
     */
    public function get_thumbnail_meta_ids(array $thumbnails, array $att_ids_map): array {
        $ids_arr = [];
        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) {
                continue;
            }
            $ids_arr[] = (int) $att_ids_map[$thumbnail->meta_id];
        }

        $ids_arr = array_values(array_unique(array_filter($ids_arr, static function ($value) {
            return $value > 0;
        })));

        if (empty($ids_arr)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids_arr), '%d'));
        $sql = "
            SELECT meta_id, post_id
            FROM {$this->postmeta}
            WHERE post_id IN ({$placeholders})
            AND meta_key = %s
        ";
        $params = array_merge($ids_arr, ['_wp_attached_file']);
        $result = $this->wpdb->get_results($this->wpdb->prepare($sql, $params));

        $attid_metaid_map = [];
        if (is_array($result)) {
            foreach ($result as $res) {
                $attid_metaid_map[$res->post_id] = $res->meta_id;
            }
        }

        $map = [];
        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) {
                continue;
            }
            $att_id = (int) $att_ids_map[$thumbnail->meta_id];
            if (!isset($attid_metaid_map[$att_id])) {
                continue;
            }
            $map[$thumbnail->meta_id] = $attid_metaid_map[$att_id];
        }

        return $map;
    }

    /**
     * Updates attachment metadata for a storage bucket.
     *
     * @param string|int $bucket_id
     * @param array $thumbnails
     * @param array $meta_ids_map
     * @return array
     */
    public function speed_up_attachments_meta(string|int $bucket_id, array $thumbnails, array $meta_ids_map): array {
        $metaValues = [];
        foreach ($thumbnails as $thumbnail) {
            if (!isset($meta_ids_map[$thumbnail->meta_id])) {
                continue;
            }

            $su_url = $this->build_su_url($bucket_id, $thumbnail->storage_id);
            $metaValues[(int) $meta_ids_map[$thumbnail->meta_id]] = $su_url;
        }

        if (empty($metaValues)) {
            return [];
        }

        $metaIds = array_keys($metaValues);
        $placeholders = implode(',', array_fill(0, count($metaIds), '%d'));
        $sql = "
            SELECT meta_id, post_id, meta_key
            FROM {$this->postmeta}
            WHERE meta_id IN ({$placeholders})
        ";
        $query = $this->wpdb->prepare($sql, ...$metaIds);
        $rows = $this->wpdb->get_results($query);

        if (!is_array($rows)) {
            return [];
        }

        $allowedKeys = [
            '_wp_attached_file',
            '_wp_attachment_metadata',
        ];

        $updated = [];
        foreach ($rows as $row) {
            $metaId = (int) ($row->meta_id ?? 0);
            $postId = (int) ($row->post_id ?? 0);
            $metaKey = (string) ($row->meta_key ?? '');
            $metaValue = $metaValues[$metaId] ?? '';

            if ($metaId <= 0 || $postId <= 0 || $metaValue === '') {
                continue;
            }

            if (strpos($metaKey, 'fifu_') === 0) {
                continue;
            }

            if (!in_array($metaKey, $allowedKeys, true)) {
                continue;
            }

            // Avoid unsafe legacy updates by meta_id; attachment meta updates are scoped by (post_id, meta_key) and allowlisted.
            update_post_meta($postId, $metaKey, $metaValue);
            $updated[] = [
                'post_id' => $postId,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue,
            ];
        }

        return $updated;
    }

    /**
     * Synchronizes the category attachment as the legacy helper did.
     *
     * @param int $term_id
     * @return void
     */
    private function ctgr_update_fake_attach_id(int $term_id): void {
if (! class_exists('Fifu_Post_Attachment_Sync_Service')) {
return;
        }

        Fifu_Post_Attachment_Sync_Service::sync_category_attachment($term_id);
}

    /**
     * Insert custom fields for posts as part of speed-up operations.
     *
     * @param string $valuesSql
     * @return int
     */
    public function insert_post_custom_fields(string $valuesSql): int {
        if (!$valuesSql) {
            return 0;
        }

        $valuesSql = $this->filter_values_sql_for_db2_rows($valuesSql);
        if ($valuesSql === '') {
            return 0;
        }

        if ($this->values_sql_contains_db2_managed_key($valuesSql)) {
            // DB2-only: do not write DB2-managed FIFU keys to legacy meta.
            return 0;
        }

        $query = "INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) VALUES {$valuesSql}";
        return (int) $this->wpdb->query($query);
    }

    /**
     * Insert custom fields for terms as part of speed-up operations.
     *
     * @param string $valuesSql
     * @return int
     */
    public function insert_term_custom_fields(string $valuesSql): int {
        if (!$valuesSql) {
            return 0;
        }

        $valuesSql = $this->filter_values_sql_for_db2_rows($valuesSql);
        if ($valuesSql === '') {
            return 0;
        }

        if ($this->values_sql_contains_db2_managed_key($valuesSql)) {
            // DB2-only: do not write DB2-managed FIFU keys to legacy meta.
            return 0;
        }

        $query = "INSERT INTO {$this->termmeta} (term_id, meta_key, meta_value) VALUES {$valuesSql}";
        return (int) $this->wpdb->query($query);
    }

    /**
     * Return internal URLs associated with the given posts.
     *
     * @param array|int|string $postIds
     * @return array
     */
    public function get_internal_urls_for_posts(array|int|string $postIds): array {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        if (!$idsCsv) {
            return [];
        }

        $this->wpdb->query("SET SESSION group_concat_max_len = 1048576;");
        $query = "
            SELECT
                p.id AS att_id,
                p.guid AS url,
                (
                    SELECT alt.meta_value
                    FROM {$this->postmeta} alt
                    WHERE alt.post_id = p.id
                      AND alt.meta_key = '_wp_attachment_image_alt'
                    LIMIT 1
                ) AS alt
            FROM {$this->posts} p
            WHERE FIND_IN_SET(p.id,
                (
                    SELECT GROUP_CONCAT(pm.meta_value) AS att_ids
                    FROM {$this->postmeta} pm
                    WHERE pm.post_id IN ({$idsCsv})
                    AND meta_key IN ('bkp_thumbnail_id', 'bkp_product_image_gallery')
                )
            )
        ";

        $results = $this->wpdb->get_results($query);
        return $results ? $this->hydrate_internal_attachment_rows($results) : [];
    }

    /**
     * Return internal URLs associated with the given terms.
     *
     * @param array|int|string $termIds
     * @return array
     */
    public function get_internal_urls_for_terms(array|int|string $termIds): array {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($termIds);
        if (!$idsCsv) {
            return [];
        }

        $this->wpdb->query("SET SESSION group_concat_max_len = 1048576;");
        $query = "
            SELECT
                p.id AS att_id,
                p.guid AS url,
                (
                    SELECT alt.meta_value
                    FROM {$this->postmeta} alt
                    WHERE alt.post_id = p.id
                      AND alt.meta_key = '_wp_attachment_image_alt'
                    LIMIT 1
                ) AS alt
            FROM {$this->posts} p
            WHERE FIND_IN_SET(p.id,
                (
                    SELECT GROUP_CONCAT(tm.meta_value) AS att_ids
                    FROM {$this->termmeta} tm
                    WHERE tm.term_id IN ({$idsCsv})
                    AND meta_key = 'bkp_thumbnail_id'
                )
            )
        ";

        $results = $this->wpdb->get_results($query);
        return $results ? $this->hydrate_internal_attachment_rows($results) : [];
    }

    /**
     * Delete attachment IDs linked to the provided posts for speed-up cleanup.
     *
     * @param array|int|string $postIds
     * @return int
     */
    public function delete_post_attachment_ids(array|int|string $postIds): int {
        return $this->delete_post_attachment_ids_by_keys(
            $postIds,
            ['_thumbnail_id', '_product_image_gallery']
        );
    }

    /**
     * Delete only featured attachment IDs linked to the provided posts.
     *
     * @param array|int|string $postIds
     * @return int
     */
    public function delete_post_featured_attachment_ids(array|int|string $postIds): int {
        return $this->delete_post_attachment_ids_by_keys($postIds, ['_thumbnail_id']);
    }

    /**
     * Delete attachment IDs linked to the provided terms for speed-up cleanup.
     *
     * @param array|int|string $termIds
     * @return int
     */
    public function delete_term_attachment_ids(array|int|string $termIds): int {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($termIds);
        if (!$idsCsv) {
            return 0;
        }

        $termIdsArray = array_values(array_filter(array_map('intval', explode(',', $idsCsv))));
        if (!$termIdsArray) {
            return 0;
        }

        $allowedKeys = ['thumbnail_id'];
        $termPlaceholders = implode(',', array_fill(0, count($termIdsArray), '%d'));
        $keyPlaceholders = implode(',', array_fill(0, count($allowedKeys), '%s'));

        $query = "
            DELETE FROM {$this->termmeta}
            WHERE term_id IN ({$termPlaceholders})
            AND meta_key IN ({$keyPlaceholders})
            AND meta_key NOT LIKE 'fifu\\_%'
        ";

        $args = array_merge($termIdsArray, $allowedKeys);
        return (int) $this->wpdb->query($this->wpdb->prepare($query, ...$args));
    }

    /**
     * Delete attachment IDs linked to the provided posts for the given meta keys.
     *
     * @param array|int|string $postIds
     * @param string[]         $allowedKeys
     * @return int
     */
    private function delete_post_attachment_ids_by_keys(array|int|string $postIds, array $allowedKeys): int {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        if (!$idsCsv) {
            return 0;
        }

        $postIdsArray = array_values(array_filter(array_map('intval', explode(',', $idsCsv))));
        if (!$postIdsArray) {
            return 0;
        }

        $allowedKeys = array_values(array_filter(array_unique($allowedKeys), static fn(string $key): bool => $key !== ''));
        if (!$allowedKeys) {
            return 0;
        }

        $postPlaceholders = implode(',', array_fill(0, count($postIdsArray), '%d'));
        $keyPlaceholders = implode(',', array_fill(0, count($allowedKeys), '%s'));

        $query = "
            DELETE FROM {$this->postmeta}
            WHERE post_id IN ({$postPlaceholders})
            AND meta_key IN ({$keyPlaceholders})
            AND meta_key NOT LIKE 'fifu\\_%'
        ";

        $args = array_merge($postIdsArray, $allowedKeys);
        return (int) $this->wpdb->query($this->wpdb->prepare($query, ...$args));
    }

    /**
     * Backup attachment IDs linked to the provided posts before deletion.
     *
     * @param array|int|string $postIds
     * @return int
     */
    public function backup_post_attachment_ids(array|int|string $postIds): int {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        if (!$idsCsv) {
            return 0;
        }

        $postIdsArray = array_values(array_filter(array_map('intval', explode(',', $idsCsv))));
        if (!$postIdsArray) {
            return 0;
        }

        $allowedKeys = ['_thumbnail_id', '_product_image_gallery'];
        $allowedKeysFiltered = [];
        foreach ($allowedKeys as $metaKey) {
            if ($metaKey === '' || strncmp($metaKey, 'fifu_', 5) === 0) {
                continue;
            }
            if ($this->is_db2_managed_fifu_key($metaKey)) {
                continue;
            }
            $allowedKeysFiltered[] = $metaKey;
        }
        $allowedKeysFiltered = array_values(array_unique($allowedKeysFiltered));
        if (!$allowedKeysFiltered) {
            return 0;
        }

        $backupKeys = array_map(fn(string $metaKey): string => 'bkp' . $metaKey, $allowedKeysFiltered);
        $backupMap = array_combine($allowedKeysFiltered, $backupKeys);

        $postPlaceholders = implode(',', array_fill(0, count($postIdsArray), '%d'));
        $keyPlaceholders = implode(',', array_fill(0, count($allowedKeysFiltered), '%s'));
        $caseParts = [];
        foreach ($backupMap as $metaKey => $backupKey) {
            $caseParts[] = "WHEN '{$metaKey}' THEN '{$backupKey}'";
        }
        $caseExpression = implode("\n", $caseParts);
        $caseSql = "
            CASE pm.meta_key
                {$caseExpression}
                ELSE ''
            END
        ";

        $query = "
            INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value)
            (
                SELECT pm.post_id, CONCAT('bkp', pm.meta_key) AS meta_key, pm.meta_value
                FROM {$this->postmeta} pm
                WHERE pm.post_id IN ({$postPlaceholders})
                AND pm.meta_key IN ({$keyPlaceholders})
                AND pm.meta_key NOT LIKE 'fifu\\_%'
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$this->postmeta} pm2
                    WHERE pm2.post_id = pm.post_id
                    AND pm2.meta_key = {$caseSql}
                )
            )
        ";

        $args = array_merge($postIdsArray, $allowedKeysFiltered);
        return (int) $this->wpdb->query($this->wpdb->prepare($query, ...$args));
    }

    /**
     * Backup attachment IDs linked to the provided terms before deletion.
     *
     * @param array|int|string $termIds
     * @return int
     */
    public function backup_term_attachment_ids(array|int|string $termIds): int {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($termIds);
        if (!$idsCsv) {
            return 0;
        }

        $termIdsArray = array_values(array_filter(array_map('intval', explode(',', $idsCsv))));
        if (!$termIdsArray) {
            return 0;
        }

        $allowedKeys = ['thumbnail_id'];
        $allowedKeysFiltered = [];
        foreach ($allowedKeys as $metaKey) {
            if ($metaKey === '' || strncmp($metaKey, 'fifu_', 5) === 0) {
                continue;
            }
            if ($this->is_db2_managed_fifu_key($metaKey)) {
                continue;
            }
            $allowedKeysFiltered[] = $metaKey;
        }
        $allowedKeysFiltered = array_values(array_unique($allowedKeysFiltered));
        if (!$allowedKeysFiltered) {
            return 0;
        }

        $backupKeys = array_map(fn(string $metaKey): string => 'bkp_' . $metaKey, $allowedKeysFiltered);

        $termPlaceholders = implode(',', array_fill(0, count($termIdsArray), '%d'));
        $keyPlaceholders = implode(',', array_fill(0, count($allowedKeysFiltered), '%s'));
        $backupPlaceholders = implode(',', array_fill(0, count($backupKeys), '%s'));

        $query = "
            INSERT INTO {$this->termmeta} (term_id, meta_key, meta_value)
            (
                SELECT tm.term_id, CONCAT('bkp_', tm.meta_key) AS meta_key, tm.meta_value
                FROM {$this->termmeta} tm
                WHERE tm.term_id IN ({$termPlaceholders})
                AND tm.meta_key IN ({$keyPlaceholders})
                AND tm.meta_key NOT LIKE 'fifu\\_%'
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$this->termmeta} tm2
                    WHERE tm2.term_id = tm.term_id
                    AND tm2.meta_key IN ({$backupPlaceholders})
                )
            )
        ";

        $args = array_merge($termIdsArray, $allowedKeysFiltered, $backupKeys);
        return (int) $this->wpdb->query($this->wpdb->prepare($query, ...$args));
    }

    /**
     * Collect thumbnail IDs for the provided posts.
     *
     * @param array|int|string $postIds
     * @return int[]
     */
    public function collect_thumbnail_ids(array|int|string $postIds): array {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        if ($idsCsv === '0') {
            return [];
        }

        $results = $this->wpdb->get_col("
            SELECT meta_value
            FROM {$this->postmeta}
            WHERE post_id IN ({$idsCsv})
            AND meta_key = '_thumbnail_id'
        ");

        $ids = [];
        foreach ($results as $result) {
            $id = (int) $result;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Inserts FIFU attachments for posts as part of insert_postmeta2.
     *
     * @param string $valuesSql
     * @param array|int|string $postIds
     */
    public function insert_post_attachment_meta(string $valuesSql, array|int|string $postIds): void {
        if ($valuesSql === '') {
            return;
        }

        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        if ($idsCsv === '0') {
            return;
        }

        $this->wpdb->query("INSERT INTO {$this->posts} (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered) VALUES {$valuesSql}");

        $author = $this->get_author();
        $postIdsArray = array_values(array_filter(array_map('intval', explode(',', $idsCsv))));
        if ($postIdsArray) {
            $allowedKeys = ['_thumbnail_id'];
            $metaKey = '_thumbnail_id';
            if (
                in_array($metaKey, $allowedKeys, true)
                && strncmp($metaKey, 'fifu_', 5) !== 0
                && !$this->is_db2_managed_fifu_key($metaKey)
            ) {
                $postPlaceholders = implode(',', array_fill(0, count($postIdsArray), '%d'));
                $targetSql = "
                    SELECT p.post_parent AS post_id, MIN(p.ID) AS attachment_id
                      FROM {$this->posts} p
                     WHERE p.post_parent IN ({$postPlaceholders})
                       AND p.post_author = %d
                       AND p.post_parent > 0
                     GROUP BY p.post_parent
                ";
                $updateSql = $this->wpdb->prepare(
                    "
                    UPDATE {$this->postmeta} pm
                    INNER JOIN ({$targetSql}) target
                            ON target.post_id = pm.post_id
                       SET pm.meta_value = target.attachment_id
                     WHERE pm.meta_key = %s
                       AND CAST(pm.meta_value AS UNSIGNED) = 0
                    ",
                    ...array_merge($postIdsArray, [$author, $metaKey])
                );
                $this->wpdb->query($updateSql);

                $insertSql = $this->wpdb->prepare(
                    "
                    INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value)
                    SELECT target.post_id, %s, target.attachment_id
                      FROM ({$targetSql}) target
                     WHERE NOT EXISTS (
                           SELECT 1
                             FROM {$this->postmeta} pm
                            WHERE pm.post_id = target.post_id
                              AND pm.meta_key = %s
                     )
                    ",
                    ...array_merge([$metaKey], $postIdsArray, [$author, $metaKey])
                );
                $this->wpdb->query($insertSql);

                $deleteSql = "
                    DELETE pm_zero
                      FROM {$this->postmeta} pm_zero
                      INNER JOIN {$this->postmeta} pm_real
                              ON pm_real.post_id = pm_zero.post_id
                             AND pm_real.meta_key = %s
                             AND CAST(pm_real.meta_value AS UNSIGNED) > 0
                     WHERE pm_zero.post_id IN ({$postPlaceholders})
                       AND pm_zero.meta_key = %s
                       AND CAST(pm_zero.meta_value AS UNSIGNED) = 0
                ";
                $this->wpdb->query($this->wpdb->prepare($deleteSql, ...array_merge([$metaKey], $postIdsArray, [$metaKey])));
            }
        }

        if ($postIdsArray) {
            $allowedFileKeys = ['_wp_attached_file'];
            $fileMetaKey = '_wp_attached_file';
            if (
                in_array($fileMetaKey, $allowedFileKeys, true)
                && strncmp($fileMetaKey, 'fifu_', 5) !== 0
                && !$this->is_db2_managed_fifu_key($fileMetaKey)
            ) {
                $postPlaceholders = implode(',', array_fill(0, count($postIdsArray), '%d'));
                $fileQuery = "
                    INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                        SELECT p.id, %s, p.post_content_filtered
                        FROM {$this->posts} p
                        WHERE p.post_parent IN ({$postPlaceholders})
                        AND p.post_author = %d
                    )
                ";
                $sqlFileArgs = array_merge([$fileMetaKey], $postIdsArray, [$author]);
                $sqlFile = $this->wpdb->prepare($fileQuery, ...$sqlFileArgs);
                $this->wpdb->query($sqlFile);
            }
        }

        if ($postIdsArray) {
            $allowedAltKeys = ['_wp_attachment_image_alt'];
            $altMetaKey = '_wp_attachment_image_alt';
            if (
                in_array($altMetaKey, $allowedAltKeys, true)
                && strncmp($altMetaKey, 'fifu_', 5) !== 0
                && !$this->is_db2_managed_fifu_key($altMetaKey)
            ) {
                $postPlaceholders = implode(',', array_fill(0, count($postIdsArray), '%d'));
                $altQuery = "
                    INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                        SELECT p.id, %s, p.post_title
                        FROM {$this->posts} p
                        WHERE p.post_parent IN ({$postPlaceholders})
                        AND p.post_author = %d
                        AND p.post_title IS NOT NULL
                        AND p.post_title != ''
                    )
                ";
                $sqlAltArgs = array_merge([$altMetaKey], $postIdsArray, [$author]);
                $sqlAlt = $this->wpdb->prepare($altQuery, ...$sqlAltArgs);
                $this->wpdb->query($sqlAlt);
            }
        }

        foreach ($postIdsArray as $postId) {
            clean_post_cache($postId);
            wp_cache_delete($postId, 'post_meta');
        }
    }

    /**
     * Deletes FIFU-generated attachment metadata as part of delete_attmeta2.
     *
     * @param array|int|string $attachmentIds
     */
    public function delete_attachment_meta(array|int|string $attachmentIds): void {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($attachmentIds);
        if ($idsCsv === '0') {
            return;
        }

        $attachmentIdsArray = array_values(array_filter(array_map('intval', explode(',', $idsCsv))));
        $allowedDeleteKeys = ['_thumbnail_id'];
        $metaKey = '_thumbnail_id';
        if (
            $attachmentIdsArray
            && in_array($metaKey, $allowedDeleteKeys, true)
            && strncmp($metaKey, 'fifu_', 5) !== 0
            && !$this->is_db2_managed_fifu_key($metaKey)
        ) {
            $valuePlaceholders = implode(',', array_fill(0, count($attachmentIdsArray), '%d'));
            $query = "
                DELETE FROM {$this->postmeta}
                WHERE meta_key = %s
                AND meta_value IN (0, {$valuePlaceholders})
                AND meta_key NOT LIKE 'fifu\\_%'
            ";
            $args = array_merge([$metaKey], $attachmentIdsArray);
            $this->wpdb->query($this->wpdb->prepare($query, ...$args));
        }

        if (!empty($attachmentIdsArray)) {
            $termMetaKey = 'thumbnail_id';
            $termValuePlaceholders = implode(',', array_fill(0, count($attachmentIdsArray), '%d'));
            $termQuery = "
                DELETE FROM {$this->termmeta}
                WHERE meta_key = %s
                AND meta_value IN ({$termValuePlaceholders})
            ";
            $termArgs = array_merge([$termMetaKey], $attachmentIdsArray);
            $this->wpdb->query($this->wpdb->prepare($termQuery, ...$termArgs));
        }

        $author = $this->get_author();
        $sqlDeletePosts = $this->wpdb->prepare("
            DELETE FROM {$this->posts}
            WHERE id IN ({$idsCsv})
            AND post_author = %d
        ", $author);
        $this->wpdb->query($sqlDeletePosts);

        if (!empty($attachmentIdsArray)) {
            $allowedDeleteKeys = [
                '_wp_attached_file',
                '_wp_attachment_image_alt',
                '_wp_attachment_metadata',
            ];
            $metaKeysList = "'" . implode("','", $allowedDeleteKeys) . "'";
            $metaPlaceholders = implode(',', array_fill(0, count($attachmentIdsArray), '%d'));
            $sqlDeleteMeta = "
                DELETE FROM {$this->postmeta}
                WHERE meta_key IN ({$metaKeysList})
                AND post_id IN ({$metaPlaceholders})
                AND meta_key NOT LIKE 'fifu\\_%'
            ";
            $this->wpdb->query($this->wpdb->prepare($sqlDeleteMeta, ...$attachmentIdsArray));
        }
    }

    /**
     * Deletes term thumbnail metadata as part of delete_termmeta2.
     *
     * @param array|int|string $termIds
     */
    public function delete_term_thumbnail_meta(array|int|string $termIds): void {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($termIds);
        if ($idsCsv === '0') {
            return;
        }

        $termIdsArray = array_values(array_filter(array_map('intval', explode(',', $idsCsv))));
        $metaKey = 'thumbnail_id';
        if ($termIdsArray) {
            $placeholders = implode(',', array_fill(0, count($termIdsArray), '%d'));
            $query = "
                DELETE FROM {$this->termmeta}
                WHERE meta_key = %s
                AND term_id IN ({$placeholders})
                AND meta_key NOT LIKE 'fifu\\_%'
            ";
            $this->wpdb->query($this->wpdb->prepare($query, $metaKey, ...$termIdsArray));
        }

        $author = $this->get_author();
        $sqlDeletePostmeta = $this->wpdb->prepare("
            DELETE pm
            FROM {$this->postmeta} pm
            JOIN {$this->posts} p ON pm.post_id = p.id
            WHERE pm.meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
            AND p.post_parent IN ({$idsCsv})
            AND p.post_author = %d
            AND p.post_name LIKE 'fifu-category%'
        ", $author);
        $this->wpdb->query($sqlDeletePostmeta);
    }

    /**
     * Inserts term attachments for insert_termmeta2.
     *
     * @param string $valuesSql
     * @param array|int|string $termIds
     */
    public function insert_term_attachment_meta(string $valuesSql, array|int|string $termIds): void {
        if ($valuesSql === '') {
            return;
        }

        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($termIds);
        if ($idsCsv === '0') {
            return;
        }

        $this->wpdb->query("INSERT INTO {$this->posts} (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered, post_name) VALUES {$valuesSql}");

        $author = $this->get_author();
        $termIdsArray = array_values(array_filter(array_map('intval', explode(',', $idsCsv))));
        $allowedTermMetaKeys = ['thumbnail_id'];
        $termMetaKey = 'thumbnail_id';
        if (
            $termIdsArray
            && in_array($termMetaKey, $allowedTermMetaKeys, true)
            && strncmp($termMetaKey, 'fifu_', 5) !== 0
            && !$this->is_db2_managed_fifu_key($termMetaKey)
        ) {
            $termPlaceholders = implode(',', array_fill(0, count($termIdsArray), '%d'));
            $sqlTermThumbnailArgs = array_merge([$termMetaKey], $termIdsArray, [$author]);
            $sqlTermThumbnail = $this->wpdb->prepare("
                INSERT INTO {$this->termmeta} (term_id, meta_key, meta_value) (
                    SELECT p.post_parent, %s, p.id
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$termPlaceholders})
                    AND p.post_author = %d
                    AND p.post_name LIKE 'fifu-category%'
                )
            ", ...$sqlTermThumbnailArgs);
            $this->wpdb->query($sqlTermThumbnail);
        }

        if ($termIdsArray) {
            $termPlaceholders = implode(',', array_fill(0, count($termIdsArray), '%d'));
            $allowedPostMetaKeys = ['_wp_attached_file', '_wp_attachment_image_alt'];

            $fileMetaKey = '_wp_attached_file';
            if (
                in_array($fileMetaKey, $allowedPostMetaKeys, true)
                && strncmp($fileMetaKey, 'fifu_', 5) !== 0
                && !$this->is_db2_managed_fifu_key($fileMetaKey)
            ) {
                $sqlTermFileArgs = array_merge([$fileMetaKey], $termIdsArray, [$author]);
                $sqlTermFile = $this->wpdb->prepare("
                    INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                        SELECT p.id, %s, p.post_content_filtered
                        FROM {$this->posts} p
                        WHERE p.post_parent IN ({$termPlaceholders})
                        AND p.post_author = %d
                        AND p.post_name LIKE 'fifu-category%'
                    )
                ", ...$sqlTermFileArgs);
                $this->wpdb->query($sqlTermFile);
            }

            $altMetaKey = '_wp_attachment_image_alt';
            if (
                in_array($altMetaKey, $allowedPostMetaKeys, true)
                && strncmp($altMetaKey, 'fifu_', 5) !== 0
                && !$this->is_db2_managed_fifu_key($altMetaKey)
            ) {
                $sqlTermAltArgs = array_merge([$altMetaKey], $termIdsArray, [$author]);
                $sqlTermAlt = $this->wpdb->prepare("
                    INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                        SELECT p.id, %s, p.post_title
                        FROM {$this->posts} p
                        WHERE p.post_parent IN ({$termPlaceholders})
                        AND p.post_author = %d
                        AND p.post_title IS NOT NULL
                        AND p.post_title != ''
                        AND p.post_name LIKE 'fifu-category%'
                    )
                ", ...$sqlTermAltArgs);
                $this->wpdb->query($sqlTermAlt);
            }
        }
    }

    /**
     * @param string $meta_key
     * @return bool
     */
    private function is_db2_managed_legacy_key(string $meta_key): bool {
        return in_array($meta_key, self::DB2_MANAGED_LEGACY_KEYS, true);
    }

    /**
     * @param string $meta_key
     * @return bool
     */
    private function is_db2_managed_fifu_key(string $meta_key): bool {
        return $meta_key === 'fifu_image_url';
    }

    private function get_author(): int {
        return (int) Fifu_Options_Utils::get_author();
    }

    /**
     * @param string $valuesSql
     * @return bool
     */
    private function values_sql_contains_db2_managed_key(string $valuesSql): bool {
        if ($valuesSql === '') {
            return false;
        }

        if (!preg_match_all('/\(\s*[^,]+,\s*([\'"])([^\'"]+)\1\s*,/i', $valuesSql, $matches)) {
            return false;
        }

        foreach ($matches[2] as $meta_key) {
            if ($this->is_db2_managed_fifu_key($meta_key) || $this->is_db2_managed_legacy_key($meta_key)) {
                return true;
            }
        }

        return false;
    }

    private function filter_values_sql_for_db2_rows(string $valuesSql): string {
        if ($valuesSql === '') {
            return '';
        }

        $rows = $this->split_values_sql_rows($valuesSql);
        if ($rows === null) {
            return $valuesSql;
        }

        $allowedRows = [];
        foreach ($rows as $row) {
            if ($this->row_contains_db2_managed_key($row)) {
                continue;
            }
            $allowedRows[] = $row;
        }

        return implode(', ', $allowedRows);
    }

    private function row_contains_db2_managed_key(string $row): bool {
        if (!preg_match('/\(\s*[^,]+,\s*([\'"])([^\'"]+)\1\s*,/i', $row, $match)) {
            return false;
        }

        $metaKey = (string) $match[2];
        return $this->is_db2_managed_fifu_key($metaKey) || $this->is_db2_managed_legacy_key($metaKey);
    }

    private function split_values_sql_rows(string $valuesSql): ?array {
        $rows = [];
        $length = strlen($valuesSql);
        $depth = 0;
        $inQuote = null;
        $escapeNext = false;
        $start = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $valuesSql[$i];

            if ($escapeNext) {
                $escapeNext = false;
                continue;
            }

            if ($char === '\\' && $inQuote !== null) {
                $escapeNext = true;
                continue;
            }

            if ($inQuote !== null) {
                if ($char === $inQuote) {
                    $inQuote = null;
                }
                continue;
            }

            if ($char === '\'' || $char === '"') {
                $inQuote = $char;
                continue;
            }

            if ($char === '(') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    $rows[] = substr($valuesSql, $start, $i - $start + 1);
                    $start = null;
                } elseif ($depth < 0) {
                    return null;
                }
            }
        }

        if ($depth !== 0 || $inQuote !== null) {
            return null;
        }

        return $rows;
    }

}
