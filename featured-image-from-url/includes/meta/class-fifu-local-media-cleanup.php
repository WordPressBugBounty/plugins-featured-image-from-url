<?php
declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Local media cleanup helpers for FIFU.
 */
class Fifu_Local_Media_Cleanup {

    private wpdb $wpdb;

    private string $posts_table;

    private string $postmeta_table;

    private string $termmeta_table;

    private string $fifu_term_map_table;

    private string $fifu_map_table;

    private string $fifu_alt_map_table;

    private string $fifu_url_table;

    private string $fifu_alt_table;

    private string $fifu_alt_term_map_table;

    private string $fifu_key_table;

    private int $author_id;

    public function __construct(?wpdb $wpdb = null, ?int $author_id = null) {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
        $this->posts_table = $this->wpdb->posts;
        $this->postmeta_table = $this->wpdb->postmeta;
        $this->termmeta_table = $this->wpdb->termmeta;
        $prefix = $this->wpdb->prefix;
        $this->fifu_term_map_table = $prefix . 'fifu_term_map';
        $this->fifu_map_table = $prefix . 'fifu_map';
        $this->fifu_alt_map_table = $prefix . 'fifu_alt_map';
        $this->fifu_url_table = $prefix . 'fifu_url';
        $this->fifu_alt_table = $prefix . 'fifu_alt';
        $this->fifu_alt_term_map_table = $prefix . 'fifu_alt_term_map';
        $this->fifu_key_table = $prefix . 'fifu_key';
        $this->author_id = $author_id ?? (int) Fifu_Options_Utils::get_author();
    }

    /**
     * Remove attachments de imagens de produtos que não pertencem ao autor FIFU.
     *
     * Mapeia delete_not_used_local_images_from_products().
     *
     * @return int Número de anexos enviados para a lixeira ou -1 em erro.
     */
    public function delete_unused_product_images(): int {
        $this->wpdb->query('START TRANSACTION');

        try {
            $author = (int) $this->author_id;
            $sql = $this->wpdb->prepare(
                "
                SELECT p1.ID
                FROM {$this->posts_table} p1
                INNER JOIN {$this->posts_table} p2 ON p1.post_parent = p2.ID
                WHERE p1.post_type = %s
                AND p1.post_mime_type LIKE %s
                AND p1.post_author != %d
                AND p2.post_type = %s
                ",
                'attachment',
                'image%',
                $author,
                'product'
            );

            $attachment_ids = $this->wpdb->get_col($sql);

            if (empty($attachment_ids)) {
                $this->wpdb->query('COMMIT');
                return 0;
            }

            $trashed = 0;
            foreach ($attachment_ids as $attachment_id) {
                if (wp_delete_attachment((int) $attachment_id, false)) {
                    $trashed++;
                }
            }

            $this->wpdb->query('COMMIT');
            return $trashed;
        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('FIFU error trashing local product images: ' . $e->getMessage());
            return -1;
        }
    }

