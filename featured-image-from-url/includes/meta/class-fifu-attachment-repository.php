<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Repository for retrieving FIFU attachment references.
 */
class Fifu_Attachment_Repository {

    /** @var wpdb */
    private wpdb $wpdb;

    /** @var string */
    private string $posts_table;

    /** @var string */
    private string $postmeta_table;

    /** @var string */
    private string $termmeta_table;

    public function __construct(?wpdb $wpdb_instance = null) {
        $this->wpdb = $wpdb_instance ?? $GLOBALS['wpdb'];
        $this->posts_table = $this->wpdb->posts;
        $this->postmeta_table = $this->wpdb->postmeta;
        $this->termmeta_table = $this->wpdb->termmeta;
    }

    private function getAuthorId(): int {
        return (int) Fifu_Options_Utils::get_author();
    }

    /**
     * Find an attachment ID by parent post, file path and category flag.
     *
     * @param int    $post_parent
     * @param string $file
     * @param bool   $is_category
     * @return int|null
     */
    public function find_attachment_id(int $post_parent, string $file, bool $is_category): ?int {
        $ctgr_sql = $is_category ? "AND p.post_name LIKE 'fifu-category%'" : '';

        $sql = $this->wpdb->prepare(
            "SELECT pm.post_id
            FROM {$this->postmeta_table} pm
            WHERE pm.meta_key = '_wp_attached_file'
              AND pm.meta_value = %s
              AND pm.post_id IN (
                  SELECT p.id
                  FROM {$this->posts_table} p 
                  WHERE p.post_parent = %d
                    AND post_author = %d {$ctgr_sql}
              )
            LIMIT 1",
            $file,
            $post_parent,
            $this->getAuthorId()
        );

        $row = $this->wpdb->get_row($sql);
        return $row ? (int) $row->post_id : null;
    }

    /**
     * Check whether the given attachment ID was created by FIFU.
     *
     * @param int $attachment_id
     * @return bool
     */
    public function is_fifu_attachment(int $attachment_id): bool {
        if ($attachment_id <= 0) {
            return false;
        }

        $sql = $this->wpdb->prepare(
            "SELECT 1
             FROM {$this->posts_table}
             WHERE id = %d
               AND post_type = 'attachment'
               AND post_author = %d",
            $attachment_id,
            $this->getAuthorId()
        );
        return (bool) $this->wpdb->get_var($sql);
    }

    /**
     * Fetch the IDs of orphan attachments that are still linked to a post.
     *
     * @param int $post_id
     * @return string|null
     */
    public function get_orphan_attachments_for_post(int $post_id): ?string {
        $sql = $this->wpdb->prepare(
            "SELECT GROUP_CONCAT(p.ID) AS ids
            FROM {$this->posts_table} p
            WHERE p.post_parent = %d
              AND p.post_author = %d
              AND p.post_name NOT LIKE %s
              AND p.post_type = 'attachment'
              AND NOT EXISTS (
                  SELECT 1
                  FROM {$this->postmeta_table} pm2
                  WHERE pm2.post_id = p.post_parent
                    AND pm2.meta_key = '_thumbnail_id'
                    AND pm2.meta_value <> ''
                    AND CAST(pm2.meta_value AS UNSIGNED) = p.ID
              )",
            $post_id,
            $this->getAuthorId(),
            'fifu-category%'
        );
        return $this->wpdb->get_var($sql) ?: null;
    }

    /**
     * Fetch the IDs of orphan attachments that are still linked to a category term.
     *
     * @param int $term_id
     * @return string|null
     */
    public function get_orphan_attachments_for_term(int $term_id): ?string {
        $sql = $this->wpdb->prepare(
            "SELECT GROUP_CONCAT(p.ID) AS ids
            FROM {$this->posts_table} p
            WHERE p.post_parent = %d
              AND p.post_author = %d
              AND p.post_name LIKE %s
              AND p.post_type = 'attachment'
              AND NOT EXISTS (
                  SELECT 1
                  FROM {$this->termmeta_table} tm
                  WHERE tm.term_id = p.post_parent
                    AND tm.meta_key = 'thumbnail_id'
                    AND tm.meta_value = p.ID
              )",
            $term_id,
            $this->getAuthorId(),
            'fifu-category%'
        );
        return $this->wpdb->get_var($sql) ?: null;
    }

}
