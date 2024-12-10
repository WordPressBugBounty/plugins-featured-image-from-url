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

    function get_types() {
        $post_types = fifu_get_post_types();
        return join("','", $post_types);
    }

    /* attachment metadata */

    // delete 1 _wp_attached_file or _wp_attachment_image_alt for each attachment
    function delete_attachment_meta($ids, $is_ctgr) {
        $ctgr_sql = $is_ctgr ? "AND p.post_name LIKE 'fifu-category%'" : "";

        $this->wpdb->query("
            DELETE pm
            FROM {$this->postmeta} pm JOIN {$this->posts} p ON pm.post_id = p.id
            WHERE pm.meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
            AND p.post_parent IN ({$ids})
            AND p.post_author = {$this->author} 
            {$ctgr_sql}
        ");
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
        return $this->wpdb->get_row("
            SELECT 1 
            FROM {$this->posts} 
            WHERE id = {$att_id} 
            AND post_author = {$this->author}"
                ) != null;
    }

    // get att_id by post and url
    function get_att_id($post_parent, $url, $is_ctgr) {
        $ctgr_sql = $is_ctgr ? "AND p.post_name LIKE 'fifu-category%'" : "";
        $result = $this->wpdb->get_results("
            SELECT pm.post_id
            FROM {$this->postmeta} pm
            WHERE pm.meta_key = '_wp_attached_file'
            AND pm.meta_value = '{$url}'
            AND pm.post_id IN (
                SELECT p.id
                FROM {$this->posts} p 
                WHERE p.post_parent = {$post_parent}
                AND post_author = {$this->author}
                {$ctgr_sql} 
            )
            LIMIT 1
        ");
        return $result ? $result[0]->post_id : null;
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
        return $this->wpdb->get_results("
            SELECT COUNT(1) AS amount
            FROM {$this->posts}
            WHERE post_author = {$this->author}
        ");
    }

    function get_count_wp_postmeta_fifu() {
        return $this->wpdb->get_results("
            SELECT COUNT(1) AS amount
            FROM {$this->postmeta}
            WHERE meta_key = '_wp_attached_file'
            AND EXISTS (
                SELECT 1
                FROM {$this->posts}
                WHERE id = post_id
                AND post_author = {$this->author}
            )
        ");
    }

    function tables_created() {
        return $this->wpdb->get_var("SHOW TABLES LIKE '{$this->fifu_meta_in}'");
    }

    function debug_slug($slug) {
        $sql = $this->wpdb->prepare("SELECT ID, post_author, post_content, post_title, post_status, post_parent, post_content_filtered, guid, post_type FROM {$this->posts} WHERE post_name = %s", $slug);
        return $this->wpdb->get_results($sql);
    }

    function debug_postmeta($post_id) {
        $sql = $this->wpdb->prepare("
            SELECT meta_key, meta_value
            FROM {$this->postmeta} 
            WHERE post_id = %d 
            AND (
                meta_key LIKE 'fifu%'
                OR meta_key IN ('_thumbnail_id', '_wp_attached_file', '_wp_attachment_image_alt', '_product_image_gallery', '_wc_additional_variation_images')
            )"
                , $post_id);
        return $this->wpdb->get_results($sql);
    }

    function debug_posts($id) {
        $sql = $this->wpdb->prepare("
            SELECT post_author, post_content, post_title, post_status, post_parent, post_content_filtered, guid, post_type
            FROM {$this->posts} 
            WHERE id = %d"
                , $id);
        return $this->wpdb->get_results($sql);
    }

    function debug_metain() {
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->fifu_meta_in}");
        return $this->wpdb->get_results($sql);
    }

    function debug_metaout() {
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->fifu_meta_out}");
        return $this->wpdb->get_results($sql);
    }

    // count images without dimensions
    function get_count_posts_without_dimensions() {
        return $this->wpdb->get_results("
            SELECT COUNT(1) AS amount
            FROM {$this->posts} p
            WHERE NOT EXISTS (
                SELECT 1 
                FROM {$this->postmeta} b
                WHERE p.id = b.post_id AND meta_key = '_wp_attachment_metadata'
            )
            AND p.post_author = {$this->author}
        ");
    }

    // count urls with metadata
    function get_count_urls_with_metadata() {
        return $this->wpdb->get_results("
            SELECT COUNT(1) AS amount
            FROM {$this->posts} p
            WHERE p.post_author = {$this->author}
        ");
    }

    // count urls
    function get_count_urls() {
        return $this->wpdb->get_results("
            SELECT SUM(id) AS amount
            FROM (
                SELECT count(post_id) AS id
                FROM {$this->postmeta} pm
                WHERE pm.meta_key LIKE 'fifu_%'
                AND pm.meta_key LIKE '%url%'
                AND pm.meta_key NOT LIKE '%list%'
                UNION 
                SELECT count(term_id) AS id
                FROM {$this->termmeta} tm
                WHERE tm.meta_key LIKE 'fifu_%'
                AND tm.meta_key LIKE '%url%'
            ) x
        ");
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
        return $this->wpdb->get_results("
            SELECT p.id, pm.meta_value
            FROM {$this->posts} p
            INNER JOIN {$this->postmeta} pm ON p.id = pm.post_id
            WHERE pm.meta_key = '{$meta_key}'
            ORDER BY p.post_date DESC
            LIMIT 3
        ");
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

    // get attachments without post
    function get_attachments_without_post($post_id) {
        $result = $this->wpdb->get_results("
            SELECT GROUP_CONCAT(id) AS ids 
            FROM {$this->posts} 
            WHERE post_parent = {$post_id} 
            AND post_author = {$this->author}
            AND post_name NOT LIKE 'fifu-category%' 
            AND NOT EXISTS (
	            SELECT 1
                FROM {$this->postmeta}
                WHERE post_id = post_parent
                AND meta_key = '_thumbnail_id'
                AND meta_value = id
            )
            GROUP BY post_parent
        ");
        return $result ? $result[0]->ids : null;
    }

    function get_ctgr_attachments_without_post($term_id) {
        $result = $this->wpdb->get_results("
            SELECT GROUP_CONCAT(id) AS ids 
            FROM {$this->posts} 
            WHERE post_parent = {$term_id} 
            AND post_author = {$this->author} 
            AND post_name LIKE 'fifu-category%' 
            AND NOT EXISTS (
	            SELECT 1
                FROM {$this->termmeta}
                WHERE term_id = post_parent
                AND meta_key = 'thumbnail_id'
                AND meta_value = id
            )
            GROUP BY post_parent
        ");
        return $result ? $result[0]->ids : null;
    }

    function get_posts_without_featured_image($post_types) {
        return $this->wpdb->get_results("
            SELECT id, post_title
            FROM {$this->posts} 
            WHERE post_type IN ('$post_types')
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
        return $this->wpdb->get_results("
            SELECT GROUP_CONCAT(meta_value SEPARATOR ',') as 'ids'
            FROM {$this->postmeta}
            WHERE post_id = {$post_id}
            AND meta_key IN ('_thumbnail_id')
        ");
    }

    function insert_default_thumbnail_id($value) {
        $this->wpdb->query("
            INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value)
            VALUES {$value}
        ");
    }

    // clean metadata

    function delete_attachments($ids) {
        $this->wpdb->query("
            DELETE FROM {$this->posts} 
            WHERE id IN ({$ids})
            AND post_author = {$this->author}
        ");
    }

    function delete_attachment_meta_url_and_alt($ids) {
        $this->wpdb->query("
            DELETE FROM {$this->postmeta} 
            WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
            AND post_id IN ({$ids})
            AND EXISTS (
                SELECT 1 
                FROM {$this->posts} 
                WHERE id = post_id 
                AND post_author = {$this->author}
            )
        ");
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

    /* speed up */

    function get_all_urls($page) {
        $start = $page * 1000;

        $sql = "
            (
                SELECT pm.meta_id, pm.post_id, pm.meta_value AS url, pm.meta_key, p.post_name, p.post_title, p.post_date, false AS category, null AS video_url
                FROM {$this->postmeta} pm
                INNER JOIN {$this->posts} p ON pm.post_id = p.id
                WHERE pm.meta_key = 'fifu_image_url'
                AND pm.meta_value NOT LIKE '%https://cdn.fifu.app/%'
                AND pm.meta_value NOT LIKE 'http://localhost/%'
                AND p.post_status <> 'trash'
            )
        ";
        if (class_exists('WooCommerce')) {
            $sql .= " 
                UNION
                (
                    SELECT tm.meta_id, tm.term_id AS post_id, tm.meta_value AS url, tm.meta_key, null AS post_name, t.name AS post_title, null AS post_date, true AS category, null AS video_url
                    FROM {$this->termmeta} tm
                    INNER JOIN {$this->terms} t ON tm.term_id = t.term_id
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

    function get_posts_with_internal_featured_image($page) {
        $start = $page * 1000;

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
                INNER JOIN {$this->posts} p ON pm.post_id = p.id
                INNER JOIN {$this->posts} att ON (
                    pm.meta_key = '_thumbnail_id'
                    AND pm.meta_value = att.id
                    AND att.post_author <> {$this->author}
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
                    INNER JOIN {$this->terms} t ON tm.term_id = t.term_id
                    INNER JOIN {$this->posts} att ON (
                        tm.meta_key = 'thumbnail_id'
                        AND tm.meta_value = att.id
                        AND att.post_author <> {$this->author}
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
        if ($storage_ids) {
            $storage_ids = '"' . implode('","', $storage_ids) . '"';
            $filter_post_image = "AND SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, '/', 5), '/', -1) IN ({$storage_ids})";
            $filter_term_image = "AND SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, '/', 5), '/', -1) IN ({$storage_ids})";
        } else
            $filter_post_image = $filter_term_image = "";

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
                AND pm.meta_value LIKE 'https://cdn.fifu.app/%'" .
                $filter_post_image . "
            )
        ";
        if (class_exists('WooCommerce')) {
            $sql .= "            
                UNION
                (
                    SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(tm.meta_value, '/', 5), '/', -1) AS storage_id, 
                        t.name AS post_title, 
                        null AS post_date, 
                        tm.meta_id, 
                        tm.term_id AS post_id, 
                        tm.meta_key, 
                        true AS category
                    FROM {$this->termmeta} tm
                    INNER JOIN {$this->terms} t ON tm.term_id = t.term_id
                    WHERE tm.meta_key = 'fifu_image_url'
                    AND tm.meta_value LIKE 'https://cdn.fifu.app/%'" .
                    $filter_term_image . "
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

        $query = "
            INSERT INTO {$table} (meta_id, meta_value) VALUES ";
        $count = 0;
        foreach ($thumbnails as $thumbnail) {
            $su_url = $this->get_su_url($bucket_id, $thumbnail->storage_id);

            if ($count++ != 0)
                $query .= ", ";
            $query .= "({$thumbnail->meta_id},'{$su_url}') ";
        }
        $query .= "ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
        return $this->wpdb->get_results($query);
    }

    function get_thumbnail_ids($thumbnails, $is_ctgr) {
        // join post_ids
        $i = 0;
        $ids = null;
        foreach ($thumbnails as $thumbnail)
            $ids = ($i++ == 0) ? $thumbnail->post_id : ($ids . "," . $thumbnail->post_id);

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
            $query .= "(" . $att_ids_map[$thumbnail->meta_id] . ",'{$su_url}') ";
        }
        $query .= "ON DUPLICATE KEY UPDATE post_content_filtered=VALUES(post_content_filtered)";
        return $this->wpdb->get_results($query);
    }

    function get_thumbnail_meta_ids($thumbnails, $att_ids_map) {
        // join post_ids
        $i = 0;
        $ids = null;
        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;
            $ids = ($i++ == 0) ? $att_ids_map[$thumbnail->meta_id] : ($ids . "," . $att_ids_map[$thumbnail->meta_id]);
        }

        // get meta ids
        $result = $this->wpdb->get_results("
            SELECT meta_id, post_id
            FROM {$this->postmeta} 
            WHERE post_id IN ({$ids}) 
            AND meta_key = '_wp_attached_file'
        ");

        // map att_id -> meta_id
        $attid_metaid_map = array();
        foreach ($result as $res)
            $attid_metaid_map[$res->post_id] = $res->meta_id;

        // map meta_id (fifu metadata) -> meta_id (atachment metadata)
        $map = array();
        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;
            if (!isset($attid_metaid_map[$att_ids_map[$thumbnail->meta_id]])) // no metadata, only custom field
                continue;
            $att_meta_id = $attid_metaid_map[$att_ids_map[$thumbnail->meta_id]];
            $map[$thumbnail->meta_id] = $att_meta_id;
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
            $query .= "(" . $meta_ids_map[$thumbnail->meta_id] . ",'{$su_url}') ";
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
            $att_ids_map = $this->get_thumbnail_ids($featured_list, false);
            if (count($att_ids_map) > 0) {
                $this->revert_attachments($urls, $featured_list, $att_ids_map);
                $meta_ids_map = $this->get_thumbnail_meta_ids($featured_list, $att_ids_map);
                if (count($meta_ids_map) > 0)
                    $this->revert_attachments_meta($urls, $featured_list, $meta_ids_map);
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

        $query = "
            INSERT INTO {$table} (meta_id, meta_value) VALUES ";
        $count = 0;
        foreach ($thumbnails as $thumbnail) {
            if ($count++ != 0)
                $query .= ", ";
            $url = $urls[$thumbnail->storage_id];
            $query .= "({$thumbnail->meta_id},'{$url}')";
        }
        $query .= "ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
        return $this->wpdb->get_results($query);
    }

    function revert_attachments($urls, $thumbnails, $att_ids_map) {
        $count = 0;
        $query = "
            INSERT INTO {$this->posts} (id, post_content_filtered) VALUES ";
        foreach ($thumbnails as $thumbnail) {
            if (!isset($att_ids_map[$thumbnail->meta_id])) // no metadata, only custom field
                continue;
            if ($count++ != 0)
                $query .= ", ";
            $query .= "(" . $att_ids_map[$thumbnail->meta_id] . ",'" . $urls[$thumbnail->storage_id] . "')";
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
            $query .= "(" . $meta_ids_map[$thumbnail->meta_id] . ",'" . $urls[$thumbnail->storage_id] . "')";
        }
        $query .= "ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)";
        return $this->wpdb->get_results($query);
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
        if ($this->get_attempts_invalid_media_su($url)) {
            $this->update_invalid_media_su($url);
            return;
        }

        $md5 = md5($url);
        $this->wpdb->query("
            INSERT INTO {$this->fifu_invalid_media_su} (md5, attempts) 
            VALUES ('{$md5}', 1)
        ");
    }

    function update_invalid_media_su($url) {
        $md5 = md5($url);
        $this->wpdb->query("
            UPDATE {$this->fifu_invalid_media_su} 
            SET attempts = attempts + 1
            WHERE md5 = '{$md5}'
        ");
    }

    function get_attempts_invalid_media_su($url) {
        $md5 = md5($url);
        $result = $this->wpdb->get_row("
            SELECT attempts
            FROM {$this->fifu_invalid_media_su} 
            WHERE md5 = '{$md5}'
        ");
        return $result ? (int) $result->attempts : 0;
    }

    function delete_invalid_media_su($url) {
        $md5 = md5($url);
        $this->wpdb->query("
            DELETE FROM {$this->fifu_invalid_media_su} 
            WHERE md5 = '{$md5}'
        ");
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
        $this->wpdb->query("
            INSERT INTO {$this->posts} (post_author, guid, post_title, post_mime_type, post_type, post_status, post_parent, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, post_excerpt, to_ping, pinged, post_content_filtered) 
            VALUES " . str_replace('\\', '', $value));
    }

    function insert_ctgr_attachment_by($value) {
        $this->wpdb->query("
            INSERT INTO {$this->posts} (post_author, guid, post_title, post_mime_type, post_type, post_status, post_parent, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, post_excerpt, to_ping, pinged, post_content_filtered, post_name) 
            VALUES " . str_replace('\\', '', $value));
    }

    function get_formatted_value($url, $alt, $post_parent) {
        $alt = $alt ?? '';
        return "({$this->author}, '', '" . str_replace("'", "", $alt) . "', 'image/jpeg', 'attachment', 'inherit', '{$post_parent}', now(), now(), now(), now(), '', '', '', '', '{$url}')";
    }

    function get_ctgr_formatted_value($url, $alt, $post_parent) {
        $alt = $alt ?? '';
        return "({$this->author}, '', '" . str_replace("'", "", $alt) . "', 'image/jpeg', 'attachment', 'inherit', '{$post_parent}', now(), now(), now(), now(), '', '', '', '', '{$url}', 'fifu-category-{$post_parent}')";
    }

    /* dimensions: clean all */

    function clean_dimensions_all() {
        $this->wpdb->query("
            DELETE FROM {$this->postmeta} pm            
            WHERE pm.meta_key = '_wp_attachment_metadata'
            AND EXISTS (
                SELECT 1 
                FROM {$this->posts} p 
                WHERE p.id = pm.post_id
                AND p.post_author = {$this->author} 
            )
        ");
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
                $this->wpdb->update($this->posts, $set = array('post_title' => $alt, 'post_content_filtered' => $url), $where = array('id' => $att_id), null, null);
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
                $this->wpdb->update($this->posts, $set = array('post_content_filtered' => $url, 'post_title' => $alt), $where = array('id' => $att_id), null, null);
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
        $att_id = get_option('fifu_default_attach_id');
        if (!$att_id)
            return;
        $post_types = join("','", explode(',', str_replace(' ', '', get_option('fifu_default_cpt'))));
        $post_types ? $post_types : $this->types;
        $value = null;
        foreach ($this->get_posts_without_featured_image($post_types) as $res) {
            $aux = "({$res->id}, '_thumbnail_id', {$att_id})";
            $value = $value ? $value . ',' . $aux : $aux;
        }
        if ($value) {
            $this->insert_default_thumbnail_id($value);
            update_post_meta($att_id, '_wp_attached_file', get_option('fifu_default_url'));
        }
    }

    function update_default_url($url) {
        $att_id = get_option('fifu_default_attach_id');
        if ($url != wp_get_attachment_url($att_id)) {
            $this->wpdb->update($this->posts, $set = array('post_content_filtered' => $url), $where = array('id' => $att_id), null, null);
            update_post_meta($att_id, '_wp_attached_file', $url);
        }
    }

    function delete_default_url() {
        $att_id = get_option('fifu_default_attach_id');
        wp_delete_attachment($att_id);
        delete_option('fifu_default_attach_id');
        $this->wpdb->delete($this->postmeta, array('meta_key' => '_thumbnail_id', 'meta_value' => $att_id));
    }

    /* delete post */

    function before_delete_post($post_id) {
        $default_url_enabled = fifu_is_on('fifu_enable_default_url');
        $default_att_id = $default_url_enabled ? get_option('fifu_default_attach_id') : null;
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
            ) ENGINE=MEMORY;
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
            ) ENGINE=MEMORY;
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

        $this->wpdb->query("
            INSERT INTO {$this->fifu_meta_out} (post_ids, type)
            SELECT GROUP_CONCAT(DISTINCT id ORDER BY id SEPARATOR ','), 'att'
            FROM {$this->posts} 
            WHERE post_author = {$this->author}
            GROUP BY FLOOR(id / 5000)
        ");

        $last_insert_id = $this->wpdb->insert_id;
        if ($last_insert_id) {
            $this->log_prepare($last_insert_id, $this->fifu_meta_out);
        }

        // Create a temporary table with an AUTO_INCREMENT column to generate row numbers
        $this->wpdb->query("
            CREATE TEMPORARY TABLE temp_term_out (
                id INT AUTO_INCREMENT PRIMARY KEY,
                term_id INT
            ) ENGINE=MEMORY;
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
        $inserted_records = $this->wpdb->get_results("
            SELECT id, post_ids, type
            FROM {$table}
            WHERE id = {$last_insert_id}
        ");

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
        $result = $this->wpdb->get_results("
            SELECT post_ids
            FROM {$this->fifu_meta_in}
            WHERE id = {$id}
        ");

        $this->wpdb->query("
            DELETE FROM {$this->fifu_meta_in}
            WHERE id = {$id}
        ");

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
        $result = $this->wpdb->get_results("
            SELECT post_ids
            FROM {$this->fifu_meta_out}
            WHERE id = {$id}
        ");

        $this->wpdb->query("
            DELETE FROM {$this->fifu_meta_out}
            WHERE id = {$id}
        ");

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

        $this->wpdb->query('START TRANSACTION');

        try {
            $fake_attach_id = get_option('fifu_fake_attach_id');
            $fake_attach_sql = $fake_attach_id ? "OR meta_value = {$fake_attach_id}" : "";

            $default_attach_id = get_option('fifu_default_attach_id');
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
        $result = $this->wpdb->get_results("
            SELECT post_ids
            FROM {$this->fifu_meta_out}
            WHERE id = {$id}
        ");

        $this->wpdb->query("
            DELETE FROM {$this->fifu_meta_out}
            WHERE id = {$id}
        ");

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

        try {
            $this->wpdb->query("
                INSERT INTO {$this->posts} (post_author, guid, post_title, post_mime_type, post_type, post_status, post_parent, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, post_excerpt, to_ping, pinged, post_content_filtered) 
                VALUES " . str_replace('\\', '', $value));

            $this->wpdb->query("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.post_parent, '_thumbnail_id', p.id 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids}) 
                    AND p.post_author = {$this->author} 
                )
            ");

            $this->wpdb->query("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attached_file', p.post_content_filtered
                    FROM {$this->posts} p 
                    WHERE p.post_parent IN ({$ids}) 
                    AND p.post_author = {$this->author} 
                )
            ");

            $this->wpdb->query("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attachment_image_alt', p.post_title 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids}) 
                    AND p.post_author = {$this->author} 
                    AND p.post_title IS NOT NULL 
                    AND p.post_title != ''
                )
            ");

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function delete_attmeta2($ids) {
        $this->wpdb->query('START TRANSACTION');

        try {
            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key = '_thumbnail_id' 
                AND meta_value IN (0, {$ids})
            ");

            $this->wpdb->query("
                DELETE FROM {$this->posts} 
                WHERE id IN ({$ids})
                AND post_author = {$this->author}
            ");

            $this->wpdb->query("
                DELETE FROM {$this->postmeta} 
                WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata') 
                AND post_id IN ({$ids})
            ");

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function delete_termmeta2($ids) {
        $this->wpdb->query('START TRANSACTION');

        try {
            $this->wpdb->query("
                DELETE FROM {$this->termmeta} 
                WHERE meta_key = 'thumbnail_id' 
                AND term_id IN ({$ids})
            ");

            $this->wpdb->query("
                DELETE pm
                FROM {$this->postmeta} pm JOIN {$this->posts} p ON pm.post_id = p.id
                WHERE pm.meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
                AND p.post_parent IN ({$ids})
                AND p.post_author = {$this->author} 
                AND p.post_name LIKE 'fifu-category%'
            ");

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function insert_termmeta($id) {
        $result = $this->wpdb->get_results("
            SELECT post_ids
            FROM {$this->fifu_meta_in}
            WHERE id = {$id}
        ");

        $this->wpdb->query("
            DELETE FROM {$this->fifu_meta_in}
            WHERE id = {$id}
        ");

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

        try {
            $this->wpdb->query("
                INSERT INTO {$this->posts} (post_author, guid, post_title, post_mime_type, post_type, post_status, post_parent, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, post_excerpt, to_ping, pinged, post_content_filtered, post_name) 
                VALUES " . str_replace('\\', '', $value));

            $this->wpdb->query("
                INSERT INTO {$this->termmeta} (term_id, meta_key, meta_value) (
                    SELECT p.post_parent, 'thumbnail_id', p.id 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids}) 
                    AND p.post_author = {$this->author} 
                    AND p.post_name LIKE 'fifu-category%'
                )
            ");

            $this->wpdb->query("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attached_file', p.post_content_filtered
                    FROM {$this->posts} p 
                    WHERE p.post_parent IN ({$ids}) 
                    AND p.post_author = {$this->author} 
                    AND p.post_name LIKE 'fifu-category%'
                )
            ");

            $this->wpdb->query("
                INSERT INTO {$this->postmeta} (post_id, meta_key, meta_value) (
                    SELECT p.id, '_wp_attachment_image_alt', p.post_title 
                    FROM {$this->posts} p
                    WHERE p.post_parent IN ({$ids}) 
                    AND p.post_author = {$this->author} 
                    AND p.post_title IS NOT NULL 
                    AND p.post_title != ''
                    AND p.post_name LIKE 'fifu-category%'
                )
            ");

            $this->wpdb->query('COMMIT');
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    function get_fifu_fields($ids) {
        $results = $this->wpdb->get_results("
            SELECT post_id, meta_key, meta_value
            FROM {$this->postmeta}
            WHERE post_id IN ({$ids})
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
        $url = $meta_data['fifu_image_url'];

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
    $aux = $db->get_count_urls()[0];
    return $aux ? $aux->amount : 0;
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

function fifu_db_get_all_urls($page) {
    $db = new FifuDb();
    return $db->get_all_urls($page);
}

function fifu_db_get_all_hex_ids() {
    $db = new FifuDb();
    return $db->get_all_hex_ids();
}

function fifu_db_get_posts_with_internal_featured_image($page) {
    $db = new FifuDb();
    return $db->get_posts_with_internal_featured_image($page);
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