    /**
     * Remove metas de thumbnails e galerias que apontam para anexos inexistentes.
     */
    public function delete_orphaned_thumbnails_and_galleries(): void {
        if (
            class_exists('Fifu_Plugin_Detector')
            && Fifu_Plugin_Detector::is_multisite_global_media_active()
        ) {
            $this->wpdb->query("
                DELETE FROM {$this->postmeta_table} 
                WHERE meta_key = '_thumbnail_id' 
                AND meta_value NOT LIKE '100000%' 
                AND NOT EXISTS (
                    SELECT 1 
                    FROM {$this->posts_table} p 
                    WHERE p.id = meta_value
                )
            ");
            return;
        }

        $this->wpdb->query("
            DELETE FROM {$this->postmeta_table} 
            WHERE meta_key = '_thumbnail_id' 
            AND NOT EXISTS (
                SELECT 1 
                FROM {$this->posts_table} p 
                WHERE p.id = meta_value
            )
        ");

        $this->wpdb->query("
            DELETE FROM {$this->termmeta_table} 
            WHERE meta_key = 'thumbnail_id' 
            AND NOT EXISTS (
                SELECT 1 
                FROM {$this->posts_table} p 
                WHERE p.id = meta_value
            )
        ");

        $this->wpdb->query("
            DELETE FROM {$this->postmeta_table} 
            WHERE meta_key IN ('_product_image_gallery', '_wc_additional_variation_images')
            AND NOT EXISTS (
                SELECT 1
                FROM {$this->posts_table} p
                WHERE FIND_IN_SET(p.ID, {$this->postmeta_table}.meta_value)
            )
        ");
    }

    /**
     * Delete all FIFU-related meta keys from postmeta (equivalent to "delete all FIFU URLs").
     *
     * This should mirror the legacy behavior of admin/db.php::delete_all().
     *
     * @return void
     */
    public function delete_all_fifu_meta(): void
    {
        sleep(3);
        if (!(
            Fifu_Options_Utils::is_on('fifu_run_delete_all')
            && get_option('fifu_run_delete_all_time')
            && FIFU_DELETE_ALL_URLS
        )) {
            return;
        }

        if ($this->wpdb->query('START TRANSACTION') === false) {
            return;
        }

        try {
            $deleted_rows = $this->wpdb->query("
                DELETE FROM {$this->postmeta_table}
                WHERE meta_key LIKE 'fifu_%'
            ");

            if ($deleted_rows === false) {
                $this->wpdb->query('ROLLBACK');
                return;
            }

            $deleted_term_rows = $this->wpdb->query("
                DELETE FROM {$this->termmeta_table}
                WHERE meta_key LIKE 'fifu_%'
            ");

            if ($deleted_term_rows === false) {
                $this->wpdb->query('ROLLBACK');
                return;
            }

            $has_fifu_map = $this->table_exists($this->fifu_map_table);
            $has_fifu_term_map = $this->table_exists($this->fifu_term_map_table);
            $has_fifu_alt_map = $this->table_exists($this->fifu_alt_map_table);
            $has_fifu_alt_term_map = $this->table_exists($this->fifu_alt_term_map_table);
            $has_fifu_url = $this->table_exists($this->fifu_url_table);
            $has_fifu_alt = $this->table_exists($this->fifu_alt_table);

            if ($has_fifu_map && $this->wpdb->query("DELETE FROM {$this->fifu_map_table}") === false) {
                $this->wpdb->query('ROLLBACK');
                return;
            }

            if ($has_fifu_term_map && $this->wpdb->query("DELETE FROM {$this->fifu_term_map_table}") === false) {
                $this->wpdb->query('ROLLBACK');
                return;
            }

            if ($has_fifu_alt_map && $this->wpdb->query("DELETE FROM {$this->fifu_alt_map_table}") === false) {
                $this->wpdb->query('ROLLBACK');
                return;
            }

            if ($has_fifu_alt_term_map && $this->wpdb->query("DELETE FROM {$this->fifu_alt_term_map_table}") === false) {
                $this->wpdb->query('ROLLBACK');
                return;
            }

            if ($has_fifu_url && $has_fifu_map) {
                $delete_orphan_urls = "
                    DELETE u
                    FROM {$this->fifu_url_table} u
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM {$this->fifu_map_table} m
                        WHERE m.hash = u.hash
                    )
                ";

                if ($this->wpdb->query($delete_orphan_urls) === false) {
                    $this->wpdb->query('ROLLBACK');
                    return;
                }
            }

            if ($has_fifu_alt && $has_fifu_alt_map) {
                $delete_orphan_alts = "
                    DELETE a
                    FROM {$this->fifu_alt_table} a
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM {$this->fifu_alt_map_table} am
                        WHERE am.hash = a.hash
                    )
                ";

                if ($this->wpdb->query($delete_orphan_alts) === false) {
                    $this->wpdb->query('ROLLBACK');
                    return;
                }
            }

            if ($this->wpdb->query('COMMIT') === false) {
                $this->wpdb->query('ROLLBACK');
            }
        } catch (\Throwable $throwable) {
            $this->wpdb->query('ROLLBACK');
        }
    }

    /**
     * Run a full garbage cleanup:
     * - remove invalid/placeholder thumbnails
     * - remove orphan attachments/meta
     * - remove empty FIFU meta values
     * - clean video oembed cache, etc.
     *
     * Mirrors admin/db.php::delete_garbage().
     *
     * @return void
     */
    public function delete_garbage(): void
    {
        wp_cache_flush();

        $option_attachment_ids = array_values(array_unique(array_filter(
            array_map(static fn ($value): int => (int) $value, [
                get_option('fifu_fake_attach_id'),
                get_option('fifu_default_attach_id'),
            ]),
            static fn (int $id): bool => $id > 0
        )));
        $fifu_owned_attachment_ids = $this->filter_fifu_owned_attachment_ids($option_attachment_ids);

        $this->wpdb->query('START TRANSACTION');

        try {
            $fifu_owned_attachment_ids_csv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($fifu_owned_attachment_ids);
            if ($fifu_owned_attachment_ids_csv) {
                $this->delete_attachment_core_meta_by_ids($fifu_owned_attachment_ids);
                $this->wpdb->query(
                    "DELETE FROM {$this->postmeta_table}
                    WHERE post_id IN ({$fifu_owned_attachment_ids_csv})"
                );
            }

            $this->wpdb->query("
                DELETE FROM {$this->postmeta_table} 
                WHERE meta_key IN ('_thumbnail_id', '_product_image_gallery', '_wc_additional_variation_images')
                AND (
                    meta_value = -1
                    " . ($fifu_owned_attachment_ids_csv ? "OR meta_value IN ({$fifu_owned_attachment_ids_csv})" : "") . "
                    OR meta_value IS NULL 
                    OR meta_value LIKE 'fifu:%'
                )
            ");

            $this->wpdb->query("
                DELETE FROM {$this->termmeta_table}
                WHERE meta_key = 'fifu_image_url'
                AND meta_id NOT IN (
                    SELECT * FROM (
                        SELECT MAX(tm.meta_id) AS meta_id
                        FROM {$this->termmeta_table} tm
                        WHERE tm.meta_key = 'fifu_image_url'
                        GROUP BY tm.term_id
                    ) aux
                )
            ");

            $has_global_media = class_exists('Fifu_Plugin_Detector') && Fifu_Plugin_Detector::is_multisite_global_media_active();
            $global_media_sql = $has_global_media ? "AND meta_value NOT LIKE '100000%'" : "";

            $this->wpdb->query("
                DELETE FROM {$this->postmeta_table}
                WHERE meta_key = '_thumbnail_id'
                {$global_media_sql}
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$this->posts_table} p 
                    WHERE p.ID = {$this->postmeta_table}.meta_value
                )
            ");

            $this->wpdb->query("
                DELETE FROM {$this->postmeta_table}
                WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$this->posts_table} p 
                    WHERE p.ID = {$this->postmeta_table}.post_id
                )
            ");

            $this->wpdb->query("
                DELETE FROM {$this->postmeta_table}
                WHERE meta_key LIKE 'fifu_%'
                AND (
                    meta_value = ''
                    OR meta_value IS NULL
                )
            ");
            // db2 never stores empty FIFU URLs, so there is no synced cleanup required here.

            $this->wpdb->query("
                DELETE FROM {$this->termmeta_table}
                WHERE meta_key = 'thumbnail_id'
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$this->posts_table} p 
                    WHERE p.id = meta_value
                )
            ");

            // db2 never persists empty FIFU term metadata, so no synced cleanup is required here.
            $this->wpdb->query("
                DELETE FROM {$this->termmeta_table}
                WHERE meta_key LIKE 'fifu_%'
                AND (
                    meta_value = ''
                    OR meta_value IS NULL
                )
            ");

            $this->wpdb->query('COMMIT');
        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
        }

        foreach ($fifu_owned_attachment_ids as $attachment_id) {
            $this->delete_attachment_core_meta_by_ids([$attachment_id]);
            wp_delete_attachment($attachment_id, true);
            $this->delete_attachment_core_meta_by_ids([$attachment_id]);
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$this->postmeta_table} WHERE post_id = %d",
                    $attachment_id
                )
            );
        }

        $this->delete_attachment_core_meta_by_ids($fifu_owned_attachment_ids);
        delete_option('fifu_fake_attach_id');
        delete_option('fifu_default_attach_id');
    }

    /**
     * Delete empty FIFU URLs from term meta (fifu_image_url).
     *
     * @return void
     */
    public function delete_empty_category_urls(): void
    {
        $this->wpdb->query("
            DELETE FROM {$this->termmeta_table}
            WHERE meta_key = 'fifu_image_url'
            AND (
                meta_value = ''
                OR meta_value IS NULL
            )
        ");
        // db2 does not persist empty FIFU URLs, so no additional cleanup is required.
    }

    /**
     * Delete empty FIFU URLs from post meta (fifu_image_url).
     *
     * @return void
     */
    public function delete_empty_post_urls(): void
    {
        $this->wpdb->query("
            DELETE FROM {$this->postmeta_table}
            WHERE meta_key = 'fifu_image_url'
            AND (
                meta_value = ''
                OR meta_value IS NULL
            )
        ");
        // db2 does not persist empty FIFU URLs, so no additional cleanup is required.
    }

    /**
     * Delete "fifu_image_url" for a given term ID.
     *
     * @param int $term_id
     * @return void
     */
    public function delete_image_url_category(int $term_id): void
    {
        $term_id = (int) $term_id;

        if (!function_exists('fifu_db2_mode')) {
            $this->delete_image_url_category_legacy($term_id);
            return;
        }

        $mode = fifu_db2_mode();

        if ($mode === Fifu_Db2_Mode::MODE_DB2 || $mode === Fifu_Db2_Mode::MODE_HYBRID) {
            if ($this->delete_image_url_category_db2($term_id)) {
                return;
            }
        }

        $this->delete_image_url_category_legacy($term_id);
    }

    /**
     * Legacy delete path (termmeta).
     *
     * @param int $term_id
     * @return void
     */
    private function delete_image_url_category_legacy(int $term_id): void
    {
        $termmeta = $this->termmeta_table;

        $sql = $this->wpdb->prepare(
            "DELETE FROM {$termmeta} WHERE term_id = %d AND meta_key = 'fifu_image_url'",
            $term_id
        );
        $deleted_rows = $this->wpdb->query($sql);

        if (
            $deleted_rows !== false
            && $deleted_rows > 0
            && function_exists('fifu_db2_write_service')
        ) {
            $write_service = fifu_db2_write_service();
            if ($write_service instanceof Fifu_Db2_Write_Service) {
                // Keep the db2 term mapping synchronized whenever the legacy entry is removed.
                $write_service->delete_term_image($term_id);
            }
        }
    }

    /**
     * DB2 delete path for term images.
     *
     * @param int $term_id
     * @return bool True when DB2 deletion executed; false when fallback is needed.
     */
    private function delete_image_url_category_db2(int $term_id): bool
    {
        if (!$this->has_term_image_db2_tables()) {
            return false;
        }

        $termMap = $this->fifu_term_map_table;
        $keyTable = $this->fifu_key_table;

        $sql = $this->wpdb->prepare(
            "DELETE tm
            FROM {$termMap} tm
            INNER JOIN {$keyTable} k ON k.key_id = tm.key_id
            WHERE tm.term_id = %d
              AND k.key_type = 'image'",
            $term_id
        );

        $result = $this->wpdb->query($sql);
        if ($result === false) {
            return false;
        }

        $this->delete_image_url_category_legacy($term_id);
        return true;
    }

    /**
     * Checks that the term DB2 tables needed for image storage exist.
     *
     * @return bool
     */
    private function has_term_image_db2_tables(): bool
    {
        return $this->table_exists($this->fifu_term_map_table)
            && $this->table_exists($this->fifu_key_table);
    }

    /**
     * Determines whether a given table exists in the database.
     *
     * @param string $table
     * @return bool
     */
    private function table_exists(string $table): bool
    {
        $sql = $this->wpdb->prepare("SHOW TABLES LIKE %s", $table);
        $result = $this->wpdb->get_var($sql);
        return $result !== null;
    }

    /**
     * Keep only attachment IDs owned by the FIFU author.
     *
     * @param int[] $attachment_ids
     * @return int[]
     */
    private function filter_fifu_owned_attachment_ids(array $attachment_ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $attachment_ids),
            static fn (int $id): bool => $id > 0
        )));

        if (!$ids) {
            return [];
        }

        $ids_csv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($ids);
        if (!$ids_csv) {
            return [];
        }

        $owned_ids = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT ID
                 FROM {$this->posts_table}
                 WHERE ID IN ({$ids_csv})
                   AND post_type = %s
                   AND post_author = %d",
                'attachment',
                $this->author_id
            )
        );

        return array_values(array_unique(array_filter(
            array_map('intval', (array) $owned_ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    /**
     * Delete attachment core meta rows for a list of attachment IDs.
     *
     * @param int[] $attachment_ids
     * @return void
     */
    private function delete_attachment_core_meta_by_ids(array $attachment_ids): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $attachment_ids),
            static fn (int $id): bool => $id > 0
        )));

        if (!$ids) {
            return;
        }

        $ids_csv = method_exists('Fifu_Db2_Sql_Helper', 'sanitize_ids_csv')
            ? Fifu_Db2_Sql_Helper::sanitize_ids_csv($ids)
            : implode(',', $ids);

        if (!$ids_csv) {
            return;
        }

        foreach ($ids as $attachment_id) {
            delete_post_meta($attachment_id, '_wp_attached_file');
            delete_post_meta($attachment_id, '_wp_attachment_image_alt');
            delete_post_meta($attachment_id, '_wp_attachment_metadata');
        }

        $this->wpdb->query(
            "DELETE FROM {$this->postmeta_table}
            WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
            AND post_id IN ({$ids_csv})"
        );

        $this->wpdb->query(
            "DELETE FROM {$this->postmeta_table}
            WHERE post_id IN ({$ids_csv})"
        );
    }

    /**
     * Delete FIFU-owned attachments using the effective FIFU author id.
     *
     * @param int[] $attachment_ids
     * @return void
     */
    public function delete_fifu_attachments(array $attachment_ids): void
    {
        $ids_csv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($attachment_ids);
        if (!$ids_csv) {
            return;
        }

        $owned_ids = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT ID FROM {$this->posts_table} WHERE ID IN ({$ids_csv}) AND post_author = %d",
                $this->author_id
            )
        );

        if (empty($owned_ids)) {
            return;
        }

        $owned_ids = array_values(array_filter(array_map('intval', (array) $owned_ids), static fn (int $id): bool => $id > 0));
        $owned_ids_csv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($owned_ids);
        if (!$owned_ids_csv) {
            return;
        }

        $this->wpdb->query(
            "DELETE FROM {$this->postmeta_table} WHERE post_id IN ({$owned_ids_csv})"
        );
        $this->wpdb->query(
            "DELETE FROM {$this->posts_table} WHERE ID IN ({$owned_ids_csv}) AND post_author = " . (int) $this->author_id
        );

        foreach ($owned_ids as $owned_id) {
            clean_post_cache($owned_id);
            wp_cache_delete($owned_id, 'post_meta');
        }
    }

    /**
     * Delete attachments regardless of author.
     *
     * @param int[] $attachment_ids
     * @return void
     */
    public function delete_any_attachments(array $attachment_ids): void
    {
        $ids_csv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($attachment_ids);
        if (!$ids_csv) {
            return;
        }

        $this->wpdb->query("DELETE FROM {$this->posts_table} WHERE id IN ({$ids_csv})");
    }

    /**
     * Delete attachment file/alt/meta for a given list of attachment IDs.
     *
     * @param int[] $attachment_ids
     * @return void
     */
    public function delete_attachment_file_and_alt(array $attachment_ids): void
    {
        $ids_csv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($attachment_ids);
        if (!$ids_csv) {
            return;
        }

        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->postmeta_table}
            WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
              AND post_id IN ({$ids_csv})
              AND EXISTS (
                SELECT 1 FROM {$this->posts_table} WHERE id = post_id AND post_author = %d
              )",
            $this->author_id
        );
        $this->wpdb->query($sql);
    }

    /**
     * Delete attachment file/alt/metadata rows by parent post IDs, optionally limited to FIFU category posts.
     *
     * Mirrors the behavior of the legacy delete_attachment_meta($ids, $is_ctgr) method in admin/db.php.
     *
     * @param int[] $parent_ids
     * @param bool  $is_category
     * @return void
     */
    public function delete_attachment_meta_by_parent_ids(array $parent_ids, bool $is_category): void
    {
        $ids_csv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($parent_ids);
        if (!$ids_csv) {
            return;
        }

        $ctgr_sql = $is_category ? "AND p.post_name LIKE 'fifu-category%'" : "";

        $sql = "
            DELETE pm
            FROM {$this->postmeta_table} pm
            JOIN {$this->posts_table} p ON pm.post_id = p.id
            WHERE pm.meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
            AND p.post_parent IN ({$ids_csv})
            AND p.post_author = %d 
            {$ctgr_sql}
        ";

        $this->wpdb->query(
            $this->wpdb->prepare($sql, $this->author_id)
        );
    }

    /**
     * Delete attachments and their meta records in one reusable operation.
     *
     * @param int[] $attachment_ids
     * @return void
     */
    public function delete_attachments_and_meta(array $attachment_ids): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $attachment_ids),
            static fn(int $id): bool => $id > 0
        )));

        if (!$ids) {
            return;
        }

        foreach ($ids as $attachment_id) {
            if ((int) get_post_field('post_author', $attachment_id) !== $this->author_id) {
                continue;
            }

            delete_post_meta($attachment_id, '_wp_attached_file');
            delete_post_meta($attachment_id, '_wp_attachment_image_alt');
            delete_post_meta($attachment_id, '_wp_attachment_metadata');

            wp_delete_attachment($attachment_id, true);
            clean_post_cache($attachment_id);
            wp_cache_delete($attachment_id, 'post_meta');
        }
    }
}
