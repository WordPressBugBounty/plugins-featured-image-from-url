<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-fifu-attachment-factory.php';
require_once __DIR__ . '/class-fifu-legacy-meta-repository.php';
require_once __DIR__ . '/class-fifu-metadata-db2-cleanup-guard.php';
require_once __DIR__ . '/class-fifu-metadata-queue-repository.php';

final class Fifu_Metadata_Import_Service {

    /**
     * Queue metadata records that should be restored for posts or categories.
     *
     * This method prepares the incoming metadata queue by enqueuing the provided IDs,
     * allowing the import pipeline to later hydrate the correct post or term meta records.
     *
     * @param string $postIdsCsv Comma separated list of IDs that require metadata restoration.
     * @param bool   $isCategory Whether the IDs belong to categories/terms instead of posts.
     *
     * @return void
     */
    public static function prepare_meta_in_queue(
        string $postIdsCsv,
        bool $isCategory
    ): void {
        $wpdb = self::get_wpdb();
        $queueRepository = self::get_metadata_queue_repository();
        $metaInTable = $wpdb->prefix . 'fifu_meta_in';
        $postmetaTable = $wpdb->postmeta;
        $termmetaTable = $wpdb->termmeta;
        $mapTable = $wpdb->prefix . 'fifu_map';
        $keyTable = $wpdb->prefix . 'fifu_key';
        $urlTable = $wpdb->prefix . 'fifu_url';

        $wpdb->query("SET SESSION group_concat_max_len = 1048576;");

        $importIds = $postIdsCsv ? "a.post_id IN ({$postIdsCsv}) AND" : "";
        $lastInsertId = null;
        $useDb2Meta = self::can_use_db2_post_meta($wpdb, $mapTable, $keyTable, $urlTable);

        if (!$postIdsCsv || ($importIds && !$isCategory)) {
            $wpdb->query("
                CREATE TEMPORARY TABLE temp_post_in (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    post_id INT
                );
            ");
            $wpdb->query("
                INSERT INTO temp_post_in (post_id)
                SELECT DISTINCT a.post_id
                FROM {$postmetaTable} AS a
                WHERE {$importIds}
                a.meta_key IN ('fifu_image_url')
                AND a.meta_value IS NOT NULL
                AND a.meta_value <> ''
                AND NOT EXISTS (
                    SELECT 1 
                    FROM {$postmetaTable} AS b
                    WHERE a.post_id = b.post_id
                    AND b.meta_key = '_thumbnail_id'
                    AND b.meta_value <> 0
                )
                ORDER BY a.post_id;
            ");

            if ($useDb2Meta) {
                $db2ImportIds = $postIdsCsv ? "m.post_id IN ({$postIdsCsv}) AND" : '';
                $wpdb->query("
                    INSERT IGNORE INTO temp_post_in (post_id)
                    SELECT DISTINCT m.post_id
                    FROM {$mapTable} AS m
                    INNER JOIN {$keyTable} AS k ON k.key_id = m.key_id
                    INNER JOIN {$urlTable} AS u ON u.hash = m.hash
                    WHERE {$db2ImportIds}
                    k.key_type IN ('image')
                    AND m.key_index = 0
                    AND u.url IS NOT NULL
                    AND u.url <> ''
                    AND NOT EXISTS (
                        SELECT 1
                        FROM {$postmetaTable} AS b
                        WHERE m.post_id = b.post_id
                        AND b.meta_key = '_thumbnail_id'
                        AND b.meta_value <> 0
                    )
                    ORDER BY m.post_id;
                ");
            }
            $wpdb->query("
                INSERT INTO {$metaInTable} (post_ids, type)
                SELECT GROUP_CONCAT(post_id ORDER BY post_id SEPARATOR ','), 'post'
                FROM temp_post_in
                GROUP BY FLOOR((id - 1) / 5000);
            ");

            $wpdb->query("DROP TEMPORARY TABLE temp_post_in;");

            $lastInsertId = $wpdb->insert_id;
            if ($lastInsertId) {
                $queueRepository->log_prepare($lastInsertId, $metaInTable);
            }

        }

        $importIds = $postIdsCsv ? "a.term_id IN ({$postIdsCsv}) AND" : "";

        $sentTable = $wpdb->prefix . 'fifu_sent';
        $sentEventTable = $wpdb->prefix . 'fifu_sent_event';

        $useDb2Sent = false;
        if (function_exists('fifu_db2_mode')) {
            $mode = fifu_db2_mode();
            if ($mode === Fifu_Db2_Mode::MODE_DB2 || $mode === Fifu_Db2_Mode::MODE_HYBRID) {
                if (self::table_exists($wpdb, $sentTable) && self::table_exists($wpdb, $sentEventTable)) {
                    $useDb2Sent = true;
                }
            }
        }

        if (!$postIdsCsv || ($importIds && $isCategory)) {
            $wpdb->query("
                CREATE TEMPORARY TABLE temp_term_in (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    term_id INT
                );
            ");
            $wpdb->query("
                INSERT INTO temp_term_in (term_id)
                SELECT DISTINCT a.term_id
                FROM {$termmetaTable} AS a
                WHERE {$importIds}
                a.meta_key IN ('fifu_image_url')
                AND a.meta_value IS NOT NULL
                AND a.meta_value <> ''
                AND NOT EXISTS (
                    SELECT 1 
                    FROM {$termmetaTable} AS b
                    WHERE a.term_id = b.term_id 
                    AND (
                        (b.meta_key = 'thumbnail_id' AND b.meta_value <> 0)
                        OR b.meta_key IN ('fifu_metadataterm_sent')
                    )
                )
                " . ($useDb2Sent ? "
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$sentTable} s
                    INNER JOIN {$sentEventTable} e ON e.id = s.event_id
                    WHERE s.object_type = 'term'
                      AND s.object_id = a.term_id
                      AND e.event_key = 'metadataterm'
                      AND s.sent = 1
                )
                " : "") . "
                ORDER BY a.term_id;
            ");

            $termMapTable = $wpdb->prefix . 'fifu_term_map';
            if (self::table_exists($wpdb, $termMapTable)) {
                $db2ImportIds = $postIdsCsv ? "tm.term_id IN ({$postIdsCsv}) AND" : '';
                $wpdb->query("
                    INSERT IGNORE INTO temp_term_in (term_id)
                    SELECT DISTINCT tm.term_id
                    FROM {$termMapTable} AS tm
                    INNER JOIN {$keyTable} AS k ON k.key_id = tm.key_id
                    INNER JOIN {$urlTable} AS u ON u.hash = tm.hash
                    WHERE {$db2ImportIds}
                    k.key_type IN ('image')
                    AND u.url IS NOT NULL
                    AND u.url <> ''
                    AND NOT EXISTS (
                        SELECT 1
                        FROM {$termmetaTable} AS b
                        WHERE b.term_id = tm.term_id
                        AND (
                            (b.meta_key = 'thumbnail_id' AND b.meta_value IS NOT NULL AND b.meta_value <> '' AND b.meta_value <> '0')
                            OR b.meta_key IN ('fifu_metadataterm_sent')
                        )
                    )
                    ORDER BY tm.term_id;
                ");
            }
            $wpdb->query("
                INSERT INTO {$metaInTable} (post_ids, type)
                SELECT GROUP_CONCAT(term_id ORDER BY term_id SEPARATOR ','), 'term'
                FROM temp_term_in
                GROUP BY FLOOR((id - 1) / 5000);
            ");

            $wpdb->query("DROP TEMPORARY TABLE temp_term_in;");

            $previousInsertId = $lastInsertId;
            $lastInsertId = $wpdb->insert_id;
            if ($lastInsertId && $previousInsertId !== $lastInsertId) {
                $queueRepository->log_prepare($lastInsertId, $metaInTable);
            }
        }

        }

    /**
     * Queue metadata records that should be cleaned for posts or categories.
     *
     * The queued IDs are used later to remove obsolete metadata and attachments
     * with the appropriate handling for posts versus term records.
     *
     * @param string $postIdsCsv Comma separated list of IDs that require metadata cleanup.
     * @param bool   $isCategory Whether the IDs belong to categories/terms instead of posts.
     *
     * @return void
     */
    public static function prepare_meta_out_queue(
        string $postIdsCsv,
        bool $isCategory
    ): void {
        $wpdb = self::get_wpdb();
        $queueRepository = self::get_metadata_queue_repository();
        $metaOutTable = $wpdb->prefix . 'fifu_meta_out';
        $postsTable = $wpdb->posts;
        $wpdb->query("SET SESSION group_concat_max_len = 1048576;");

        $importIds = $postIdsCsv ? "post_parent IN ({$postIdsCsv}) AND" : "";
        $categoryClause = $isCategory ? "post_name LIKE 'fifu-category%' AND" : "";

        $wpdb->query($wpdb->prepare("
            INSERT INTO {$metaOutTable} (post_ids, type)
            SELECT GROUP_CONCAT(DISTINCT id ORDER BY id SEPARATOR ','), 'att'
            FROM {$postsTable} 
            WHERE {$importIds}
            {$categoryClause}
            post_author = %d
            GROUP BY FLOOR(id / 5000)
        ", (int) Fifu_Options_Utils::get_author()));

        $lastInsertId = $wpdb->insert_id;
        if ($lastInsertId) {
            $queueRepository->log_prepare($lastInsertId, $metaOutTable);
        }

        $importIds = $postIdsCsv ? "term_id IN ({$postIdsCsv}) AND" : "";

        if (!$postIdsCsv || ($importIds && $isCategory)) {
            $wpdb->query("
                CREATE TEMPORARY TABLE temp_term_out (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    term_id INT
                );
            ");

            $wpdb->query("
                INSERT INTO temp_term_out (term_id)
                SELECT DISTINCT term_id
                FROM {$wpdb->termmeta}
                WHERE {$importIds}
                meta_key IN ('fifu_image_url')
                AND meta_value IS NOT NULL
                AND meta_value <> ''
                ORDER BY term_id;
            ");

            $wpdb->query("
                INSERT INTO {$metaOutTable} (post_ids, type)
                SELECT GROUP_CONCAT(term_id ORDER BY term_id SEPARATOR ','), 'term'
                FROM temp_term_out
                GROUP BY FLOOR((id - 1) / 5000);
            ");

            $wpdb->query("DROP TEMPORARY TABLE temp_term_out;");

            $previousInsertId = $lastInsertId;
            $lastInsertId = $wpdb->insert_id;
            if ($lastInsertId && $previousInsertId !== $lastInsertId) {
                $queueRepository->log_prepare($lastInsertId, $metaOutTable);
            }
        }
    }

    /**
     * Process a single metadata in row for a post.
     *
     * Restores attachment and metadata entries for the queue item identified by the given ID.
     *
     * @param int $queueId Identifier of the metadata in queue row to process.
     *
     * @return bool True if the row was successfully processed, false otherwise.
     */
    public static function process_post_meta_in_row(int $queueId): bool {
        $wpdb = self::get_wpdb();
        $metaInTable = $wpdb->prefix . 'fifu_meta_in';
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_ids FROM {$metaInTable} WHERE id = %d",
                $queueId
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$metaInTable} WHERE id = %d",
                $queueId
            )
        );

        Fifu_File_Logger::plugin(['insert_postmeta' => ['id' => $queueId]]);

        if (count($result) === 0) {
            return false;
        }

        $ids = $result[0]->post_ids;
        $postIds = explode(',', $ids);
        $legacyRepository = self::get_legacy_meta_repository();
        $metaData = $legacyRepository->get_fifu_fields_for_posts($postIds);
        $legacyMetaData = $legacyRepository->get_legacy_fifu_fields_for_posts($postIds);
        $legacyMetaDataToMigrate = self::filter_non_empty_legacy_rows($legacyMetaData);

        if (!self::has_any_non_empty_legacy_rows($metaData)) {
            self::decrement_metadata_counter(self::count_queue_ids($postIds));

            return true;
        }

        $attachmentFactory = new Fifu_Attachment_Factory();
        $valueArr = [];
        $existingThumbnailPostIds = self::get_existing_thumbnail_post_id_map($postIds);
        foreach ($postIds as $postId) {
            $intPostId = (int) $postId;

            if ($intPostId <= 0) {
                continue;
            }

            if (isset($existingThumbnailPostIds[$intPostId])) {
                continue;
            }

            $postMeta = $metaData[$intPostId] ?? [];
            if (!is_array($postMeta)) {
                continue;
            }

            $url = self::resolve_image_metadata_attachment_url_from_prefetched_meta($postMeta);
            if ($url === null) {
                continue;
            }

            $alt = $postMeta['fifu_image_alt'] ?? null;
            $valueArr[] = $attachmentFactory->build_insert_tuple($url, is_string($alt) ? $alt : null, $intPostId);
        }

        if (!$valueArr) {
            self::decrement_metadata_counter(self::count_queue_ids($postIds));

            return true;
        }

        $value = implode(',', $valueArr);
        wp_cache_flush();
            $service = self::get_speed_up_service();
            if ($service instanceof Fifu_Db2_Speed_Up_Service) {
                $service->restore_post_attachment_meta($value, $ids);
                if ($legacyMetaDataToMigrate) {
                    $legacyMetaDataRemaining = $legacyMetaDataToMigrate;
                    $bulkMigratedPostIds = self::migrate_simple_post_image_legacy_meta_to_db2_bulk($legacyMetaDataToMigrate);

                if ($bulkMigratedPostIds === false) {

                    /*
                     * Safety first:
                     * the bulk helper already rolled back and preserved legacy.
                     * Do not fall back to the old per-post path after a bulk transactional failure,
                     * because that could re-enter cleanup semantics for the same batch.
                     */
                    $legacyMetaDataRemaining = [];
                } else {
                    foreach (array_keys($bulkMigratedPostIds) as $migratedPostId) {
                        unset($legacyMetaDataRemaining[(int) $migratedPostId]);
                    }

                    }

                if ($legacyMetaDataRemaining) {
                    self::migrate_post_legacy_remote_meta_to_db2(
                        array_keys($legacyMetaDataRemaining),
                        $legacyMetaDataRemaining
                    );
                }
            }
        }

        self::decrement_metadata_counter(self::count_queue_ids($postIds));

        return true;
    }

    /**
     * Process a single metadata in row for a WooCommerce product.
     *
     * This method handles WooCommerce specific metadata restoration using the queue identifier.
     *
     * @param int $queueId Identifier of the WooCommerce metadata in queue row to process.
     *
     * @return bool True if the WooCommerce row was successfully processed, false otherwise.
     */
    /**
     * Process a single metadata in row for a term.
     *
     * Restores attachment and term metadata entries from the queue entry identified by the provided ID.
     *
     * @param int $queueId Identifier of the term metadata in queue row to process.
     *
     * @return bool True if the term metadata row was successfully processed, false otherwise.
     */
    public static function process_term_meta_in_row(int $queueId): bool {
        $wpdb = self::get_wpdb();
        $metaInTable = $wpdb->prefix . 'fifu_meta_in';

        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_ids FROM {$metaInTable} WHERE id = %d",
                $queueId
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$metaInTable} WHERE id = %d",
                $queueId
            )
        );

        Fifu_File_Logger::plugin(['insert_termmeta' => ['id' => $queueId]]);

        if (count($result) === 0) {
            return false;
        }

        $ids = $result[0]->post_ids;
        $termIds = explode(',', $ids);
        $legacyTermMeta = self::get_legacy_term_fifu_fields($termIds);

        $attachmentFactory = new Fifu_Attachment_Factory();
        $valueArr = [];
        foreach ($termIds as $termId) {
            $intTermId = (int) $termId;
            $termMeta = $legacyTermMeta[$intTermId] ?? array();
            if ($intTermId <= 0) {
                continue;
            }

            if (get_term_meta($intTermId, 'thumbnail_id', true)) {
                continue;
            }

            $url = self::get_db2_term_url($intTermId, 'image');

            if ($url === '') {
                $legacyImageUrl = trim((string) ($termMeta['fifu_image_url'] ?? get_term_meta($intTermId, 'fifu_image_url', true)));
                if ($legacyImageUrl !== '') {
                    $url = $legacyImageUrl;
                }
            }

            $url = htmlspecialchars_decode(trim((string) $url));
            if ($url === '') {
                continue;
            }

            $alt = self::get_db2_term_alt($intTermId, 'image');
            if ($alt === '') {
                $alt = trim((string) ($termMeta['fifu_image_alt'] ?? ''));
            }

            if ($alt === '') {
                $legacyAlt = get_term_meta($intTermId, 'fifu_image_alt', true);
                if ($legacyAlt !== '' && $legacyAlt !== null) {
                    $alt = trim((string) $legacyAlt);
                }
            }

            $aux = $attachmentFactory->build_category_insert_tuple(
                $url,
                $alt,
                $intTermId
            );
            $valueArr[] = $aux;
        }

        if (!$valueArr) {
            self::decrement_metadata_counter(self::count_queue_ids($termIds));

            return true;
        }

        $value = implode(',', $valueArr);
        wp_cache_flush();
        $service = self::get_speed_up_service();
        if ($service instanceof Fifu_Db2_Speed_Up_Service) {
            $service->restore_term_attachments($value, $ids);
            self::migrate_term_legacy_remote_meta_to_db2($termIds, $legacyTermMeta);
        }

        self::decrement_metadata_counter(self::count_queue_ids($termIds));

        return true;
    }

    /**
     * @param array<int|string>              $postIds
     * @param array<int,array<string,string>> $metaData
     * @return void
     */
    private static function migrate_post_legacy_remote_meta_to_db2(array $postIds, array $metaData): void {
        $updater = self::get_post_meta_updater();
        if (!$updater) {
            return;
        }

        $preserveKey = 'fifu_preserve_legacy_meta_until_db2_confirmation';
        foreach ($postIds as $postId) {
            $intPostId = (int) $postId;
            $meta = $metaData[$intPostId] ?? null;
            if (!is_array($meta)) {
                continue;
            }

            $allowFeaturedCleanup = !self::has_partial_db2_confirmation_for_post_featured_meta($intPostId, $meta);
            $previousPreserve = array_key_exists($preserveKey, $_REQUEST) ? $_REQUEST[$preserveKey] : null;
            $_REQUEST[$preserveKey] = true;
            try {
                self::sync_post_featured_legacy_meta_to_db2($updater, $intPostId, $meta);
            } finally {
                if ($previousPreserve === null) {
                    unset($_REQUEST[$preserveKey]);
                } else {
                    $_REQUEST[$preserveKey] = $previousPreserve;
                }
            }

            if ($allowFeaturedCleanup) {
                self::cleanup_legacy_featured_postmeta_if_db2_matches($intPostId, $meta);
            }
        }
    }

    /**
     * @param array<int|string>               $postIds
     * @param array<int,array<string,string>> $galleryMetaMap
     * @param array<int,array<string,string>> $galleryAltsMap
     * @return void
     */
    private static function migrate_term_legacy_remote_meta_to_db2(array $termIds, array $metaData): void {
        $updater = self::get_term_meta_updater();
        if (!$updater) {
            return;
        }

        $preserveKey = 'fifu_preserve_legacy_meta_until_db2_confirmation';
        foreach ($termIds as $termId) {
            $intTermId = (int) $termId;
            $meta = $metaData[$intTermId] ?? null;
            if (!is_array($meta)) {
                continue;
            }

            $allowImageCleanup = !self::has_partial_db2_confirmation_for_term_image($intTermId, $meta);
            $previousPreserve = array_key_exists($preserveKey, $_REQUEST) ? $_REQUEST[$preserveKey] : null;
            $_REQUEST[$preserveKey] = true;
            try {
                self::sync_term_legacy_meta_to_db2($updater, $intTermId, $meta);
            } finally {
                if ($previousPreserve === null) {
                    unset($_REQUEST[$preserveKey]);
                } else {
                    $_REQUEST[$preserveKey] = $previousPreserve;
                }
            }

            if ($allowImageCleanup) {
                self::cleanup_legacy_term_image_postmeta_if_db2_matches($intTermId, $meta);
            }
        }
    }

    /**
     * @param FIFU_Post_Meta_Updater $updater
     * @param int                    $postId
     * @param array<string,string>   $meta
     * @return void
     */
    private static function sync_post_featured_legacy_meta_to_db2(
        FIFU_Post_Meta_Updater $updater,
        int $postId,
        array $meta
    ): void {
        foreach (['fifu_image_url', 'fifu_image_alt'] as $metaKey) {
            $value = trim((string) ($meta[$metaKey] ?? ''));
            if ($value === '') {
                continue;
            }

            $updater->update_or_delete($postId, $metaKey, $value);
        }
    }

    /**
     * @param FIFU_Post_Meta_Updater $updater
     * @param int                    $postId
     * @param array<string,string>   $galleryMeta
     * @return array{indexedGallerySynced:bool,imageListSynced:bool}
     */
    private static function cleanup_legacy_featured_postmeta_if_db2_matches(int $postId, array $meta): void {
        if (!function_exists('get_post_meta')) {
            return;
        }

        $legacyUrl = (string) ($meta['fifu_image_url'] ?? get_post_meta($postId, 'fifu_image_url', true));
        $legacyAlt = (string) ($meta['fifu_image_alt'] ?? get_post_meta($postId, 'fifu_image_alt', true));
        $db2ImageUrls = self::get_db2_post_pipe_list($postId, 'image');
        $db2ImageAlts = self::get_db2_post_alt_pipe_list($postId, 'image');

        if (Fifu_Metadata_Db2_Cleanup_Guard::can_cleanup_url_and_alt($legacyUrl, $legacyAlt, $db2ImageUrls[0] ?? null, $db2ImageAlts[0] ?? null)) {
            delete_post_meta($postId, 'fifu_image_url');
            if ($legacyAlt !== '') {
                delete_post_meta($postId, 'fifu_image_alt');
            }
            clean_post_cache($postId);
            wp_cache_delete($postId, 'post_meta');
        }


    }

    /**
     * @param int $postId
     * @param string $legacyUrlKey
     * @param string $legacyAltKey
     * @param string $keyType
     * @return void
     */
    private static function has_partial_db2_confirmation_for_post_featured_meta(int $postId, array $meta): bool {
        $legacyAlt = trim((string) ($meta['fifu_image_alt'] ?? ''));
        if ($legacyAlt === '') {
            return false;
        }

        $db2Urls = self::get_db2_post_pipe_list($postId, 'image');
        $db2Alts = self::get_db2_post_alt_pipe_list($postId, 'image');

        return ($db2Urls !== [] || $db2Alts !== []) && !Fifu_Metadata_Db2_Cleanup_Guard::can_cleanup_url_and_alt(
            (string) ($meta['fifu_image_url'] ?? ''),
            $legacyAlt,
            $db2Urls[0] ?? null,
            $db2Alts[0] ?? null
        );
    }

    /**
     * @param int $postId
     * @return bool
     */
    private static function has_partial_db2_confirmation_for_term_image(int $termId, array $meta): bool {
        $legacyAlt = trim((string) ($meta['fifu_image_alt'] ?? ''));
        if ($legacyAlt === '') {
            return false;
        }

        $db2Url = self::get_db2_term_url($termId, 'image');
        $db2Alt = self::get_db2_term_alt($termId, 'image');

        return (($db2Url !== '' || $db2Alt !== '') && !Fifu_Metadata_Db2_Cleanup_Guard::can_cleanup_url_and_alt(
            (string) ($meta['fifu_image_url'] ?? ''),
            $legacyAlt,
            $db2Url,
            $db2Alt
        ));
    }

    /**
     * @param int $termId
     * @param array<string,string> $meta
     * @return bool
     */

    /**
     * @param int $termId
     * @param array<string,string> $meta
     * @return void
     */
    private static function cleanup_legacy_term_image_postmeta_if_db2_matches(int $termId, array $meta): void {
        if (!function_exists('get_term_meta')) {
            return;
        }

        $legacyUrl = (string) ($meta['fifu_image_url'] ?? get_term_meta($termId, 'fifu_image_url', true));
        $legacyAlt = (string) ($meta['fifu_image_alt'] ?? get_term_meta($termId, 'fifu_image_alt', true));
        $db2Url = self::get_db2_term_url($termId, 'image');
        $db2Alt = self::get_db2_term_alt($termId, 'image');

        if (!Fifu_Metadata_Db2_Cleanup_Guard::can_cleanup_url_and_alt($legacyUrl, $legacyAlt, $db2Url, $db2Alt)) {
            return;
        }

        if ($legacyUrl !== '') {
            delete_term_meta($termId, 'fifu_image_url');
        }

        if ($legacyAlt !== '') {
            delete_term_meta($termId, 'fifu_image_alt');
        }

        clean_term_cache($termId);
        wp_cache_delete($termId, 'term_meta');
    }

    /**
     * @param int    $postId
     * @param string $metaKey
     * @return void
     */
    private static function get_db2_post_alt_pipe_list(int $postId, string $keyType): array {
        if (!function_exists('fifu_db2_manager') || !class_exists('Fifu_Db2_Manager', false)) {
            return [];
        }

        $manager = fifu_db2_manager();
        if (!$manager instanceof Fifu_Db2_Manager) {
            return [];
        }

        $mappings = $manager->getPostAltMappings($postId, $keyType);
        if (!is_array($mappings) || $mappings === []) {
            return [];
        }

        $alts = [];
        foreach ($mappings as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            if (isset($mapping['key_type']) && (string) $mapping['key_type'] !== $keyType) {
                continue;
            }

            $alt = trim((string) ($mapping['alt'] ?? ''));
            if ($alt === '') {
                continue;
            }

            $index = isset($mapping['key_index']) ? (int) $mapping['key_index'] : 0;
            if ($index < 0) {
                continue;
            }

            $alts[$index] = $alt;
        }

        ksort($alts);

        return $alts;
    }

    /**
     * Returns DB2 post URL mappings indexed by key_index.
     *
     * @param int $postId
     * @param string $keyType
     * @return array<int, string>
     */
    private static function get_db2_term_url(int $termId, string $keyType): string {
        if (!function_exists('fifu_db2_manager') || !class_exists('Fifu_Db2_Manager', false)) {
            return '';
        }

        $manager = fifu_db2_manager();
        if (!$manager instanceof Fifu_Db2_Manager) {
            return '';
        }

        $mapping = $manager->getTermMapping($termId, $keyType);
        if (!is_array($mapping)) {
            return '';
        }

        return trim((string) ($mapping['url'] ?? ''));
    }

    /**
     * @param int $termId
     * @param string $keyType
     * @return string
     */
    private static function get_db2_term_alt(int $termId, string $keyType): string {
        if (!function_exists('fifu_db2_manager') || !class_exists('Fifu_Db2_Manager', false)) {
            return '';
        }

        $manager = fifu_db2_manager();
        if (!$manager instanceof Fifu_Db2_Manager) {
            return '';
        }

        if (!method_exists($manager, 'getTermAltMapping')) {
            return '';
        }

        $mapping = $manager->getTermAltMapping($termId, $keyType);
        if (!is_array($mapping)) {
            return '';
        }

        return trim((string) ($mapping['alt'] ?? ''));
    }

    /**
     * @param array<int,array<string,string>> $rowsById
     * @return bool
     */
    private static function has_any_non_empty_legacy_rows(array $rowsById): bool {
        foreach ($rowsById as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Build the remaining post IDs that still need the slow Woo legacy migration fallback.
     *
     * Placeholder rows returned by legacy loaders must not count as work. This keeps
     * DB2-only Image Metadata regeneration from entering migrate_woo_legacy_remote_meta_to_db2()
     * when there are no meaningful legacy values left to migrate.
     *
     * @param array<int,array<string,mixed>> $featuredMetaMap
     * @param array<int,array<string,mixed>> $galleryMetaMap
     * @param array<int,array<string,mixed>> $galleryAltsMap
     * @param array<int,mixed>               $hasIndexedGalleryUrlsMap
     * @return int[]
     */
    private static function get_existing_thumbnail_post_id_map(array $postIds): array {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        if ($idsCsv === '0') {
            return [];
        }

        $wpdb = self::get_wpdb();
        $rows = $wpdb->get_col("
            SELECT DISTINCT post_id
            FROM {$wpdb->postmeta}
            WHERE post_id IN ({$idsCsv})
              AND meta_key = '_thumbnail_id'
              AND meta_value IS NOT NULL
              AND meta_value <> ''
              AND meta_value <> '0'
        ");

        if (!is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $postId) {
            $intPostId = (int) $postId;
            if ($intPostId > 0) {
                $map[$intPostId] = true;
            }
        }

        return $map;
    }

    /**
     * Resolve the image URL used to create the Image Metadata attachment using only
     * metadata that was already preloaded for the current batch.
     *
     * Do not call Fifu_Post_Main_Image_Resolver here. That resolver is correct for
     * normal per-post reads, but too expensive inside 5k Image Metadata batches.
     *
     * @param array<string,string|null> $postMeta
     * @return string|null
     */
    private static function resolve_image_metadata_attachment_url_from_prefetched_meta(array $postMeta): ?string {
        return self::normalize_image_metadata_url($postMeta['fifu_image_url'] ?? null);
    }

    /**
     * @param mixed $url
     * @return string|null
     */
    private static function normalize_image_metadata_url($url): ?string {
        if (!is_string($url) && !is_numeric($url)) {
            return null;
        }

        $url = htmlspecialchars_decode((string) $url);
        $url = str_replace("'", '%27', $url);
        $url = trim($url);

        return $url !== '' ? $url : null;
    }

    /**
     * Keep only rows that contain at least one non-empty legacy value.
     *
     * @param array<int,array<string,string>> $rowsById
     * @return array<int,array<string,string>>
     */
    private static function filter_non_empty_legacy_rows(array $rowsById): array {
        $filtered = [];

        foreach ($rowsById as $id => $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    $filtered[(int) $id] = $row;
                    break;
                }
            }
        }

        return $filtered;
    }

    /**
     * Build the payload consumed by the image-list bulk migration fast path.
     *
     * @param array<int,array<string,string>> $featuredMetaMap
     * @param array<int,array<string,string>> $galleryMetaMap
     * @param array<int,array<string,string>> $galleryAltsMap
     * @return array<int,array<string,string>>
     */
    private static function migrate_simple_post_image_legacy_meta_to_db2_bulk(array $metaData) {
        $rows = self::build_simple_post_image_legacy_bulk_rows($metaData);
        if ($rows === []) {
            return [];
        }

        if (!class_exists('Fifu_Db2_Normalizer', false)) {
            return [];
        }

        $wpdb = self::get_wpdb();
        $prefix = $wpdb->prefix;

        $urlTable = $prefix . 'fifu_url';
        $keyTable = $prefix . 'fifu_key';
        $mapTable = $prefix . 'fifu_map';
        $altTable = $prefix . 'fifu_alt';
        $altMapTable = $prefix . 'fifu_alt_map';

        foreach ([$urlTable, $keyTable, $mapTable] as $requiredTable) {
            if (!self::table_exists($wpdb, $requiredTable)) {
                return [];
            }
        }

        $hasAltRows = false;
        foreach ($rows as $row) {
            if ((string) $row['alt'] !== '') {
                $hasAltRows = true;
                break;
            }
        }

        if ($hasAltRows) {
            foreach ([$altTable, $altMapTable] as $requiredAltTable) {
                if (!self::table_exists($wpdb, $requiredAltTable)) {
                    return [];
                }
            }
        }

        $imageKeyId = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT key_id FROM {$keyTable} WHERE key_type = %s LIMIT 1",
                'image'
            )
        );

        if ($imageKeyId <= 0) {
            return [];
        }

        $tmpTable = 'tmp_fifu_image_meta_migration_' . preg_replace('/[^a-zA-Z0-9_]/', '', uniqid('', true));

        $createTemp = $wpdb->query("
        CREATE TEMPORARY TABLE {$tmpTable} (
            post_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            url_hash CHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            url TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            alt_hash CHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
            alt TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
            KEY idx_url_hash (url_hash),
            KEY idx_alt_hash (alt_hash)
        ) ENGINE=InnoDB
    ");

        if ($createTemp === false) {
            return false;
        }

        if (!self::insert_simple_post_image_legacy_stage_rows($tmpTable, $rows)) {
            $wpdb->query("DROP TEMPORARY TABLE IF EXISTS {$tmpTable}");
            return false;
        }

        $expectedUrlCount = count($rows);
        $expectedAltCount = 0;
        foreach ($rows as $row) {
            if ((string) $row['alt'] !== '') {
                $expectedAltCount++;
            }
        }

        $migratedMap = [];
        foreach ($rows as $row) {
            $migratedMap[(int) $row['post_id']] = true;
        }

        $oldSuppressErrors = $wpdb->suppress_errors();
        $wpdb->suppress_errors(true);
        $wpdb->query('START TRANSACTION');

        try {
            $result = $wpdb->query("
            INSERT INTO {$urlTable} (hash, url, w, h)
            SELECT t.url_hash, t.url, NULL, NULL
            FROM {$tmpTable} t
            ON DUPLICATE KEY UPDATE
                url = VALUES(url)
        ");

            if ($result === false) {
                throw new RuntimeException('Failed to bulk insert DB2 image URLs.');
            }

            $result = $wpdb->query(
                $wpdb->prepare(
                    "
                INSERT INTO {$mapTable} (post_id, key_id, key_index, hash)
                SELECT t.post_id, %d, 0, t.url_hash
                FROM {$tmpTable} t
                ON DUPLICATE KEY UPDATE hash = VALUES(hash)
                ",
                    $imageKeyId
                )
            );

            if ($result === false) {
                throw new RuntimeException('Failed to bulk insert DB2 image mappings.');
            }

            $confirmedUrlCount = (int) $wpdb->get_var(
                $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM {$tmpTable} t
                INNER JOIN {$mapTable} m
                    ON m.post_id = t.post_id
                   AND m.key_id = %d
                   AND m.key_index = 0
                   AND m.hash COLLATE utf8mb4_general_ci = t.url_hash COLLATE utf8mb4_general_ci
                INNER JOIN {$urlTable} u
                    ON u.hash COLLATE utf8mb4_general_ci = t.url_hash COLLATE utf8mb4_general_ci
                   AND u.url COLLATE utf8mb4_general_ci = t.url COLLATE utf8mb4_general_ci
                ",
                    $imageKeyId
                )
            );

            if ($confirmedUrlCount !== $expectedUrlCount) {
                throw new RuntimeException(
                    sprintf(
                        'DB2 image URL confirmation mismatch. expected=%d actual=%d',
                        $expectedUrlCount,
                        $confirmedUrlCount
                    )
                );
            }

            if ($expectedAltCount > 0) {
                $result = $wpdb->query("
                INSERT IGNORE INTO {$altTable} (hash, alt)
                SELECT DISTINCT t.alt_hash, t.alt
                FROM {$tmpTable} t
                WHERE t.alt <> ''
            ");

                if ($result === false) {
                    throw new RuntimeException('Failed to bulk insert DB2 image ALTs.');
                }

                $result = $wpdb->query(
                    $wpdb->prepare(
                        "
                    INSERT INTO {$altMapTable} (post_id, key_id, key_index, hash)
                    SELECT t.post_id, %d, 0, t.alt_hash
                    FROM {$tmpTable} t
                    WHERE t.alt <> ''
                    ON DUPLICATE KEY UPDATE hash = VALUES(hash)
                    ",
                        $imageKeyId
                    )
                );

                if ($result === false) {
                    throw new RuntimeException('Failed to bulk insert DB2 image ALT mappings.');
                }

                $confirmedAltCount = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "
                    SELECT COUNT(*)
                    FROM {$tmpTable} t
                    INNER JOIN {$altMapTable} m
                        ON m.post_id = t.post_id
                       AND m.key_id = %d
                       AND m.key_index = 0
                       AND m.hash COLLATE utf8mb4_general_ci = t.alt_hash COLLATE utf8mb4_general_ci
                    INNER JOIN {$altTable} a
                        ON a.hash COLLATE utf8mb4_general_ci = t.alt_hash COLLATE utf8mb4_general_ci
                       AND a.alt COLLATE utf8mb4_general_ci = t.alt COLLATE utf8mb4_general_ci
                    WHERE t.alt <> ''
                    ",
                        $imageKeyId
                    )
                );

                if ($confirmedAltCount !== $expectedAltCount) {
                    throw new RuntimeException(
                        sprintf(
                            'DB2 image ALT confirmation mismatch. expected=%d actual=%d',
                            $expectedAltCount,
                            $confirmedAltCount
                        )
                    );
                }
            }

            /*
             * Legacy cleanup is intentionally inside the same transaction as the DB2 writes.
             * If this delete fails, rollback also reverts the DB2 writes above.
             */
            $result = $wpdb->query("
            DELETE pm
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$tmpTable} t ON t.post_id = pm.post_id
            WHERE pm.meta_key IN ('fifu_image_url', 'fifu_image_alt')
        ");

            if ($result === false) {
                throw new RuntimeException('Failed to bulk delete legacy featured image metadata.');
            }

            $wpdb->query('COMMIT');
        } catch (Throwable $throwable) {
            $wpdb->query('ROLLBACK');
            $wpdb->query("DROP TEMPORARY TABLE IF EXISTS {$tmpTable}");
            $wpdb->suppress_errors($oldSuppressErrors);
            return false;
        }

        $wpdb->query("DROP TEMPORARY TABLE IF EXISTS {$tmpTable}");
        $wpdb->suppress_errors($oldSuppressErrors);

        self::clear_post_meta_cache_for_post_ids(array_keys($migratedMap));
        foreach (array_keys($migratedMap) as $migratedPostId) {
            $altRows = $wpdb->get_results($wpdb->prepare(
                "SELECT m.key_index, a.alt
                 FROM {$altMapTable} m
                 INNER JOIN {$altTable} a ON a.hash = m.hash
                 WHERE m.post_id = %d
                   AND m.key_id = %d
                 ORDER BY m.key_index ASC",
                (int) $migratedPostId,
                $imageKeyId
            ), ARRAY_A);
        }

        return $migratedMap;
    }

    /**
     * Bulk-migrate clean legacy indexed slider metadata to DB2 atomically.
     *
     * @param array<int,array<string,string>> $metaData
     * @return array<int,bool>|false
     */


    /**
     * Load indexed slider metadata directly from wp_postmeta for bulk migration.
     *
     * @param array<int> $postIds
     * @return array<int,array<string,string>>
     */


    /**
     * Hydrate Woo featured metadata from clean indexed slider payloads.
     *
     * @param array<int,array<string,string>> $legacyFeaturedMetaMap
     * @param array<int,array<string,string>> $legacyGalleryMetaMap
     */


    /**
     * Bulk-migrate simple legacy image-list metadata to DB2 atomically.
     *
     * Fast path only:
     * - image/indexed image/list image fields;
     * - optional image/indexed/list ALT fields;
     * - no video;
     * - no slider;
     * - no custom video.
     *
     * @param array<int,array<string,string>> $metaData
     * @return array<int,bool>|false
     */
    private static function get_post_ids_with_db2_image_mapping(array $postIds): array {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        if ($idsCsv === '0') {
            return [];
        }

        $wpdb = self::get_wpdb();
        $prefix = $wpdb->prefix;

        $mapTable = $prefix . 'fifu_map';
        $keyTable = $prefix . 'fifu_key';

        if (!self::table_exists($wpdb, $mapTable) || !self::table_exists($wpdb, $keyTable)) {
            return [];
        }

        $rows = $wpdb->get_col("
            SELECT DISTINCT m.post_id
            FROM {$mapTable} m
            INNER JOIN {$keyTable} k ON k.key_id = m.key_id
            WHERE m.post_id IN ({$idsCsv})
              AND k.key_type = 'image'
              AND m.key_index = 0
        ");

        if (!is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $postId) {
            $intPostId = (int) $postId;
            if ($intPostId > 0) {
                $map[$intPostId] = true;
            }
        }

        return $map;
    }

    private static function get_post_ids_with_db2_image_alt_mapping(array $postIds): array {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        if ($idsCsv === '0') {
            return [];
        }

        $wpdb = self::get_wpdb();
        $prefix = $wpdb->prefix;

        $altMapTable = $prefix . 'fifu_alt_map';
        $keyTable = $prefix . 'fifu_key';

        if (!self::table_exists($wpdb, $altMapTable) || !self::table_exists($wpdb, $keyTable)) {
            return [];
        }

        $rows = $wpdb->get_col("
            SELECT DISTINCT m.post_id
            FROM {$altMapTable} m
            INNER JOIN {$keyTable} k ON k.key_id = m.key_id
            WHERE m.post_id IN ({$idsCsv})
              AND k.key_type = 'image'
              AND m.key_index = 0
        ");

        if (!is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $postId) {
            $intPostId = (int) $postId;
            if ($intPostId > 0) {
                $map[$intPostId] = true;
            }
        }

        return $map;
    }

    private static function build_simple_post_image_legacy_bulk_rows(array $metaData): array {
        $rows = [];
        $postIds = array_keys($metaData);
        $postsWithDb2ImageMapping = self::get_post_ids_with_db2_image_mapping($postIds);
        $postsWithDb2ImageAltMapping = self::get_post_ids_with_db2_image_alt_mapping($postIds);

        foreach ($metaData as $postId => $meta) {
            $intPostId = (int) $postId;
            if ($intPostId <= 0 || !is_array($meta)) {
                continue;
            }

            $legacyImageUrl = trim((string) ($meta['fifu_image_url'] ?? ''));
            if ($legacyImageUrl === '') {
                continue;
            }

            $convertedUrl = function_exists('fifu_convert')
                ? fifu_convert($legacyImageUrl)
                : $legacyImageUrl;

            $normalizedUrl = class_exists('Fifu_Db2_Normalizer', false)
                ? Fifu_Db2_Normalizer::normalize_url((string) $convertedUrl)
                : null;

            if ($normalizedUrl === null) {
                continue;
            }

            $legacyAltRaw = (string) ($meta['fifu_image_alt'] ?? '');
            $legacyAltTrimmed = trim($legacyAltRaw);
            $normalizedAlt = '';

            if ($legacyAltTrimmed !== '') {
                $normalizedAltValue = class_exists('Fifu_Db2_Normalizer', false)
                    ? Fifu_Db2_Normalizer::normalize_alt($legacyAltRaw)
                    : null;

                /*
                 * Do not bulk-clean a non-empty legacy ALT that cannot be represented
                 * safely in DB2. Keep this post in the old/safe path instead.
                 */
                if ($normalizedAltValue === null) {
                    continue;
                }

                $normalizedAlt = $normalizedAltValue;
            }

            $hasDb2ImageMapping = isset($postsWithDb2ImageMapping[$intPostId]);
            $hasDb2ImageAltMapping = isset($postsWithDb2ImageAltMapping[$intPostId]);

            if (
                ($hasDb2ImageMapping && !$hasDb2ImageAltMapping)
                || (!$hasDb2ImageMapping && $hasDb2ImageAltMapping)
            ) {
                continue;
            }

            $rows[$intPostId] = [
                'post_id' => $intPostId,
                'url_hash' => md5($normalizedUrl),
                'url' => $normalizedUrl,
                'alt_hash' => $normalizedAlt !== '' ? md5($normalizedAlt) : '',
                'alt' => $normalizedAlt,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,string>> $metaData
     * @return array<int,array{post_id:int,key_index:int,url_hash:string,url:string,alt_hash:string,alt:string}>
     */
    private static function insert_simple_post_image_legacy_stage_rows(string $tmpTable, array $rows): bool {
        if ($rows === []) {
            return true;
        }

        $wpdb = self::get_wpdb();
        $chunks = array_chunk($rows, 500, true);

        foreach ($chunks as $chunk) {
            $valuesSql = [];
            $args = [];

            foreach ($chunk as $row) {
                $valuesSql[] = '(%d, %s, %s, %s, %s)';
                $args[] = (int) $row['post_id'];
                $args[] = (string) $row['url_hash'];
                $args[] = (string) $row['url'];
                $args[] = (string) $row['alt_hash'];
                $args[] = (string) $row['alt'];
            }

            $sql = "
            INSERT INTO {$tmpTable} (post_id, url_hash, url, alt_hash, alt)
            VALUES " . implode(',', $valuesSql) . "
            ON DUPLICATE KEY UPDATE
                url_hash = VALUES(url_hash),
                url = VALUES(url),
                alt_hash = VALUES(alt_hash),
                alt = VALUES(alt)
        ";

            $result = $wpdb->query($wpdb->prepare($sql, ...$args));
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $tmpTable
     * @param array<int,array{post_id:int,key_index:int,url_hash:string,url:string,alt_hash:string,alt:string}> $rows
     * @return bool
     */
    private static function clear_post_meta_cache_for_post_ids(array $postIds): void {
        if (!function_exists('wp_cache_delete')) {
            return;
        }

        foreach ($postIds as $postId) {
            $intPostId = (int) $postId;
            if ($intPostId > 0) {
                wp_cache_delete($intPostId, 'post_meta');
            }
        }
    }
    /**
     * Returns the DB2 URL list for a post/key-type pair, ordered by key index.
     *
     * @param int $postId
     * @param string $keyType
     * @return array<int, string>
     */
    private static function get_db2_post_pipe_list(int $postId, string $keyType): array {
        if (!function_exists('fifu_db2_manager') || !class_exists('Fifu_Db2_Manager', false)) {
            return [];
        }

        $manager = fifu_db2_manager();
        if (!$manager instanceof Fifu_Db2_Manager) {
            return [];
        }

        $urls = [];
        $seenIndexes = [];
        $primaryMapping = $manager->getPostMapping($postId, $keyType, 0);
        if (is_array($primaryMapping)) {
            $primaryUrl = trim((string) ($primaryMapping['url'] ?? ''));
            if ($primaryUrl !== '') {
                $urls[0] = $primaryUrl;
                $seenIndexes[0] = true;
            }
        }

        $mappings = $manager->getPostMappings($postId, $keyType);
        if (!is_array($mappings) || $mappings === []) {
            return $urls;
        }

        usort(
            $mappings,
            static fn(array $left, array $right): int => ((int) ($left['key_index'] ?? 0)) <=> ((int) ($right['key_index'] ?? 0))
        );

        foreach ($mappings as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            if (isset($mapping['key_type']) && (string) $mapping['key_type'] !== $keyType) {
                continue;
            }

            $index = isset($mapping['key_index']) ? (int) $mapping['key_index'] : 0;
            if ($index < 0) {
                continue;
            }

            $url = trim((string) ($mapping['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            if ($index === 0 && isset($seenIndexes[0])) {
                continue;
            }

            if (isset($seenIndexes[$index])) {
                continue;
            }

            $urls[$index] = $url;
            $seenIndexes[$index] = true;
        }

        ksort($urls);

        return $urls;
    }

    /**
     * @param string|null $value
     * @return string|null
     */
    private static function normalize_pipe_list(?string $value): ?string {
        if ($value === null) {
            return null;
        }

        $entries = array_values(
            array_filter(
                array_map('trim', explode('|', $value)),
                static fn(string $entry): bool => $entry !== ''
            )
        );

        if (!$entries) {
            return null;
        }

        return implode('|', $entries);
    }

    /**
     * @param FIFU_Term_Meta_Updater $updater
     * @param int                    $termId
     * @param array<string,string>   $meta
     * @return void
     */
    private static function sync_term_legacy_meta_to_db2(
        FIFU_Term_Meta_Updater $updater,
        int $termId,
        array $meta
    ): void {
        foreach (['fifu_image_url', 'fifu_image_alt'] as $metaKey) {
            $value = trim((string) ($meta[$metaKey] ?? ''));
            if ($value === '') {
                continue;
            }

            $updater->update_or_delete_term($termId, $metaKey, $value);
        }
    }

    /**
     * @param array<int|string> $termIds
     * @return array<int,array<string,string>>
     */
    private static function get_legacy_term_fifu_fields(array $termIds): array {
        $idsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($termIds);
        if ($idsCsv === '0') {
            return [];
        }

        $data = [];
        foreach (array_map('intval', explode(',', $idsCsv)) as $termId) {
            $data[$termId] = [
                'fifu_image_url' => '',
                'fifu_image_alt' => '',
            ];
        }

        $wpdb = self::get_wpdb();
        $results = $wpdb->get_results("
            SELECT term_id, meta_key, meta_value
            FROM {$wpdb->termmeta}
            WHERE term_id IN ({$idsCsv})
            AND meta_key IN ('fifu_image_url', 'fifu_image_alt')
        ");

        foreach ($results as $row) {
            $termId = (int) $row->term_id;
            if (!isset($data[$termId])) {
                continue;
            }

            $data[$termId][$row->meta_key] = $row->meta_value;
        }

        return $data;
    }

    /**
     * @param array<int|string> $postIds
     * @return void
     */
    public static function process_att_meta_out_row(int $queueId): bool {
        return self::process_meta_out_row($queueId, 'att', 'delete_attmeta', 'cleanup_post_attachment_meta');
    }

    /**
     * Process a single metadata out row for WooCommerce products.
     *
     * Handles WooCommerce specific cleanup operations for the queue entry provided.
     *
     * @param int $queueId Identifier of the WooCommerce metadata out queue row to process.
     *
     * @return bool True if the row was successfully processed, false otherwise.
     */
    /**
     * Process a single metadata out row for terms.
     *
     * Removes the queued term metadata entries associated with the given queue identifier.
     *
     * @param int $queueId Identifier of the term metadata out queue row to process.
     *
     * @return bool True if the row was successfully processed, false otherwise.
     */
    public static function process_term_meta_out_row(int $queueId): bool {
        return self::process_meta_out_row($queueId, 'term', 'delete_termmeta', 'cleanup_term_attachments');
    }

    /**
     * Clean up orphaned WAI attachments from the queue.
     *
     * This method performs the garbage collection that ensures WAI metadata rows are deleted when no longer needed.
     *
     * @return bool True if the garbage cleanup ran successfully, false otherwise.
     */
    public static function delete_garbage_wai(): bool {
        $wpdb = self::get_wpdb();
        $author = (int) Fifu_Options_Utils::get_author();

        $wpdb->query('START TRANSACTION');
        try {
            $sql1 = $wpdb->prepare("
                DELETE FROM {$wpdb->postmeta} 
                WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_image_alt', '_wp_attachment_metadata')
                AND post_id IN (
                    SELECT ID
                    FROM {$wpdb->posts}
                    WHERE ID = post_id
                    AND post_author = %d
                    AND post_parent = 0
                )
            ", $author);
            $wpdb->query($sql1);

            $sql2 = $wpdb->prepare("
                DELETE FROM {$wpdb->posts}
                WHERE post_author = %d
                AND post_parent = 0
            ", $author);
            $wpdb->query($sql2);

            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
        }

        return true;
    }

    /**
     * Execute the full metadata import pipeline for the provided IDs.
     *
     * Coordinates queue preparation, processing, and cleanup for either posts or terms.
     *
     * @param array  $postIds    List of post or term IDs to import metadata for.
     * @param string $metaKey    Meta key that identifies metadata entries being imported.
     * @param bool   $isCategory Whether the incoming IDs represent terms/categories.
     *
     * @return int Number of rows processed during the import.
     */
    public static function run_import(
        array $postIds,
        string $metaKey,
        bool $isCategory
    ): int {
        if (empty($postIds)) {
            return 0;
        }

        $total = count($postIds);
        Fifu_File_Logger::plugin(['import' => ['count' => $total, 'ctgr' => $isCategory]]);

        $postIdsCsv = Fifu_Db2_Sql_Helper::sanitize_ids_csv($postIds);
        $queueRepository = self::get_metadata_queue_repository();

        self::prepare_meta_out_queue($postIdsCsv, $isCategory);

        $id = $queueRepository->get_last_meta_out_id('att');
        if ($id) {
            self::process_att_meta_out_row($id);
        }

        if ($isCategory) {
            $id = $queueRepository->get_last_meta_out_id('term');
            if ($id) {
                self::process_term_meta_out_row($id);
            }
        }

        self::prepare_meta_in_queue($postIdsCsv, $isCategory);

        if (!$isCategory) {
            $id = $queueRepository->get_last_meta_in_id('post');
            if ($id) {
                self::process_post_meta_in_row($id);
            }

        } else {
            $id = $queueRepository->get_last_meta_in_id('term');
            if ($id) {
                self::process_term_meta_in_row($id);
            }
        }

        $wpdb = self::get_wpdb();
        $importTable = $wpdb->prefix . 'fifu_import';
        $categoryFlag = (int) $isCategory;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$importTable} WHERE post_id IN ({$postIdsCsv}) AND category = %d",
                $categoryFlag
            )
        );

        if (!$isCategory) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$postIdsCsv}) AND meta_key = %s",
                    $metaKey
                )
            );
        } else {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ({$postIdsCsv}) AND meta_key = %s",
                    $metaKey
                )
            );
        }

        return $total;
    }

    /**
     * Internal helper to execute metadata out operations for a specific type.
     *
     * @param int    $queueId
     * @param string $type
     * @param string $logAction
     * @param string $speedUpAction
     *
     * @return bool
     */
    private static function process_meta_out_row(
        int $queueId,
        string $type,
        string $logAction,
        string $speedUpAction
    ): bool {
        $wpdb = self::get_wpdb();
        $metaOutTable = $wpdb->prefix . 'fifu_meta_out';

        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_ids FROM {$metaOutTable} WHERE id = %d",
                $queueId
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$metaOutTable} WHERE id = %d",
                $queueId
            )
        );

        Fifu_File_Logger::plugin([$logAction => ['id' => $queueId]]);

        if (count($result) === 0) {
            return false;
        }

        $ids = $result[0]->post_ids;
        $postIds = explode(',', $ids);
        wp_cache_flush();
        $service = self::get_speed_up_service();
        if ($service instanceof Fifu_Db2_Speed_Up_Service && method_exists($service, $speedUpAction)) {
            $service->{$speedUpAction}($ids);
        }

        self::decrement_metadata_counter(self::count_queue_ids($postIds));

        return true;
    }

    /**
     * @return wpdb
     */
    private static function get_wpdb(): wpdb {
        global $wpdb;
        return $wpdb;
    }

    /**
     * @return Fifu_Metadata_Queue_Repository
     */
    private static function get_metadata_queue_repository(): Fifu_Metadata_Queue_Repository {
        return new Fifu_Metadata_Queue_Repository();
    }

    private static function can_use_db2_post_meta(
        wpdb $wpdb,
        string $mapTable,
        string $keyTable,
        string $urlTable
    ): bool {
        return self::table_exists($wpdb, $mapTable)
            && self::table_exists($wpdb, $keyTable)
            && self::table_exists($wpdb, $urlTable);
    }

    /**
     * Checks if the specified table exists in the database.
     *
     * @param wpdb $wpdb
     * @param string $table
     * @return bool
     */
    private static function table_exists(wpdb $wpdb, string $table): bool {
        $sql = $wpdb->prepare("SHOW TABLES LIKE %s", $table);
        return $wpdb->get_var($sql) !== null;
    }

    /**
     * @return Fifu_Legacy_Meta_Repository
     */
    private static function get_legacy_meta_repository(): Fifu_Legacy_Meta_Repository {
        $wpdb = self::get_wpdb();
        return new Fifu_Legacy_Meta_Repository($wpdb, $wpdb->prefix . 'postmeta');
    }

    /**
     * @return FIFU_Post_Meta_Updater|null
     */
    private static function get_post_meta_updater(): ?FIFU_Post_Meta_Updater {
        if (!class_exists('FIFU_Post_Meta_Updater', false)) {
            return null;
        }

        return FIFU_Post_Meta_Updater::instance();
    }

    /**
     * @return FIFU_Term_Meta_Updater|null
     */
    private static function get_term_meta_updater(): ?FIFU_Term_Meta_Updater {
        if (!class_exists('FIFU_Term_Meta_Updater', false)) {
            return null;
        }

        return FIFU_Term_Meta_Updater::instance();
    }

    /**
     * @return Fifu_Db2_Speed_Up_Service|null
     */
    private static function get_speed_up_service(): ?Fifu_Db2_Speed_Up_Service {
        $repository = self::get_speed_up_repository();
        if (!$repository) {
            return null;
        }

        return new Fifu_Db2_Speed_Up_Service($repository);
    }

    /**
     * @return Fifu_Db2_Speed_Up_Repository|null
     */
    private static function get_speed_up_repository(): ?Fifu_Db2_Speed_Up_Repository {
        if (!function_exists('fifu_db2_speed_up_repository')) {
            return null;
        }

        return fifu_db2_speed_up_repository();
    }

    /**
     * @param array<int|string> $ids
     */
    private static function count_queue_ids(array $ids): int {
        $count = 0;

        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }

        if ((int) $id <= 0) {
            continue;
        }

            $count++;
        }

        return $count;
    }

    /**
     * @param array<int|string>|string $postIds
     * @return int[]
     */
    private static function parse_metadata_post_ids($postIds): array {
        $raw = is_array($postIds) ? $postIds : preg_split('/[,|\\s]+/', (string) $postIds);
        $ids = [];

        foreach ($raw ?: [] as $id) {
            $id = (int) trim((string) $id);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function decrement_metadata_counter(int $processedCount): void {
        $processedCount = max(0, $processedCount);

        $current = Fifu_Transient_Manager::get('fifu_metadata_counter');
        $current = is_numeric($current) ? (int) $current : 0;

        Fifu_Transient_Manager::set(
            'fifu_metadata_counter',
            max(0, $current - $processedCount),
            0
        );
    }
}
