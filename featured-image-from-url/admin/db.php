<?php

class FifuDb {

    private $wpdb;
    private $posts;
    private $options;
    private $postmeta;
    private $terms;
    private $termmeta;
    private $term_taxonomy;
    private $term_relationships;
    private $fifu_meta_in;
    private $fifu_meta_out;
    private $fifu_invalid_media_su;
    private $query;
    private $author;
    private $types;

    function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->posts = $wpdb->prefix . 'posts';
        $this->options = $wpdb->prefix . 'options';
        $this->postmeta = $wpdb->prefix . 'postmeta';
        $this->terms = $wpdb->prefix . 'terms';
        $this->termmeta = $wpdb->prefix . 'termmeta';
        $this->term_taxonomy = $wpdb->prefix . 'term_taxonomy';
        $this->term_relationships = $wpdb->prefix . 'term_relationships';
        $this->fifu_meta_in = $wpdb->prefix . 'fifu_meta_in';
        $this->fifu_meta_out = $wpdb->prefix . 'fifu_meta_out';
        $this->fifu_invalid_media_su = $wpdb->prefix . 'fifu_invalid_media_su';
        $this->author = fifu_get_author();
        $this->types = $this->get_types();
    }

    function get_types(): string {
        $raw = (array) fifu_get_post_types();

        // Sanitize and validate against registered post types
        $registered = get_post_types([], 'names'); // array of valid names
        $safe = [];
        foreach ($raw as $pt) {
            $pt = sanitize_key($pt);
            if ($pt !== '' && isset($registered[$pt])) {
                $safe[] = $pt;
            }
        }
        // Deduplicate while preserving order
        $safe = array_values(array_unique($safe));
        return implode("','", $safe);
    }

    function sanitize_ids_csv($ids, bool $allow_zero = false): string {
        // Normalize $ids to an array
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
                if ($id === '' || !ctype_digit($id)) { // digits only
                    continue;
                }
                $n = (int) $id; // safe after ctype_digit
            } else {
                continue;
            }

            if ($n > 0 || ($allow_zero && $n === 0)) {
                $set[$n] = true; // dedupe
            }
        }

        if (!$set) {
            return '0'; // ensures valid "IN (0)" => no matches
        }

        return implode(',', array_keys($set));
    }

    // Sanitize a list of post types (array or CSV string) for safe IN (...) usage
    function sanitize_post_types_list($post_types) {
        // Normalize input to array
        if (is_string($post_types)) {
            $post_types = explode(',', str_replace(['"', "'"], '', $post_types));
        } elseif (!is_array($post_types)) {
            $post_types = [];
        }

        // Whitelist of registered post types
        $registered = array_flip(get_post_types([], 'names'));

        // Sanitize + dedupe
        $set = [];
        foreach ($post_types as $pt) {
            $pt = sanitize_key(trim((string) $pt)); // [a-z0-9_-], lowercased
            if ($pt === '' || !isset($registered[$pt])) {
                continue;
            }
            $set[$pt] = true;
        }

        if (!$set) {
            // If used in a class context that defines $this->types, keep compatibility
            if (isset($this) && isset($this->types) && is_string($this->types) && $this->types !== '') {
                return $this->types;
            }
            // Safe default: match nothing
            return "''";
        }

        $items = array_keys($set);
        // sanitize_key already guarantees safe charset; quoting is enough for IN (...)
        return "'" . implode("','", $items) . "'";
    }

    function build_in_from_option_csv(string $base_key, string $option_name): array {
        $field = (string) get_option($option_name);

        $keys = [$base_key];
        if ($field !== '') {
            foreach (explode(',', $field) as $k) {
                $k = trim($k);
                if ($k !== '')
                    $keys[] = $k;
            }
        }
        $keys = array_values(array_unique($keys));

        $in = implode(',', array_fill(0, count($keys), '%s')); // e.g. ['fifu_isbn','custom1'] -> IN ('fifu_isbn','custom1')
        return [$in, $keys];
    }

    /* attachment metadata */

    // delete 1 _wp_attached_file or _wp_attachment_image_alt for each attachment
    function delete_attachment_meta($ids, $is_ctgr) {
        $ctgr_sql = $is_ctgr ? "AND p.post_name LIKE 'fifu-category%'" : "";
        $ids_csv = $this->sanitize_ids_csv($ids);
        $author = $this->author;
        $sql = "
            DELETE pm
            FROM {$this->postmeta} pm JOIN {$this->posts} p ON pm.post_id = p.id
            WHERE pm.meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
            AND p.post_parent IN ({$ids_csv})
            AND p.post_author = %d 
            {$ctgr_sql}
        ";
        $this->wpdb->query($this->wpdb->prepare($sql, $author));
    }

    function insert_thumbnail_id_ctgr($ids, $is_ctgr) {
        $ctgr_sql = $is_ctgr ? "AND p.post_name LIKE 'fifu-category%'" : "";

        $this->wpdb->query("
            INSERT INTO {$this->termmeta} (term_id, meta_key, meta_value) (
                SELECT p.post_parent, 'thumbnail_id', p.id 
                FROM {$this->posts} p LEFT OUTER JOIN {$this->termmeta} b ON p.post_parent = b.term_id AND meta_key = 'thumbnail_id'
                WHERE b.term_id IS NULL
                AND p.post_parent IN ({$ids}) 
                AND p.post_author = {$this->author} 
                {$ctgr_sql}
            )
        ");
    }

    // has attachment created by FIFU
    function is_fifu_attachment($att_id) {
        $sql = $this->wpdb->prepare(
                "SELECT 1 FROM {$this->posts} WHERE id = %d AND post_author = %d",
                (int) $att_id,
                $this->author
        );
        return $this->wpdb->get_row($sql) != null;
    }

    // get att_id by post and url
    function get_att_id($post_parent, $url, $is_ctgr) {
        $ctgr_sql = $is_ctgr ? "AND p.post_name LIKE 'fifu-category%'" : "";
        $sql = $this->wpdb->prepare(
                "SELECT pm.post_id
             FROM {$this->postmeta} pm
             WHERE pm.meta_key = '_wp_attached_file'
               AND pm.meta_value = %s
               AND pm.post_id IN (
                   SELECT p.id
                   FROM {$this->posts} p 
                   WHERE p.post_parent = %d
                     AND post_author = %d {$ctgr_sql}
               )
             LIMIT 1",
                $url,
                (int) $post_parent,
                $this->author
        );
        $row = $this->wpdb->get_row($sql);
        return $row ? (int) $row->post_id : null;
    }

    function get_count_wp_postmeta() {
        return $this->wpdb->get_results("
            SELECT COUNT(1) AS amount
            FROM {$this->postmeta}
        ");
    }

    function get_count_wp_posts() {
        return $this->wpdb->get_results("
            SELECT COUNT(1) AS amount
            FROM {$this->posts}
        ");
    }

    function get_count_wp_posts_fifu() {
        $sql = $this->wpdb->prepare(
                "SELECT COUNT(1) AS amount FROM {$this->posts} WHERE post_author = %d",
                $this->author
        );
        return $this->wpdb->get_results($sql);
    }

    function get_count_wp_postmeta_fifu() {
        $sql = $this->wpdb->prepare(
                "SELECT COUNT(1) AS amount
             FROM {$this->postmeta}
             WHERE meta_key = '_wp_attached_file'
               AND EXISTS (
                   SELECT 1 FROM {$this->posts}
                   WHERE id = post_id AND post_author = %d
               )",
                $this->author
        );
        return $this->wpdb->get_results($sql);
    }

    function tables_created() {
        return $this->wpdb->get_var("SHOW TABLES LIKE '{$this->fifu_meta_in}'");
    }

    function debug_slug($slug) {
        $sql = $this->wpdb->prepare(
                "SELECT ID, post_author, post_content, post_title, post_status, post_parent, post_content_filtered, guid, post_type 
             FROM {$this->posts} 
             WHERE post_name = %s
               AND post_status <> 'private'
               AND (post_password = '' OR post_password IS NULL)",
                $slug
        );
        return $this->wpdb->get_results($sql);
    }

    function debug_postmeta($post_id) {
        $sql = $this->wpdb->prepare("
            SELECT pm.meta_key, pm.meta_value
            FROM {$this->postmeta} pm
            INNER JOIN {$this->posts} p ON p.ID = pm.post_id
            WHERE pm.post_id = %d 
              AND p.post_status <> 'private'
              AND (p.post_password = '' OR p.post_password IS NULL)
              AND (
                  pm.meta_key LIKE 'fifu%'
                  OR pm.meta_key IN ('_thumbnail_id', '_wp_attached_file', '_wp_attachment_image_alt', '_product_image_gallery', '_wc_additional_variation_images')
              )"
                , $post_id);
        return $this->wpdb->get_results($sql);
    }

    function debug_posts($id) {
        $sql = $this->wpdb->prepare("
            SELECT post_author, post_content, post_title, post_status, post_parent, post_content_filtered, guid, post_type
            FROM {$this->posts} 
            WHERE id = %d
            AND post_status <> 'private'
            AND (post_password = '' OR post_password IS NULL)"
                , $id);
        return $this->wpdb->get_results($sql);
    }

    function debug_metain() {
        // No placeholders here; do not call prepare()
        return $this->wpdb->get_results("SELECT * FROM {$this->fifu_meta_in}");
    }

    function debug_metaout() {
        // No placeholders here; do not call prepare()
        return $this->wpdb->get_results("SELECT * FROM {$this->fifu_meta_out}");
    }

    // count images without dimensions
    function get_count_posts_without_dimensions() {
        $author = $this->author;
        $sql = $this->wpdb->prepare("
            SELECT COUNT(1) AS amount
            FROM {$this->posts} p
            WHERE NOT EXISTS (
                SELECT 1 
                FROM {$this->postmeta} b
                WHERE p.id = b.post_id AND meta_key = '_wp_attachment_metadata'
            )
            AND p.post_author = %d
        ", $author);
        return $this->wpdb->get_results($sql);
    }

    // count urls with metadata
    function get_count_urls_with_metadata() {
        $author = $this->author;
        $sql = $this->wpdb->prepare("
            SELECT COUNT(1) AS amount
            FROM {$this->posts} p
            WHERE p.post_author = %d
        ", $author);
        return $this->wpdb->get_results($sql);
    }

    // Count URLs across postmeta and termmeta (no UNION; no meta_value filters; no tm '%list%' filter)
    function get_count_urls() {
        $sql = "
            SELECT
                (
                    SELECT COUNT(*)
                    FROM {$this->postmeta} AS pm
                    WHERE pm.meta_key LIKE 'fifu!_%' ESCAPE '!'
                    AND pm.meta_key LIKE '%url%'
                    AND pm.meta_key NOT LIKE '%list%'
                ) +
                (
                    SELECT COUNT(*)
                    FROM {$this->termmeta} AS tm
                    WHERE tm.meta_key LIKE 'fifu!_%' ESCAPE '!'
                    AND tm.meta_key LIKE '%url%'
                ) AS amount
        ";
        return (int) $this->wpdb->get_var($sql);
    }

    function get_count_metadata_operations() {
        return $this->wpdb->get_var("
            SELECT 
                COALESCE(
                    (
                        SELECT SUM(
                            CASE 
                                WHEN post_ids IS NULL OR post_ids = '' THEN 0
                                ELSE CHAR_LENGTH(post_ids) - CHAR_LENGTH(REPLACE(post_ids, ',', '')) + 1
                            END
                        ) 
                        FROM {$this->fifu_meta_in}
                    ), 0
                ) +
                COALESCE(
                    (
                        SELECT SUM(
                            CASE 
                                WHEN post_ids IS NULL OR post_ids = '' THEN 0
                                ELSE CHAR_LENGTH(post_ids) - CHAR_LENGTH(REPLACE(post_ids, ',', '')) + 1
                            END
                        ) 
                        FROM {$this->fifu_meta_out}
                    ), 0
                ) AS total_amount
        ");
    }

    // get last (images/videos/sliders)
    function get_last($meta_key) {
        $sql = $this->wpdb->prepare(
                "SELECT p.id, pm.meta_value
            FROM {$this->posts} p
            INNER JOIN {$this->postmeta} pm ON p.id = pm.post_id
            WHERE pm.meta_key = %s
            ORDER BY p.post_date DESC
            LIMIT 3",
                $meta_key
        );
        return $this->wpdb->get_results($sql);
    }

    function get_last_image() {
        return $this->wpdb->get_results("
            SELECT pm.meta_value
            FROM {$this->postmeta} pm 
            WHERE pm.meta_key = 'fifu_image_url'
            ORDER BY pm.meta_id DESC
            LIMIT 1
        ");
    }

    // get child posts (excluding the featured image) for a given post
    function get_attachments_without_post($post_id) {
        $sql = $this->wpdb->prepare(
                "SELECT GROUP_CONCAT(p.ID) AS ids
            FROM {$this->posts} p
            WHERE p.post_parent = %d
            AND p.post_author = %d
            AND p.post_name NOT LIKE %s
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->postmeta} pm2
                WHERE pm2.post_id = p.post_parent
                    AND pm2.meta_key = '_thumbnail_id'
                    AND pm2.meta_value = p.ID
            )",
                (int) $post_id,
                (int) $this->author,
                'fifu-category%' // no need for %% since it's a %s value
        );

        // One row expected; return CSV string or null
        $ids_csv = $this->wpdb->get_var($sql);
        return $ids_csv ?: null;
    }

    function get_ctgr_attachments_without_post($term_id) {
        $sql = $this->wpdb->prepare(
                "SELECT GROUP_CONCAT(p.ID) AS ids
            FROM {$this->posts} p
            WHERE p.post_parent = %d
            AND p.post_author = %d
            AND p.post_name LIKE %s
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->termmeta} tm
                WHERE tm.term_id = p.post_parent
                    AND tm.meta_key = 'thumbnail_id'
                    AND tm.meta_value = p.ID
            )",
                (int) $term_id,
                (int) $this->author,
                'fifu-category%' // pass pattern as a value; no %% needed
        );

        $ids_csv = $this->wpdb->get_var($sql);
        return $ids_csv ?: null;
    }

    function get_posts_without_featured_image($post_types) {
        $safe = $this->sanitize_post_types_list($post_types);
        return $this->wpdb->get_results("
            SELECT id, post_title
            FROM {$this->posts} 
            WHERE post_type IN ($safe)
            AND post_status = 'publish'
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->postmeta} 
                WHERE post_id = id
                AND meta_key IN ('_thumbnail_id', 'fifu_image_url')
            )
            ORDER BY id DESC
        ");
    }

    function get_number_of_posts() {
        return $this->wpdb->get_row("
            SELECT count(1) AS n
            FROM {$this->posts} 
            WHERE post_type IN ('$this->types')
            AND post_status = 'publish'"
                )->n;
    }

    function get_featured_and_gallery_ids($post_id) {
        $sql = $this->wpdb->prepare(
                "SELECT GROUP_CONCAT(meta_value SEPARATOR ',') as 'ids'
            FROM {$this->postmeta}
            WHERE post_id = %d
              AND meta_key IN ('_thumbnail_id')",
                (int) $post_id
        );
        return $this->wpdb->get_results($sql);
    }

    function insert_default_thumbnail_id($value) {
        $this->wpdb->query("
            INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value)
            VALUES {$value}
        ");
    }

    // clean metadata

    function delete_attachments($ids) {
        $ids_csv = $this->sanitize_ids_csv($ids);
        $sql = $this->wpdb->prepare(
                "DELETE FROM {$this->posts} WHERE id IN ({$ids_csv}) AND post_author = %d",
                $this->author
        );
        $this->wpdb->query($sql);
    }

    function delete_attachment_meta_url_and_alt($ids) {
        $ids_csv = $this->sanitize_ids_csv($ids);
        $sql = $this->wpdb->prepare(
                "DELETE FROM {$this->postmeta}
            WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
              AND post_id IN ({$ids_csv})
              AND EXISTS (SELECT 1 FROM {$this->posts} WHERE id = post_id AND post_author = %d)",
                $this->author
        );
        $this->wpdb->query($sql);
    }

    function delete_empty_urls_category() {
        $this->wpdb->query("
            DELETE FROM {$this->termmeta} 
            WHERE meta_key = 'fifu_image_url'
            AND (
                meta_value = ''
                OR meta_value is NULL
            )
        ");
    }

    function delete_empty_urls() {
        $this->wpdb->query("
            DELETE FROM {$this->postmeta} 
            WHERE meta_key = 'fifu_image_url'
            AND (
                meta_value = ''
                OR meta_value is NULL
            )
        ");
    }

    /* wp_options */

    function select_option_prefix($prefix) {
        if ($prefix === '')
            return []; // avoid SELECT all
        $like = $this->wpdb->esc_like($prefix) . '%'; // escape LIKE wildcards safely
        $sql = $this->wpdb->prepare(
                "SELECT option_name, option_value
            FROM {$this->options}
            WHERE option_name LIKE %s
            ORDER BY option_name",
                $like
        );
        return $this->wpdb->get_results($sql);
    }

    function delete_option_prefix($prefix) {
        if ($prefix === '') {
            return 0; // safety: avoid deleting everything
        }
        $like = $this->wpdb->esc_like($prefix) . '%'; // escape % and _
        $sql_select = $this->wpdb->prepare(
                "SELECT option_name FROM {$this->options} WHERE option_name LIKE %s",
                $like
        );
        $options_to_delete = $this->wpdb->get_col($sql_select);
        $sql_delete = $this->wpdb->prepare(
                "DELETE FROM {$this->options} WHERE option_name LIKE %s",
                $like
        );
        $deleted_count = (int) $this->wpdb->query($sql_delete);
        // Clear cache for deleted options
        foreach ($options_to_delete as $option_name) {
            wp_cache_delete($option_name, 'options');
        }
        return $deleted_count;
    }

    /* speed up */

    function get_all_urls($page, $type, $keyword) {
        $page = max(0, (int) $page); // Ensure page is non-negative
        $start = $page * 1000;

        // Posts filter
        $filter_posts = '';
        if ($keyword) {
            $like = '%' . $this->wpdb->esc_like($keyword) . '%';
            if ($type == 'title')
                $filter_posts = $this->wpdb->prepare('AND p.post_title LIKE %s', $like);
            elseif ($type == 'url')
                $filter_posts = $this->wpdb->prepare('AND pm.meta_value LIKE %s', $like);
        }

        $sql = "
            (
                SELECT pm.meta_id, pm.post_id, pm.meta_value AS url, pm.meta_key, p.post_name, p.post_title, p.post_date, false AS category, null AS video_url
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id {$filter_posts}
                WHERE pm.meta_key = 'fifu_image_url'
                AND pm.meta_value NOT LIKE '%https://cdn.fifu.app/%'
                AND pm.meta_value NOT LIKE 'http://localhost/%'
                AND p.post_status <> 'trash'
            )
        ";
        if (class_exists('WooCommerce')) {
            // Terms filter
            $filter_terms = '';
            if ($keyword) {
                $like = '%' . $this->wpdb->esc_like($keyword) . '%';
                if ($type == 'title')
                    $filter_terms = $this->wpdb->prepare('AND t.name LIKE %s', $like);
                elseif ($type == 'url')
                    $filter_terms = $this->wpdb->prepare('AND tm.meta_value LIKE %s', $like);
            }
            $sql .= " 
                UNION
                (
                    SELECT tm.meta_id, tm.term_id AS post_id, tm.meta_value AS url, tm.meta_key, null AS post_name, t.name AS post_title, null AS post_date, true AS category, null AS video_url
                    FROM {$this->termmeta} tm
                    INNER JOIN {$this->terms} t ON tm.term_id = t.term_id {$filter_terms}
                    WHERE tm.meta_key IN ('fifu_image_url')
                    AND tm.meta_value NOT LIKE '%https://cdn.fifu.app/%'
                    AND tm.meta_value NOT LIKE 'http://localhost/%'
                )
            ";
        }
        $sql .= " 
            ORDER BY post_id DESC
            LIMIT {$start},1000
        ";
        return $this->wpdb->get_results($sql);
    }

    function get_all_hex_ids() {
        $sql = "
            (
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, '/', -1), '-', 1) AS hex_id
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id
                WHERE (pm.meta_key LIKE 'fifu_%image_url%')
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
                    WHERE tm.meta_key IN ('fifu_image_url')
                    AND tm.meta_value LIKE '%https://cdn.fifu.app/%'
                )
            ";
        }
        $sql .= " 
            ORDER BY hex_id DESC
        ";
        return $this->wpdb->get_col($sql);
    }

    function get_posts_with_internal_featured_image($page, $type, $keyword) {
        $start = max(0, (int) $page) * 1000;

        $filter = "";
        if ($keyword) {
            if ($type == 'title') {
                $like = '%' . $this->wpdb->esc_like($keyword) . '%';
                $filter = $this->wpdb->prepare('AND p.post_title LIKE %s', $like);
            } elseif ($type == 'postid') {
                $filter = $this->wpdb->prepare('AND pm.post_id = %d', (int) $keyword);
            }
        }

        // Prepare author filter fragments once to avoid preparing the whole query later
        $author_clause_posts = $this->wpdb->prepare('AND att.post_author <> %d', $this->author);
        $author_clause_terms = $author_clause_posts;

        $sql = "
            (
                SELECT 
                    pm.post_id, 
                    att.guid AS url, 
                    p.post_name, 
                    p.post_title, 
                    p.post_date, 
                    att.id AS thumbnail_id,
                    (SELECT meta_value FROM {$this->postmeta} pm2 WHERE pm2.post_id = pm.post_id AND pm2.meta_key = '_product_image_gallery') AS gallery_ids,
                    false AS category
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id {$filter} AND p.post_title <> ''
                INNER JOIN {$this->posts} att ON (
                    pm.meta_key = '_thumbnail_id'
                    AND pm.meta_value = att.id
                    {$author_clause_posts}
                )
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM {$this->postmeta}
                    WHERE post_id = pm.post_id
                    AND (meta_key LIKE 'fifu_%image_url%' OR meta_key IN ('bkp_thumbnail_id', 'bkp_product_image_gallery'))
                )
                AND (
                    SELECT COUNT(1)
                    FROM {$this->postmeta}
                    WHERE post_id = pm.post_id
                    AND meta_key = '_product_image_gallery'
                ) <= 1
                AND p.post_status <> 'trash'
            )
        ";
        if (class_exists('WooCommerce')) {
            $filter = "";
            if ($keyword) {
                if ($type == 'title') {
                    $like = '%' . $this->wpdb->esc_like($keyword) . '%';
                    $filter = $this->wpdb->prepare('AND t.name LIKE %s', $like);
                } elseif ($type == 'postid') {
                    $filter = $this->wpdb->prepare('AND tm.term_id = %d', (int) $keyword);
                }
            }
            $sql .= " 
                UNION 
                (
                    SELECT
                        tm.term_id AS post_id, 
                        att.guid AS url, 
                        null AS post_name, 
                        t.name AS post_title, 
                        null AS post_date, 
                        att.id AS thumbnail_id,
                        null AS gallery_ids,
                        true AS category
                    FROM {$this->termmeta} tm
                    INNER JOIN {$this->terms} t ON tm.term_id = t.term_id {$filter}
                    INNER JOIN {$this->posts} att ON (
                        tm.meta_key = 'thumbnail_id'
                        AND tm.meta_value = att.id
                        {$author_clause_terms}
                    )
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM {$this->termmeta}
                        WHERE term_id = tm.term_id
                        AND (meta_key = 'fifu_image_url' OR meta_key = 'bkp_thumbnail_id')
                    )
                )
            ";
        }
        $sql .= " 
            ORDER BY post_id DESC
            LIMIT {$start},1000
        ";
        return $this->wpdb->get_results($sql);
    }

    function get_posts_su($storage_ids) {
        if (!empty($storage_ids)) {
            // normalize and drop empties
            $ids = array_values(array_filter(array_map('strval', (array) $storage_ids), static fn($v) => $v !== ''));
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '%s'));
                $filter_post_image = $this->wpdb->prepare(
                        "AND SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, '/', 5), '/', -1) IN ($in)", $ids
                );
                $filter_term_image = $this->wpdb->prepare(
                        "AND SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, '/', 5), '/', -1) IN ($in)", $ids
                );
            } else {
                $filter_post_image = $filter_term_image = "";
            }
        } else {
            $filter_post_image = $filter_term_image = "";
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
                WHERE pm.meta_key LIKE 'fifu_%image_url%'
                AND pm.meta_value LIKE 'https://cdn.fifu.app/%'
                {$filter_post_image}
            )
        ";
        if (class_exists('WooCommerce')) {
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
            ";
        }

        return $this->wpdb->get_results($sql);
    }

    /* speed up (add) */

    function add_urls_su($bucket_id, $thumbnails) {
        // custom field
        $this->speed_up_custom_fields($bucket_id, $thumbnails, false);

        // two groups
        $featured_list = array();
        foreach ($thumbnails as $thumbnail) {
            if ($thumbnail->meta_key == 'fifu_image_url')
                array_push($featured_list, $thumbnail);
        }

        // featured group
        if (count($featured_list) > 0) {
            $att_ids_map = $this->get_thumbnail_ids($featured_list, false);
            if (count($att_ids_map) > 0) {
                $this->speed_up_attachments($bucket_id, $featured_list, $att_ids_map);
                $meta_ids_map = $this->get_thumbnail_meta_ids($featured_list, $att_ids_map);
                if (count($meta_ids_map) > 0)
                    $this->speed_up_attachments_meta($bucket_id, $featured_list, $meta_ids_map);
            }
        }
    }

    function ctgr_add_urls_su($bucket_id, $thumbnails) {
        // custom field
        $this->speed_up_custom_fields($bucket_id, $thumbnails, true);

        $featured_list = array();
        foreach ($thumbnails as $thumbnail)
            array_push($featured_list, $thumbnail);

        // featured group
        if (count($featured_list) > 0) {
            $att_ids_map = $this->get_thumbnail_ids($featured_list, true);
            if (count($att_ids_map) > 0) {
                $this->speed_up_attachments($bucket_id, $featured_list, $att_ids_map);
                $meta_ids_map = $this->get_thumbnail_meta_ids($featured_list, $att_ids_map);
                if (count($meta_ids_map) > 0)
                    $this->speed_up_attachments_meta($bucket_id, $featured_list, $meta_ids_map);
            }
        }
    }

    function get_su_url($bucket_id, $storage_id) {
        return 'https://cdn.fifu.app/' . $bucket_id . '/' . $storage_id;
    }

    function speed_up_custom_fields($bucket_id, $thumbnails, $is_ctgr) {
        $table = $is_ctgr ? $this->termmeta : $this->postmeta;

        $values = [];
        $args = [];

        foreach ($thumbnails as $thumbnail) {
            $su_url = $this->get_su_url($bucket_id, $thumbnail->storage_id);

            $values[] = '(%d,%s)';
            $args[] = (int) $thumbnail->meta_id;
            $args[] = $su_url;
        }

        if (!$values)
            return 0;

        $query = "
            INSERT INTO {$table} (meta_id, meta_value)
            VALUES " . implode(', ', $values) . "
            ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)
        ";

        return $this->wpdb->query($this->wpdb->prepare($query, $args));
    }

    function get_thumbnail_ids($thumbnails, $is_ctgr) {
        // join post_ids (sanitized)
        $ids_list = array();
        foreach ($thumbnails as $thumbnail)
            $ids_list[] = (int) $thumbnail->post_id;
        $ids = $this->sanitize_ids_csv($ids_list);

        // get featured ids
        if ($is_ctgr) {
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

        // map featured ids
        $featured_map = array();
        foreach ($result as $res)
            $featured_map[$res->post_id] = $res->att_id;

        // map thumbnails
        $map = array();
        foreach ($thumbnails as $thumbnail) {
            if (isset($featured_map[$thumbnail->post_id])) {
                $att_id = $featured_map[$thumbnail->post_id];
                $map[$thumbnail->meta_id] = $att_id;
            }
        }
        // meta_id -> att_id
        return $map;
    }

    function speed_up_attachments($bucket_id, $thumbnails, $att_ids_map) {
        $count = 0;
        $query = "
            INSERT INTO {$this->posts} (id, post_content_filtered) VALUES ";
        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;

            $su_url = $this->get_su_url($bucket_id, $thumbnail->storage_id);

            if ($count++ != 0)
                $query .= ", ";
            $query .= $this->wpdb->prepare("(%d, %s)", $att_ids_map[$thumbnail->meta_id], $su_url) . " ";
        }
        $query .= "ON DUPLICATE KEY UPDATE post_content_filtered=VALUES(post_content_filtered)";
        return $this->wpdb->get_results($query);
    }

    function get_thumbnail_meta_ids($thumbnails, $att_ids_map) {
        // Collect distinct numeric attachment post_ids
        $ids_arr = array();
        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;
            $ids_arr[] = (int) $att_ids_map[$thumbnail->meta_id];
        }
        $ids_arr = array_values(array_unique(array_filter($ids_arr, function ($v) {
                            return $v > 0;
                        })));

        // No IDs -> nothing to query
        if (empty($ids_arr)) {
            return array();
        }

        // Build prepared IN(...) and run the safe query
        $placeholders = implode(',', array_fill(0, count($ids_arr), '%d'));
        $sql = "
            SELECT meta_id, post_id
            FROM {$this->postmeta}
            WHERE post_id IN ($placeholders)
            AND meta_key = %s
        ";
        $params = array_merge($ids_arr, array('_wp_attached_file'));
        $result = $this->wpdb->get_results($this->wpdb->prepare($sql, $params));

        // map att_id -> meta_id
        $attid_metaid_map = array();
        foreach ($result as $res) {
            $attid_metaid_map[$res->post_id] = $res->meta_id;
        }

        // map meta_id (fifu metadata) -> meta_id (attachment metadata)
        $map = array();
        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;
            $att_id = (int) $att_ids_map[$thumbnail->meta_id];
            if (!isset($attid_metaid_map[$att_id])) // no attachment metadata
                continue;
            $map[$thumbnail->meta_id] = $attid_metaid_map[$att_id];
        }

        return $map;
    }

    function speed_up_attachments_meta($bucket_id, $thumbnails, $meta_ids_map) {
        $count = 0;
        $query = "
            INSERT INTO {$this->postmeta} (meta_id, meta_value) VALUES ";

        foreach ($thumbnails as $thumbnail) {
            if (!isset($meta_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;

            $su_url = $this->get_su_url($bucket_id, $thumbnail->storage_id);

            if ($count++ != 0)
                $query .= ", ";

            // Minimal change: use prepare to safely build each VALUES tuple
            $query .= $this->wpdb->prepare("(%d, %s)", $meta_ids_map[$thumbnail->meta_id], $su_url) . " ";
        }

        $query .= "ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
        return $this->wpdb->get_results($query);
    }

    /* speed up (remove) */

    function remove_urls_su($bucket_id, $thumbnails, $urls, $video_urls) {
        foreach ($thumbnails as $thumbnail) {
            // post removed
            if (!$thumbnail->meta_id)
                unset($urls[$thumbnail->storage_id]);
        }

        if (empty($urls))
            return;

        // custom field
        $this->revert_custom_fields($thumbnails, $urls, $video_urls, false);

        // two groups
        $featured_list = array();
        foreach ($thumbnails as $thumbnail) {
            if ($thumbnail->meta_key == 'fifu_image_url')
                array_push($featured_list, $thumbnail);
        }

        // featured group
        if (count($featured_list) > 0) {
            $att_ids_map_featured = $this->get_thumbnail_ids($featured_list, false);
            if (count($att_ids_map_featured) > 0) {
                $this->revert_attachments($urls, $featured_list, $att_ids_map_featured);
                $meta_ids_map_featured = $this->get_thumbnail_meta_ids($featured_list, $att_ids_map_featured);
                if (count($meta_ids_map_featured) > 0)
                    $this->revert_attachments_meta($urls, $featured_list, $meta_ids_map_featured);
            }
        }
    }

    function ctgr_remove_urls_su($bucket_id, $thumbnails, $urls, $video_urls) {
        foreach ($thumbnails as $thumbnail) {
            // post removed
            if (!$thumbnail->meta_id)
                unset($urls[$thumbnail->storage_id]);
        }

        if (empty($urls))
            return;

        // custom field
        $this->revert_custom_fields($thumbnails, $urls, $video_urls, true);

        $featured_list = array();
        foreach ($thumbnails as $thumbnail)
            array_push($featured_list, $thumbnail);

        // featured group
        if (count($featured_list) > 0) {
            $att_ids_map = $this->get_thumbnail_ids($featured_list, true);
            if (count($att_ids_map) > 0) {
                $this->revert_attachments($urls, $featured_list, $att_ids_map);
                $meta_ids_map = $this->get_thumbnail_meta_ids($featured_list, $att_ids_map);
                if (count($meta_ids_map) > 0)
                    $this->revert_attachments_meta($urls, $featured_list, $meta_ids_map);
            }
        }
    }

    public function usage_verification_su($hex_ids) {
        $postmeta_results = $this->wpdb->get_col("
            SELECT meta_value
            FROM {$this->postmeta}
            WHERE meta_key LIKE 'fifu_%'
            AND meta_value LIKE 'https://cdn.fifu.app/%'
        ");

        $termmeta_results = $this->wpdb->get_col("
            SELECT meta_value
            FROM {$this->termmeta}
            WHERE meta_key LIKE 'fifu_%'
            AND meta_value LIKE 'https://cdn.fifu.app/%'
        ");

        $all_results = array_merge($postmeta_results, $termmeta_results);

        // Filter results using PHP
        $filtered_results = array_filter($all_results, function ($meta_value) use ($hex_ids) {
            // Split by "-" and take the first part
            $dash_split = explode('-', $meta_value);
            $first_part = $dash_split[0] ?? '';

            // Split the first part by "/" and take the last segment
            $slash_split = explode('/', $first_part);
            $hex_id = end($slash_split);

            // Check if the extracted hex_id is in the provided list
            return in_array($hex_id, $hex_ids, true);
        });

        return $filtered_results;
    }

    /* speed up (add custom fields) */

    function revert_custom_fields($thumbnails, $urls, $video_urls, $is_ctgr) {
        $table = $is_ctgr ? $this->termmeta : $this->postmeta;

        // Return early if no thumbnails to process
        if (empty($thumbnails)) {
            return null;
        }

        $query = "
            INSERT INTO {$table} (meta_id, meta_value) VALUES ";
        $count = 0;

        foreach ($thumbnails as $thumbnail) {
            if ($count++ != 0) {
                $query .= ", ";
            }

            $url = (isset($urls[$thumbnail->storage_id]) ? $urls[$thumbnail->storage_id] : '');

            // Minimal change: build each VALUES tuple with prepare to avoid SQL injection
            $query .= $this->wpdb->prepare("(%d, %s)", (int) $thumbnail->meta_id, $url);
        }

        $query .= " ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
        return $this->wpdb->get_results($query);
    }

    function revert_attachments($urls, $thumbnails, $att_ids_map) {
        // Handle null or invalid parameters
        if ($urls === null || !is_array($urls))
            $urls = [];
        if ($thumbnails === null || !is_array($thumbnails))
            $thumbnails = [];
        if ($att_ids_map === null || !is_array($att_ids_map))
            $att_ids_map = [];

        $count = 0;
        $query = "
            INSERT INTO {$this->posts} (id, post_content_filtered) VALUES ";

        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;

            if ($count++ != 0)
                $query .= ", ";

            $url = isset($urls[$thumbnail->storage_id]) ? $urls[$thumbnail->storage_id] : '';

            // Minimal change: use prepare to safely build each VALUES tuple
            $query .= $this->wpdb->prepare("(%d, %s)", (int) $att_ids_map[$thumbnail->meta_id], $url);
        }

        // If no thumbnails were processed, return early
        if ($count == 0) {
            return array(); // Return empty array instead of running invalid query
        }

        $query .= "ON DUPLICATE KEY UPDATE post_content_filtered=VALUES(post_content_filtered)";
        return $this->wpdb->get_results($query);
    }

    function revert_attachments_meta($urls, $thumbnails, $meta_ids_map) {
        $count = 0;
        $query = "
            INSERT INTO {$this->postmeta} (meta_id, meta_value) VALUES ";

        foreach ($thumbnails as $thumbnail) {
            if (!isset($meta_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;

            if ($count++ != 0)
                $query .= ", ";

            $url = isset($urls[$thumbnail->storage_id]) ? $urls[$thumbnail->storage_id] : '';

            // Minimal change: safely build each VALUES tuple
            $query .= $this->wpdb->prepare("(%d, %s)", (int) $meta_ids_map[$thumbnail->meta_id], $url);
        }

        // Only execute query if there are valid operations to perform
        if ($count > 0) {
            $query .= "ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
            return $this->wpdb->get_results($query);
        }

        // Return empty array if no valid operations were found
        return array();
    }

    // speed up (db)

    function create_table_invalid_media_su() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        maybe_create_table($this->fifu_invalid_media_su, "
            CREATE TABLE {$this->fifu_invalid_media_su} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
                md5 VARCHAR(32) NOT NULL,
                attempts INT NOT NULL,
                UNIQUE KEY (md5)
            )
        ");
    }

    function insert_invalid_media_su($url) {
        if ($url === null || $url === '')
            return;
        if ($this->get_attempts_invalid_media_su($url)) {
            $this->update_invalid_media_su($url);
            return;
        }

        $md5 = md5($url);
        $this->wpdb->query(
                $this->wpdb->prepare(
                        "INSERT INTO {$this->fifu_invalid_media_su} (md5, attempts) VALUES (%s, 1)",
                        $md5
                )
        );
    }

    function update_invalid_media_su($url) {
        if ($url === null || $url === '')
            return;
        $md5 = md5($url);
        $this->wpdb->query(
                $this->wpdb->prepare(
                        "UPDATE {$this->fifu_invalid_media_su} SET attempts = attempts + 1 WHERE md5 = %s",
                        $md5
                )
        );
    }

    function get_attempts_invalid_media_su($url) {
        if ($url === null || $url === '')
            return 0;
        $md5 = md5($url);
        $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                        "SELECT attempts FROM {$this->fifu_invalid_media_su} WHERE md5 = %s",
                        $md5
                )
        );
        return $result ? (int) $result->attempts : 0;
    }

    function delete_invalid_media_su($url) {
        if ($url === null || $url === '')
            return;
        $md5 = md5($url);
        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_invalid_media_su} WHERE md5 = %s",
                        $md5
                )
        );
    }

    ///////////////////////////////////////////////////////////////////////////////////

    function count_available_images() {
        $total = 0;

        $featured = $this->wpdb->get_results("
            SELECT COUNT(1) AS total
            FROM {$this->postmeta}
            WHERE meta_key = '_thumbnail_id'
        ");

        $total += (int) $featured[0]->total;

        if (class_exists('WooCommerce')) {
            $gallery = $this->wpdb->get_results("
                SELECT SUM(LENGTH(meta_value) - LENGTH(REPLACE(meta_value, ',', '')) + 1) AS total
                FROM {$this->postmeta}
                WHERE meta_key = '_product_image_gallery'
            ");

            $total += (int) $gallery[0]->total;

            $category = $this->wpdb->get_results("
                SELECT COUNT(1) AS total
                FROM {$this->termmeta}
                WHERE meta_key = 'thumbnail_id'
            ");

            $total += (int) $category[0]->total;
        }

        return $total;
    }

    /* insert attachment */

    function insert_attachment_by($value) {
        // $value should be a list of PREPARED tuples (e.g., from get_formatted_value), joined by ', '
        $values_sql = is_array($value) ? implode(', ', $value) : (string) $value;

        $sql = "
            INSERT INTO {$this->posts}
                (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent,
                post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered)
            VALUES {$values_sql}";
        return $this->wpdb->query($sql);
    }

    function insert_ctgr_attachment_by($value) {
        // $value should be a list of PREPARED tuples (e.g., from get_ctgr_formatted_value), joined by ', '
        $values_sql = is_array($value) ? implode(', ', $value) : (string) $value;

        $sql = "
            INSERT INTO {$this->posts}
                (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent,
                post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered, post_name)
            VALUES {$values_sql}";
        return $this->wpdb->query($sql);
    }

    function get_formatted_value($url, $alt, $post_parent) {
        $alt = $alt ?? '';
        // Return a PREPARED tuple; caller concatenates multiple with ", "
        return $this->wpdb->prepare(
                        "(%d, %s, %s, %s, %s, %s, %s, %d, NOW(), NOW(), NOW(), NOW(), %s, %s, %s, %s)",
                        (int) $this->author, // post_author
                        '', // guid
                        $alt, // post_title
                        $alt, // post_excerpt
                        'image/jpeg', // post_mime_type
                        'attachment', // post_type
                        'inherit', // post_status
                        (int) $post_parent, // post_parent
                        '', // post_content
                        '', // to_ping
                        '', // pinged
                        $url                           // post_content_filtered
                );
    }

    function get_ctgr_formatted_value($url, $alt, $post_parent) {
        $alt = $alt ?? '';
        // Return a PREPARED tuple; caller concatenates multiple with ", "
        return $this->wpdb->prepare(
                        "(%d, %s, %s, %s, %s, %s, %s, %d, NOW(), NOW(), NOW(), NOW(), %s, %s, %s, %s, %s)",
                        (int) $this->author, // post_author
                        '', // guid
                        $alt, // post_title
                        $alt, // post_excerpt
                        'image/jpeg', // post_mime_type
                        'attachment', // post_type
                        'inherit', // post_status
                        (int) $post_parent, // post_parent
                        '', // post_content
                        '', // to_ping
                        '', // pinged
                        $url, // post_content_filtered
                        'fifu-category-' . (int) $post_parent// post_name
                );
    }

    /* dimensions: clean all */

    function clean_dimensions_all() {
        // Ensure author ID is numeric
        $author_id = (int) $this->author;

        // Build a prepared statement with placeholders
        $query = $this->wpdb->prepare(
                "
            DELETE FROM {$this->postmeta} pm
            WHERE pm.meta_key = %s
            AND EXISTS (
                SELECT 1
                FROM {$this->posts} p
                WHERE p.id = pm.post_id
                AND p.post_author = %d
            )
            ",
                '_wp_attachment_metadata', // %s placeholder for meta_key
                $author_id                    // %d placeholder for author
        );

        // Execute the prepared query
        $this->wpdb->query($query);
    }

    /* save 1 post */

    function update_fake_attach_id($post_id) {
        $att_id = get_post_thumbnail_id($post_id);
        $url = fifu_main_image_url($post_id, false);
        $has_fifu_attachment = $att_id ? ($this->is_fifu_attachment($att_id) && get_option('fifu_default_attach_id') != $att_id) : false;
        // delete
        if (!$url || $url == get_option('fifu_default_url')) {
            if ($has_fifu_attachment) {
                wp_delete_attachment($att_id);
                delete_post_thumbnail($post_id);
                if (fifu_get_default_url() && fifu_is_valid_default_cpt($post_id))
                    set_post_thumbnail($post_id, get_option('fifu_default_attach_id'));
            } else {
                // when an external image is removed and an internal is added at the same time
                $attachments = $this->get_attachments_without_post($post_id);
                if ($attachments) {
                    $this->delete_attachment_meta_url_and_alt($attachments);
                    $this->delete_attachments($attachments);
                }

                if (fifu_get_default_url() && fifu_is_valid_default_cpt($post_id)) {
                    $post_thumbnail_id = get_post_thumbnail_id($post_id);
                    $hasInternal = $post_thumbnail_id && get_post_field('post_author', $post_thumbnail_id) != $this->author;
                    if (!$hasInternal)
                        set_post_thumbnail($post_id, get_option('fifu_default_attach_id'));
                }
            }
        } else {
            // update
            $alt = get_post_meta($post_id, 'fifu_image_alt', true);

            if ($has_fifu_attachment) {
                update_post_meta($att_id, '_wp_attached_file', $url);
                $alt ? update_post_meta($att_id, '_wp_attachment_image_alt', $alt) : delete_post_meta($att_id, '_wp_attachment_image_alt');
                $this->wpdb->update($this->posts, $set = array('post_title' => $alt, 'post_excerpt' => $alt, 'post_content_filtered' => $url), $where = array('id' => $att_id), null, null);
            }
            // insert
            else {
                $value = $this->get_formatted_value($url, $alt, $post_id);
                $this->insert_attachment_by($value);
                $att_id = $this->wpdb->insert_id;
                update_post_meta($post_id, '_thumbnail_id', $att_id);
                update_post_meta($att_id, '_wp_attached_file', $url);
                $alt && update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
                $attachments = $this->get_attachments_without_post($post_id);
                if ($attachments) {
                    $this->delete_attachment_meta_url_and_alt($attachments);
                    $this->delete_attachments($attachments);
                }
            }
        }
    }

    /* save 1 category */

    function ctgr_update_fake_attach_id($term_id) {
        $att_id = get_term_meta($term_id, 'thumbnail_id');
        $att_id = $att_id ? $att_id[0] : null;
        $has_fifu_attachment = $att_id ? $this->is_fifu_attachment($att_id) : false;

        $url = get_term_meta($term_id, 'fifu_image_url', true);

        // delete
        if (!$url) {
            if ($has_fifu_attachment) {
                wp_delete_attachment($att_id);
                update_term_meta($term_id, 'thumbnail_id', 0);
            }
        } else {
            // update
            $alt = get_term_meta($term_id, 'fifu_image_alt', true);
            if ($has_fifu_attachment) {
                update_post_meta($att_id, '_wp_attached_file', $url);
                $alt ? update_post_meta($att_id, '_wp_attachment_image_alt', $alt) : delete_post_meta($att_id, '_wp_attachment_image_alt');
                $this->wpdb->update($this->posts, $set = array('post_content_filtered' => $url, 'post_title' => $alt, 'post_excerpt' => $alt), $where = array('id' => $att_id), null, null);
            }
            // insert
            else {
                $value = $this->get_ctgr_formatted_value($url, $alt, $term_id);
                $this->insert_ctgr_attachment_by($value);
                $att_id = $this->wpdb->insert_id;
                update_term_meta($term_id, 'thumbnail_id', $att_id);
                update_post_meta($att_id, '_wp_attached_file', $url);
                $alt && update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
                $attachments = $this->get_ctgr_attachments_without_post($term_id);
                if ($attachments) {
                    $this->delete_attachment_meta_url_and_alt($attachments);
                    $this->delete_attachments($attachments);
                }
            }
        }
    }

    /* default url */

    function create_attachment($url) {
        $value = $this->get_formatted_value($url, null, null);
        $this->insert_attachment_by($value);
        return $this->wpdb->insert_id;
    }

    function set_default_url() {
        $att_id = (int) get_option('fifu_default_attach_id');
        if (!$att_id)
            return;

        $post_types_csv = $this->sanitize_post_types_list((string) get_option('fifu_default_cpt'));

        $tuples = [];
        foreach ($this->get_posts_without_featured_image($post_types_csv) as $res) {
            // (%d, %s, %d) -> (post_id, meta_key, meta_value)
            $tuples[] = $this->wpdb->prepare("(%d, %s, %d)", (int) $res->id, '_thumbnail_id', $att_id);
        }

        if ($tuples) {
            $this->insert_default_thumbnail_id(implode(',', $tuples));
            update_post_meta($att_id, '_wp_attached_file', (string) get_option('fifu_default_url'));
        }
    }

    function update_default_url($url) {
        $att_id = (int) get_option('fifu_default_attach_id');
        if ($url != wp_get_attachment_url($att_id)) {
            $this->wpdb->update($this->posts, $set = array('post_content_filtered' => $url), $where = array('id' => $att_id), null, null);
            update_post_meta($att_id, '_wp_attached_file', $url);
        }
    }

    function delete_default_url() {
        $att_id = (int) get_option('fifu_default_attach_id');
        wp_delete_attachment($att_id);
        delete_option('fifu_default_attach_id');
        $this->wpdb->delete($this->postmeta, array('meta_key' => '_thumbnail_id', 'meta_value' => $att_id));
    }

    /* delete post */

    function before_delete_post($post_id) {
        $default_url_enabled = fifu_is_on('fifu_enable_default_url');
        $default_att_id = $default_url_enabled ? (int) get_option('fifu_default_attach_id') : null;
        $result = $this->get_featured_and_gallery_ids($post_id);
        if ($result) {
            $aux = $result[0]->ids;
            $ids = $aux ? explode(',', $aux) : array();
            $value = null;
            foreach ($ids as $id) {
                if ($id && $id != $default_att_id)
                    $value = ($value == null) ? $id : $value . ',' . $id;
            }
            if ($value) {
                $this->delete_attachment_meta_url_and_alt($value);
                $this->delete_attachments($value);
            }
        }
    }

    /* clean metadata */

    function enable_clean() {
        $this->delete_garbage();
        fifu_disable_fake();
        update_option('fifu_fake', 'toggleoff', 'no');
    }

    function clear_meta_in() {
        $this->wpdb->query("DELETE FROM {$this->fifu_meta_in} WHERE 1=1");
    }

    function clear_meta_out() {
        $this->wpdb->query("DELETE FROM {$this->fifu_meta_out} WHERE 1=1");
    }

    /* delete all urls */

    function delete_all() {
        sleep(3);
        if (fifu_is_on('fifu_run_delete_all') && get_option('fifu_run_delete_all_time') && FIFU_DELETE_ALL_URLS) {
            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key LIKE 'fifu_%'
            ");
        }
    }

    /* metadata */

    function create_table_meta_in() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        maybe_create_table($this->fifu_meta_in, "
            CREATE TABLE {$this->fifu_meta_in} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                post_ids TEXT NOT NULL,
                type VARCHAR(8) NOT NULL
            )
        ");
    }

    function create_table_meta_out() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        maybe_create_table($this->fifu_meta_out, "
            CREATE TABLE {$this->fifu_meta_out} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                post_ids TEXT NOT NULL,
                type VARCHAR(16) NOT NULL
            )
        ");
    }

    function prepare_meta_in($post_ids_str) {
        $this->wpdb->query("SET SESSION group_concat_max_len = 1048576;"); // because GROUP_CONCAT is limited to 1024 characters
        // post (cpt)
        // Create a temporary table with an AUTO_INCREMENT column to generate row numbers
        $this->wpdb->query("
            CREATE TEMPORARY TABLE temp_post_in (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT
            );
        ");

        // Insert distinct post_ids into the temporary table, applying the necessary conditions
        $this->wpdb->query("
            INSERT INTO temp_post_in (post_id)
            SELECT DISTINCT a.post_id
            FROM {$this->postmeta} AS a
            WHERE
            a.meta_key IN ('fifu_image_url')
            AND a.meta_value IS NOT NULL
            AND a.meta_value <> ''
            AND NOT EXISTS (
                SELECT 1 
                FROM {$this->postmeta} AS b
                WHERE a.post_id = b.post_id
                AND b.meta_key = '_thumbnail_id'
                AND b.meta_value <> 0
            )
            ORDER BY a.post_id;
        ");

        // Insert into the final table from the temporary table and group by row number
        $this->wpdb->query("
            INSERT INTO {$this->fifu_meta_in} (post_ids, type)
            SELECT GROUP_CONCAT(post_id ORDER BY post_id SEPARATOR ','), 'post'
            FROM temp_post_in
            GROUP BY FLOOR((id - 1) / 5000);
        ");

        // Drop the temporary table
        $this->wpdb->query("
            DROP TEMPORARY TABLE temp_post_in;
        ");

        $last_insert_id = $this->wpdb->insert_id;
        if ($last_insert_id) {
            $this->log_prepare($last_insert_id, $this->fifu_meta_in);
        }

        // term (woocommerce category)
        // Create a temporary table with an AUTO_INCREMENT column to generate row numbers
        $this->wpdb->query("
            CREATE TEMPORARY TABLE temp_term_in (
                id INT AUTO_INCREMENT PRIMARY KEY,
                term_id INT
            );
        ");

        // Insert distinct term_ids into the temporary table, applying the necessary conditions
        $this->wpdb->query("
            INSERT INTO temp_term_in (term_id)
            SELECT DISTINCT a.term_id
            FROM {$this->termmeta} AS a
            WHERE
            a.meta_key IN ('fifu_image_url')
            AND a.meta_value IS NOT NULL
            AND a.meta_value <> ''
            AND NOT EXISTS (
                SELECT 1 
                FROM {$this->termmeta} AS b
                WHERE a.term_id = b.term_id 
                AND (
                    (b.meta_key = 'thumbnail_id' AND b.meta_value <> 0)
                    OR b.meta_key IN ('fifu_metadataterm_sent')
                )
            )
            ORDER BY a.term_id;
        ");

        // Insert into the final table from the temporary table and group by row number
        $this->wpdb->query("
            INSERT INTO {$this->fifu_meta_in} (post_ids, type)
            SELECT GROUP_CONCAT(term_id ORDER BY term_id SEPARATOR ','), 'term'
            FROM temp_term_in
            GROUP BY FLOOR((id - 1) / 5000);
        ");

        // Drop the temporary table
        $this->wpdb->query("
            DROP TEMPORARY TABLE temp_term_in;
        ");

        $prev_insert_id = $last_insert_id;
        $last_insert_id = $this->wpdb->insert_id;
        if ($last_insert_id && $prev_insert_id != $last_insert_id) {
            $this->log_prepare($last_insert_id, $this->fifu_meta_in);
        }
    }

    function prepare_meta_out() {
        $this->wpdb->query("SET SESSION group_concat_max_len = 1048576;"); // because GROUP_CONCAT is limited to 1024 characters

        $sql = "
            INSERT INTO {$this->fifu_meta_out} (post_ids, type)
            SELECT GROUP_CONCAT(DISTINCT id ORDER BY id SEPARATOR ','), 'att'
            FROM {$this->posts} 
            WHERE post_author = %d
            GROUP BY FLOOR(id / 5000)
        ";
        $this->wpdb->query($this->wpdb->prepare($sql, $this->author));

        $last_insert_id = $this->wpdb->insert_id;
        if ($last_insert_id) {
            $this->log_prepare($last_insert_id, $this->fifu_meta_out);
        }

        // Create a temporary table with an AUTO_INCREMENT column to generate row numbers
        $this->wpdb->query("
            CREATE TEMPORARY TABLE temp_term_out (
                id INT AUTO_INCREMENT PRIMARY KEY,
                term_id INT
            );
        ");

        // Insert distinct term_ids into the temporary table, applying the necessary conditions
        $this->wpdb->query("
            INSERT INTO temp_term_out (term_id)
            SELECT DISTINCT term_id
            FROM {$this->termmeta}
            WHERE
            meta_key IN ('fifu_image_url')
            AND meta_value IS NOT NULL
            AND meta_value <> ''
            ORDER BY term_id;
        ");

        // Insert into the final table from the temporary table and group by row number
        $this->wpdb->query("
            INSERT INTO {$this->fifu_meta_out} (post_ids, type)
            SELECT GROUP_CONCAT(term_id ORDER BY term_id SEPARATOR ','), 'term'
            FROM temp_term_out
            GROUP BY FLOOR((id - 1) / 5000);
        ");

        // Drop the temporary table
        $this->wpdb->query("
            DROP TEMPORARY TABLE temp_term_out;
        ");

        $prev_insert_id = $last_insert_id;
        $last_insert_id = $this->wpdb->insert_id;
        if ($last_insert_id && $prev_insert_id != $last_insert_id) {
            $this->log_prepare($last_insert_id, $this->fifu_meta_out);
        }
    }

    function get_meta_in() {
        return $this->wpdb->get_results("
            SELECT id AS post_id
            FROM {$this->fifu_meta_in}
        ");
    }

    function get_meta_out() {
        return $this->wpdb->get_results("
            SELECT id AS post_id
            FROM {$this->fifu_meta_out}
        ");
    }

    function get_meta_in_first() {
        return $this->wpdb->get_results("
            SELECT id AS post_id
            FROM {$this->fifu_meta_in}
            LIMIT 1
        ");
    }

    function get_meta_out_first() {
        return $this->wpdb->get_results("
            SELECT id AS post_id
            FROM {$this->fifu_meta_out}
            LIMIT 1
        ");
    }

    function get_type_meta_in($id) {
        $query = $this->wpdb->prepare("
            SELECT type
            FROM {$this->fifu_meta_in}
            WHERE id = %d",
                $id
        );
        return $this->wpdb->get_var($query);
    }

    function log_prepare($last_insert_id, $table) {
        $inserted_records = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT id, post_ids, type FROM {$table} WHERE id = %d",
                        (int) $last_insert_id
                )
        );

        foreach ($inserted_records as $record) {
            fifu_plugin_log([$table => [
                    'id' => $record->id,
                    'post_ids' => $record->post_ids,
                    'type' => $record->type
            ]]);
        }
    }

    function get_type_meta_out($id) {
        $query = $this->wpdb->prepare("
            SELECT type
            FROM {$this->fifu_meta_out}
            WHERE id = %d",
                $id
        );
        return $this->wpdb->get_var($query);
    }

    function insert_postmeta($id) {
        $result = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT post_ids FROM {$this->fifu_meta_in} WHERE id = %d",
                        (int) $id
                )
        );

        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_meta_in} WHERE id = %d",
                        (int) $id
                )
        );

        if (count($result) == 0)
            return false;

        // insert 1 attachment for each selected post
        $value_arr = array();
        $ids = $result[0]->post_ids;
        $meta_data = $this->get_fifu_fields($ids);
        $post_ids = explode(",", $ids);
        foreach ($post_ids as $post_id) {
            $url = $this->get_main_image_url($meta_data[$post_id], $post_id);
            $aux = $this->get_formatted_value($url, $meta_data[$post_id]['fifu_image_alt'], $post_id);
            array_push($value_arr, $aux);
        }
        $value = implode(",", $value_arr);
        wp_cache_flush();
        $this->insert_postmeta2($value, $ids);

        fifu_set_transient('fifu_metadata_counter', fifu_get_transient('fifu_metadata_counter') - count($post_ids), 0);

        return true;
    }

    function delete_attmeta($id) {
        $result = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT post_ids FROM {$this->fifu_meta_out} WHERE id = %d",
                        (int) $id
                )
        );

        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_meta_out} WHERE id = %d",
                        (int) $id
                )
        );

        if (count($result) == 0)
            return false;

        $ids = $result[0]->post_ids;
        $post_ids = explode(",", $ids);
        wp_cache_flush();
        $this->delete_attmeta2($ids);

        fifu_set_transient('fifu_metadata_counter', fifu_get_transient('fifu_metadata_counter') - count($post_ids), 0);

        return true;
    }

    function delete_garbage() {
        wp_cache_flush();

        // Cast option-derived IDs to integers to avoid SQL injection
        $fake_attach_id = (int) get_option('fifu_fake_attach_id');
        $default_attach_id = (int) get_option('fifu_default_attach_id');

        $this->wpdb->query('START TRANSACTION');

        try {
            $fake_attach_sql = $fake_attach_id ? "OR meta_value = {$fake_attach_id}" : "";
            $default_attach_sql = $default_attach_id ? "OR meta_value = {$default_attach_id}" : "";

            // default
            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key IN ('_thumbnail_id')
                AND (
                    meta_value = -1
                    {$fake_attach_sql}
                    {$default_attach_sql}
                    OR meta_value IS NULL 
                    OR meta_value LIKE 'fifu:%'
                )
            ");

            // duplicated
            $this->wpdb->query("
                DELETE FROM {$this->termmeta}
                WHERE meta_key = 'fifu_image_url'
                AND meta_id NOT IN (
                    SELECT * FROM (
                        SELECT MAX(tm.meta_id) AS meta_id
                        FROM {$this->termmeta} tm
                        WHERE tm.meta_key = 'fifu_image_url'
                        GROUP BY tm.term_id
                    ) aux
                )
            ");

            $global_media_sql = fifu_is_multisite_global_media_active() ? "AND meta_value NOT LIKE '100000%'" : "";

            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key = '_thumbnail_id' 
                {$global_media_sql}
                AND NOT EXISTS (
                    SELECT 1 
                    FROM {$this->posts} p 
                    WHERE p.id = meta_value
                )
            ");

            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata') 
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$this->posts} p 
                    WHERE p.id = post_id
                )
            ");

            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key LIKE 'fifu_%'
                AND (
                    meta_value = ''
                    OR meta_value is NULL
                )
            ");

            $this->wpdb->query("
                DELETE FROM {$this->termmeta} 
                WHERE meta_key = 'thumbnail_id' 
                AND NOT EXISTS (
                    SELECT 1 
                    FROM {$this->posts} p 
                    WHERE p.id = meta_value
                )
            ");

            $this->wpdb->query("
                DELETE FROM {$this->termmeta} 
                WHERE meta_key LIKE 'fifu_%'
                AND (
                    meta_value = ''
                    OR meta_value is NULL
                )
            ");

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }

        wp_delete_attachment($fake_attach_id);
        wp_delete_attachment($default_attach_id);
        delete_option('fifu_fake_attach_id');
        delete_option('fifu_default_attach_id');

        return true;
    }

    function delete_termmeta($id) {
        $result = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT post_ids FROM {$this->fifu_meta_out} WHERE id = %d",
                        (int) $id
                )
        );

        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_meta_out} WHERE id = %d",
                        (int) $id
                )
        );

        if (count($result) == 0)
            return false;

        $ids = $result[0]->post_ids;
        $term_ids = explode(",", $ids);
        wp_cache_flush();
        $this->delete_termmeta2($ids);

        fifu_set_transient('fifu_metadata_counter', fifu_get_transient('fifu_metadata_counter') - count($term_ids), 0);

        return true;
    }

    function insert_postmeta2($value, $ids) {
        $this->wpdb->query('START TRANSACTION');
        $ids_csv = $this->sanitize_ids_csv($ids);

        try {
            $this->wpdb->query(
                    "INSERT INTO {$this->posts} (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered) 
                VALUES " . $value
            );

            $author = $this->author;
            $sql_thumb = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.post_parent, '_thumbnail_id', p.id 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.post_author = %d 
                )
            ", $author);
            $this->wpdb->query($sql_thumb);

            $sql_file = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attached_file', p.post_content_filtered
                    FROM {$this->posts} p 
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.post_author = %d 
                )
            ", $author);
            $this->wpdb->query($sql_file);

            $sql_alt = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attachment_image_alt', p.post_title 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.post_author = %d 
                    AND p.post_title IS NOT NULL 
                    AND p.post_title != ''
                )
            ", $author);
            $this->wpdb->query($sql_alt);

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function delete_attmeta2($ids) {
        $this->wpdb->query('START TRANSACTION');
        $ids_csv = $this->sanitize_ids_csv($ids);

        try {
            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key = '_thumbnail_id' 
                AND meta_value IN (0, {$ids_csv})
            ");

            $author = $this->author;
            $sql_del_posts = $this->wpdb->prepare("
                DELETE FROM {$this->posts} 
                WHERE id IN ({$ids_csv})
                AND post_author = %d
            ", $author);
            $this->wpdb->query($sql_del_posts);

            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata') 
                AND post_id IN ({$ids_csv})
            ");

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function delete_termmeta2($ids) {
        $this->wpdb->query('START TRANSACTION');
        $ids_csv = $this->sanitize_ids_csv($ids);

        try {
            $this->wpdb->query("
                DELETE FROM {$this->termmeta} 
                WHERE meta_key = 'thumbnail_id' 
                AND term_id IN ({$ids_csv})
            ");

            $author = $this->author;
            $sql_del_pm = $this->wpdb->prepare("
                DELETE pm
                FROM {$this->postmeta} pm JOIN {$this->posts} p ON pm.post_id = p.id
                WHERE pm.meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
                AND p.post_parent IN ({$ids_csv})
                AND p.post_author = %d 
                AND p.post_name LIKE 'fifu-category%'
            ", $author);
            $this->wpdb->query($sql_del_pm);

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function insert_termmeta($id) {
        $result = $this->wpdb->get_results(
                $this->wpdb->prepare(
                        "SELECT post_ids FROM {$this->fifu_meta_in} WHERE id = %d",
                        (int) $id
                )
        );

        $this->wpdb->query(
                $this->wpdb->prepare(
                        "DELETE FROM {$this->fifu_meta_in} WHERE id = %d",
                        (int) $id
                )
        );

        if (count($result) == 0)
            return false;

        // insert 1 attachment for each selected category
        $value_arr = array();
        $ids = $result[0]->post_ids;
        $term_ids = explode(",", $ids);
        foreach ($term_ids as $term_id) {
            $url = get_term_meta($term_id, 'fifu_image_url', true);
            $url = htmlspecialchars_decode($url);
            $aux = $this->get_ctgr_formatted_value($url, get_term_meta($term_id, 'fifu_image_alt', true), $term_id);
            array_push($value_arr, $aux);
        }
        $value = implode(",", $value_arr);
        wp_cache_flush();
        $this->insert_termmeta2($value, $ids);

        fifu_set_transient('fifu_metadata_counter', fifu_get_transient('fifu_metadata_counter') - count($term_ids), 0);

        return true;
    }

    function insert_termmeta2($value, $ids) {
        $this->wpdb->query('START TRANSACTION');
        $ids_csv = $this->sanitize_ids_csv($ids);

        try {
            $this->wpdb->query(
                    "INSERT INTO {$this->posts} (post_author, guid, post_title, post_excerpt, post_mime_type, post_type, post_status, post_parent, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, to_ping, pinged, post_content_filtered, post_name) 
                VALUES " . $value
            );

            $author = $this->author;
            $sql_term_thumbnail = $this->wpdb->prepare("
                INSERT INTO {$this->termmeta} (term_id, meta_key, meta_value) (
                    SELECT p.post_parent, 'thumbnail_id', p.id 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.post_author = %d 
                    AND p.post_name LIKE 'fifu-category%'
                )
            ", $author);
            $this->wpdb->query($sql_term_thumbnail);

            $sql_term_file = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attached_file', p.post_content_filtered
                    FROM {$this->posts} p 
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.post_author = %d 
                    AND p.post_name LIKE 'fifu-category%'
                )
            ", $author);
            $this->wpdb->query($sql_term_file);

            $sql_term_alt = $this->wpdb->prepare("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attachment_image_alt', p.post_title 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids_csv}) 
                    AND p.post_author = %d 
                    AND p.post_title IS NOT NULL 
                    AND p.post_title != ''
                    AND p.post_name LIKE 'fifu-category%'
                )
            ", $author);
            $this->wpdb->query($sql_term_alt);

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function get_fifu_fields($ids) {
        $ids_csv = $this->sanitize_ids_csv($ids);
        $results = $this->wpdb->get_results("
            SELECT post_id, meta_key, meta_value
            FROM {$this->postmeta}
            WHERE post_id IN ({$ids_csv})
            AND meta_key IN ('fifu_image_url', 'fifu_image_alt')
        ");

        $post_ids = explode(",", $ids);

        $data = [];
        foreach ($post_ids as $id) {
            $data[$id] = [
                'fifu_image_url' => "",
                'fifu_image_alt' => ""
            ];
        }

        // Populate the results
        foreach ($results as $row) {
            if (isset($data[$row->post_id]))
                $data[$row->post_id][$row->meta_key] = $row->meta_value;
        }

        return $data;
    }

    function get_main_image_url($meta_data, $post_id) {
        $url = $meta_data['fifu_image_url'] ?? '';

        if (!$url && fifu_no_internal_image($post_id) && (get_option('fifu_default_url') && fifu_is_on('fifu_enable_default_url'))) {
            if (fifu_is_valid_default_cpt($post_id))
                $url = get_option('fifu_default_url');
        }

        if (!$url)
            return null;

        $url = htmlspecialchars_decode($url);

        return str_replace("'", "%27", $url);
    }
}

/* dimensions: clean all */

function fifu_db_clean_dimensions_all() {
    $db = new FifuDb();
    return $db->clean_dimensions_all();
}

/* dimensions: amount */

function fifu_db_missing_dimensions() {
    $db = new FifuDb();

    $aux = $db->get_count_posts_without_dimensions()[0];
    return $aux ? $aux->amount : -1;
}

/* count: metadata */

function fifu_db_count_urls_with_metadata() {
    $db = new FifuDb();
    $aux = $db->get_count_urls_with_metadata()[0];
    return $aux ? $aux->amount : 0;
}

function fifu_db_count_metadata_operations() {
    $db = new FifuDb();
    $total_amount = $db->get_count_metadata_operations();
    return $total_amount ? $total_amount : 0;
}

/* count: urls */

function fifu_db_count_urls() {
    $db = new FifuDb();
    $aux = $db->get_count_urls();
    return $aux ? $aux : 0;
}

function fifu_db_get_count_wp_posts() {
    $db = new FifuDb();
    $aux = $db->get_count_wp_posts()[0];
    return $aux ? $aux->amount : 0;
}

function fifu_db_get_count_wp_postmeta() {
    $db = new FifuDb();
    $aux = $db->get_count_wp_postmeta()[0];
    return $aux ? $aux->amount : 0;
}

function fifu_db_get_count_wp_posts_fifu() {
    $db = new FifuDb();
    $aux = $db->get_count_wp_posts_fifu()[0];
    return $aux ? $aux->amount : 0;
}

function fifu_db_get_count_wp_postmeta_fifu() {
    $db = new FifuDb();
    $aux = $db->get_count_wp_postmeta_fifu()[0];
    return $aux ? $aux->amount : 0;
}

function fifu_db_tables_created() {
    $db = new FifuDb();
    return $db->tables_created();
}

/* clean metadata */

function fifu_db_enable_clean() {
    $db = new FifuDb();
    $db->clear_meta_in();
    $db->enable_clean();
}

function fifu_db_clear_meta_in() {
    $db = new FifuDb();
    $db->clear_meta_in();
}

function fifu_db_clear_meta_out() {
    $db = new FifuDb();
    $db->clear_meta_out();
}

function fifu_db_get_type_meta_in($id) {
    $db = new FifuDb();
    return $db->get_type_meta_in($id);
}

function fifu_db_get_type_meta_out($id) {
    $db = new FifuDb();
    return $db->get_type_meta_out($id);
}

function fifu_db_insert_postmeta($id) {
    $db = new FifuDb();
    return $db->insert_postmeta($id);
}

function fifu_db_insert_termmeta($id) {
    $db = new FifuDb();
    return $db->insert_termmeta($id);
}

function fifu_db_delete_attmeta($id) {
    $db = new FifuDb();
    return $db->delete_attmeta($id);
}

function fifu_db_delete_termmeta($id) {
    $db = new FifuDb();
    return $db->delete_termmeta($id);
}

/* delete all urls */

function fifu_db_delete_all() {
    $db = new FifuDb();
    return $db->delete_all();
}

/* save post */

function fifu_db_update_fake_attach_id($post_id) {
    $db = new FifuDb();
    $db->update_fake_attach_id($post_id);
}

/* save category */

function fifu_db_ctgr_update_fake_attach_id($term_id) {
    $db = new FifuDb();
    $db->ctgr_update_fake_attach_id($term_id);
}

/* default url */

function fifu_db_create_attachment($url) {
    $db = new FifuDb();
    return $db->create_attachment($url);
}

function fifu_db_set_default_url() {
    $db = new FifuDb();
    return $db->set_default_url();
}

function fifu_db_update_default_url($url) {
    $db = new FifuDb();
    return $db->update_default_url($url);
}

function fifu_db_delete_default_url() {
    $db = new FifuDb();
    return $db->delete_default_url();
}

/* delete post */

function fifu_db_before_delete_post($post_id) {
    $db = new FifuDb();
    $db->before_delete_post($post_id);
}

/* number of posts */

function fifu_db_number_of_posts() {
    $db = new FifuDb();
    return $db->get_number_of_posts();
}

/* speed up */

function fifu_db_get_all_urls($page, $type, $keyword) {
    $db = new FifuDb();
    return $db->get_all_urls($page, $type, $keyword);
}

function fifu_db_get_all_hex_ids() {
    $db = new FifuDb();
    return $db->get_all_hex_ids();
}

function fifu_db_get_posts_with_internal_featured_image($page, $type, $keyword) {
    $db = new FifuDb();
    return $db->get_posts_with_internal_featured_image($page, $type, $keyword);
}

function fifu_get_posts_su($storage_ids) {
    $db = new FifuDb();
    return $db->get_posts_su($storage_ids);
}

function fifu_add_urls_su($bucket_id, $thumbnails) {
    $db = new FifuDb();
    return $db->add_urls_su($bucket_id, $thumbnails);
}

function fifu_ctgr_add_urls_su($bucket_id, $thumbnails) {
    $db = new FifuDb();
    return $db->ctgr_add_urls_su($bucket_id, $thumbnails);
}

function fifu_remove_urls_su($bucket_id, $thumbnails, $urls, $video_urls) {
    $db = new FifuDb();
    return $db->remove_urls_su($bucket_id, $thumbnails, $urls, $video_urls);
}

function fifu_ctgr_remove_urls_su($bucket_id, $thumbnails, $urls, $video_urls) {
    $db = new FifuDb();
    return $db->ctgr_remove_urls_su($bucket_id, $thumbnails, $urls, $video_urls);
}

function fifu_usage_verification_su($hex_ids) {
    $db = new FifuDb();
    return $db->usage_verification_su($hex_ids);
}

function fifu_db_count_available_images() {
    $db = new FifuDb();
    return $db->count_available_images();
}

/* invalid media */

function fifu_db_create_table_invalid_media_su() {
    $db = new FifuDb();
    return $db->create_table_invalid_media_su();
}

function fifu_db_insert_invalid_media_su($url) {
    $db = new FifuDb();
    return $db->insert_invalid_media_su($url);
}

function fifu_db_delete_invalid_media_su($url) {
    $db = new FifuDb();
    return $db->delete_invalid_media_su($url);
}

function fifu_db_get_attempts_invalid_media_su($url) {
    $db = new FifuDb();
    return $db->get_attempts_invalid_media_su($url);
}

/* get last urls */

function fifu_db_get_last($meta_key) {
    $db = new FifuDb();
    return $db->get_last($meta_key);
}

function fifu_db_get_last_image() {
    $db = new FifuDb();
    return $db->get_last_image();
}

/* att_id */

function fifu_db_get_att_id($post_parent, $url, $is_ctgr) {
    $db = new FifuDb();
    return $db->get_att_id($post_parent, $url, $is_ctgr);
}

/* metadata */

function fifu_db_maybe_create_table_meta_in() {
    $db = new FifuDb();
    $db->create_table_meta_in();
}

function fifu_db_maybe_create_table_meta_out() {
    $db = new FifuDb();
    $db->create_table_meta_out();
}

function fifu_db_prepare_meta_in() {
    $db = new FifuDb();
    $db->prepare_meta_in(null);
}

function fifu_db_prepare_meta_out() {
    $db = new FifuDb();
    $db->prepare_meta_out();
}

function fifu_db_get_meta_in() {
    $db = new FifuDb();
    return $db->get_meta_in();
}

function fifu_db_get_meta_out() {
    $db = new FifuDb();
    return $db->get_meta_out();
}

function fifu_db_get_meta_in_first() {
    $db = new FifuDb();
    return $db->get_meta_in_first();
}

function fifu_db_get_meta_out_first() {
    $db = new FifuDb();
    return $db->get_meta_out_first();
}

/* wp_options */

function fifu_db_select_option_prefix($prefix) {
    $db = new FifuDb();
    return $db->select_option_prefix($prefix);
}

function fifu_db_delete_option_prefix($prefix) {
    $db = new FifuDb();
    return $db->delete_option_prefix($prefix);
}

/* debug */

function fifu_db_debug_slug($slug) {
    $db = new FifuDb();
    return $db->debug_slug($slug);
}

function fifu_db_debug_postmeta($post_id) {
    $db = new FifuDb();
    return $db->debug_postmeta($post_id);
}

function fifu_db_debug_posts($id) {
    $db = new FifuDb();
    return $db->debug_posts($id);
}

function fifu_db_debug_metain() {
    $db = new FifuDb();
    return $db->debug_metain();
}

function fifu_db_debug_metaout() {
    $db = new FifuDb();
    return $db->debug_metaout();
}

