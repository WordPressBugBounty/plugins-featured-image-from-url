<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Orchestrates DB2 speed-up operations for FIFU.
 */
final class Fifu_Db2_Speed_Up_Service
{
    private Fifu_Db2_Speed_Up_Repository $repository;

    /**
     * @param Fifu_Db2_Speed_Up_Repository $repository
     */
    public function __construct(Fifu_Db2_Speed_Up_Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Speed up URLs for a given bucket and list of thumbnails (posts).
     *
     * Mirrors the behavior of admin/db.php::add_urls_su().
     *
     * @param string|int   $bucket_id
     * @param array $thumbnails
     * @return void
     */
    public function add_urls(string|int $bucket_id, array $thumbnails): void
    {
        $repository = $this->repository;
        $repository->speed_up_custom_fields($bucket_id, $thumbnails, false);

        $featured_list = [];
        foreach ($thumbnails as $thumbnail) {
            if (
                isset($thumbnail->meta_key)
                && in_array(
                    $thumbnail->meta_key,
                    ['fifu_image_url'],
                    true
                )
            ) {
                $featured_list[] = $thumbnail;
            }
        }

        if (!empty($featured_list)) {
            $att_ids_map = $repository->get_thumbnail_ids($featured_list, false);
            if (!empty($att_ids_map)) {
                $repository->speed_up_attachments($bucket_id, $featured_list, $att_ids_map);
                $meta_ids_map = $repository->get_thumbnail_meta_ids($featured_list, $att_ids_map);
                if (!empty($meta_ids_map)) {
                    $repository->speed_up_attachments_meta($bucket_id, $featured_list, $meta_ids_map);
                }
            }
        }
    }

    /**
     * Delegates migration of the legacy featured image URL and paired ALT before a FIFU Cloud operation.
     *
     * @param int[] $postIds
     * @return void
     */
    public function migrate_posts_legacy_featured_image_state_before_cloud_operation(array $postIds): void
    {
        $this->repository->migrate_posts_legacy_featured_image_state_before_cloud_operation($postIds);
    }

    public function migrate_terms_legacy_media_state_before_cloud_delete(array $termIds): void
    {
        $this->repository->migrate_terms_legacy_media_state_before_cloud_delete($termIds);
    }

    /**
     * Speed up URLs for a given bucket and list of thumbnails (categories).
     *
     * Mirrors the behavior of admin/db.php::ctgr_add_urls_su().
     *
     * @param string|int   $bucket_id
     * @param array $thumbnails
     * @return void
     */
    public function add_category_urls(string|int $bucket_id, array $thumbnails): void
    {
        $repository = $this->repository;
        $repository->speed_up_custom_fields($bucket_id, $thumbnails, true);

        $featured_list = [];
        foreach ($thumbnails as $thumbnail) {
            $featured_list[] = $thumbnail;
        }

        if (!empty($featured_list)) {
            $att_ids_map = $repository->get_thumbnail_ids($featured_list, true);
            if (!empty($att_ids_map)) {
                $repository->speed_up_attachments($bucket_id, $featured_list, $att_ids_map);
                $meta_ids_map = $repository->get_thumbnail_meta_ids($featured_list, $att_ids_map);
                if (!empty($meta_ids_map)) {
                    $repository->speed_up_attachments_meta($bucket_id, $featured_list, $meta_ids_map);
                }
            }
        }
    }

    /**
     * Orchestrates the rollback of speed up changes for posts using the repository.
     *
     * @param array $thumbnails
     * @param array $urls
     * @param array $videoUrls
     * @param array $attachmentIdsMap
     * @param array $metaIdsMap
     * @return void
     */
    public function revert_posts_speed_up(
        array $thumbnails,
        array $urls,
        array $videoUrls,
        array $attachmentIdsMap,
        array $metaIdsMap
    ): void {
        $repository = $this->repository;

        $repository->revert_custom_fields($thumbnails, $urls, $videoUrls, false);

        if (!empty($attachmentIdsMap)) {
            $repository->revert_attachments($urls, $videoUrls, $thumbnails, $attachmentIdsMap);
        }

        if (!empty($metaIdsMap)) {
            $repository->revert_attachments_meta($urls, $videoUrls, $thumbnails, $metaIdsMap);
        }
    }

    /**
     * Orchestrates the rollback of speed up changes for terms using the repository.
     *
     * @param array $thumbnails
     * @param array $urls
     * @param array $videoUrls
     * @param array $attachmentIdsMap
     * @param array $metaIdsMap
     * @return void
     */
    public function revert_terms_speed_up(
        array $thumbnails,
        array $urls,
        array $videoUrls,
        array $attachmentIdsMap,
        array $metaIdsMap
    ): void {
        $repository = $this->repository;

        $repository->revert_custom_fields($thumbnails, $urls, $videoUrls, true);

        if (!empty($attachmentIdsMap)) {
            $repository->revert_attachments($urls, $videoUrls, $thumbnails, $attachmentIdsMap);
        }

        if (!empty($metaIdsMap)) {
            $repository->revert_attachments_meta($urls, $videoUrls, $thumbnails, $metaIdsMap);
        }


    }

    /**
     * Prepare post-level removals for speed-up rollback.
     *
     * @param array $thumbnails
     * @param array $urls
     * @param array $videoUrls
     */
    public function remove_post_urls(array $thumbnails, array $urls, array $videoUrls): void
    {
        foreach ($thumbnails as $thumbnail) {
            if (empty($thumbnail->meta_id ?? null)) {
                $storageId = $thumbnail->storage_id ?? null;
                unset($urls[$storageId], $videoUrls[$storageId]);
            }
        }

        if (empty($urls) && empty($videoUrls)) {
            return;
        }

        $featuredList = [];
        foreach ($thumbnails as $thumbnail) {
            $metaKey = $thumbnail->meta_key ?? '';
            if (in_array($metaKey, ['fifu_image_url'], true)) {
                $featuredList[] = $thumbnail;
            }
        }

        $attIdsMapFeatured = [];
        $metaIdsMapFeatured = [];
        if (!empty($featuredList)) {
            $attIdsMapFeatured = $this->repository->get_thumbnail_ids($featuredList, false);
            if (!empty($attIdsMapFeatured)) {
                $metaIdsMapFeatured = $this->repository->get_thumbnail_meta_ids($featuredList, $attIdsMapFeatured);
            }
        }

        $this->revert_posts_speed_up(
            $featuredList,
            $urls,
            $videoUrls,
            $attIdsMapFeatured,
            $metaIdsMapFeatured
        );

        $this->revert_featured_to_local($featuredList, false);
    }

    /**
     * Prepare term-level removals for speed-up rollback.
     *
     * @param array $thumbnails
     * @param array $urls
     * @param array $videoUrls
     */
    public function remove_term_urls(array $thumbnails, array $urls, array $videoUrls): void
    {
        foreach ($thumbnails as $thumbnail) {
            if (empty($thumbnail->meta_id ?? null)) {
                $storageId = $thumbnail->storage_id ?? null;
                unset($urls[$storageId], $videoUrls[$storageId]);
            }
        }

        $this->cleanup_legacy_term_image_alt_after_speedup_delete($thumbnails);

        if (empty($urls) && empty($videoUrls)) {
            return;
        }

        $featuredList = [];
        foreach ($thumbnails as $thumbnail) {
            $featuredList[] = $thumbnail;
        }

        $attIdsMap = [];
        $metaIdsMap = [];
        if (!empty($featuredList)) {
            $attIdsMap = $this->repository->get_thumbnail_ids($featuredList, true);
            if (!empty($attIdsMap)) {
                $metaIdsMap = $this->repository->get_thumbnail_meta_ids($featuredList, $attIdsMap);
            }
        }

        $this->revert_terms_speed_up(
            $thumbnails,
            $urls,
            $videoUrls,
            $attIdsMap,
            $metaIdsMap
        );

        $this->revert_featured_to_local($featuredList, true);
    }

    /**
     * Revert featured items to local attachments.
     *
     * @param array $featuredList
     * @param bool  $isCategory
     */
    public function revert_featured_to_local(array $featuredList, bool $isCategory): void
    {
        if (empty($featuredList)) {
            return;
        }

        foreach ($featuredList as $item) {
            $objectId = $this->extract_post_id($item);
            if ($objectId <= 0) {
                continue;
            }

            $metaKey = $this->resolve_featured_source_meta_key($item);
            $backupId = $isCategory
                ? get_term_meta($objectId, 'bkp_thumbnail_id', true)
                : get_post_meta($objectId, 'bkp_thumbnail_id', true);
            $fifuUrl = $this->resolve_current_featured_fifu_url($objectId, $metaKey, $isCategory);
            $currentThumb = $isCategory
                ? get_term_meta($objectId, 'thumbnail_id', true)
                : get_post_meta($objectId, '_thumbnail_id', true);

            if (!$backupId || !$fifuUrl || !$currentThumb) {
                continue;
            }

            $backupId = (int) $backupId;
            if ($backupId <= 0) {
                continue;
            }

            $attachmentExists = false;
            if (function_exists('get_attached_file')) {
                $file = get_attached_file($backupId);
                if ($file && file_exists($file)) {
                    $attachmentExists = true;
                }
            }

            if (!$attachmentExists) {
                if ($isCategory) {
                    delete_term_meta($objectId, 'bkp_thumbnail_id');
                } else {
                    delete_post_meta($objectId, 'bkp_thumbnail_id');
                }
                continue;
            }

            if ($isCategory) {
                $this->clear_current_featured_media($objectId, $metaKey, true);
                update_term_meta($objectId, 'thumbnail_id', $backupId);
                delete_term_meta($objectId, 'bkp_thumbnail_id');
                continue;
            }

            $this->clear_current_featured_media($objectId, $metaKey, false);
            update_post_meta($objectId, '_thumbnail_id', $backupId);
            delete_post_meta($objectId, 'bkp_thumbnail_id');
        }
    }

    /**
     * Resolve the featured source meta key from a thumbnail item.
     *
     * @param object|array $item
     * @return string
     */
    private function resolve_featured_source_meta_key(object|array $item): string
    {
        if (is_array($item)) {
            return (string) ($item['meta_key'] ?? '');
        }

        return (string) ($item->meta_key ?? '');
    }

    /**
     * Resolve the current FIFU media URL for the featured item based on its source meta key.
     *
     * @param int $objectId
     * @param string $metaKey
     * @param bool $isCategory
     * @return string
     */
    private function resolve_current_featured_fifu_url(int $objectId, string $metaKey, bool $isCategory): string
    {
        if ($isCategory) {
            return match ($metaKey) {
                'fifu_image_url' => (string) (Fifu_Term_Image_Url_Read_Service::get_image_url($objectId) ?? ''),
                default => '',
            };
        }

        return match ($metaKey) {
            'fifu_image_url' => (string) (Fifu_Post_Image_Url_Read_Service::get_image_url($objectId) ?? ''),
            default => '',
        };
    }

    /**
     * Clear the current featured media using the correct media type for the source meta key.
     *
     * @param int $objectId
     * @param string $metaKey
     * @param bool $isCategory
     * @return void
     */
    private function clear_current_featured_media(int $objectId, string $metaKey, bool $isCategory): void
    {
        if (!class_exists('Fifu_Developer_Media_Service')) {
            return;
        }

        if ($isCategory) {
            match ($metaKey) {
                'fifu_image_url' => $this->clear_current_term_image_media($objectId),
                default => null,
            };
            return;
        }

        match ($metaKey) {
            'fifu_image_url' => Fifu_Developer_Media_Service::set_image($objectId, ''),
            default => null,
        };
    }

    /**
     * Clear the current term image and remove residual legacy ALT termmeta during SU rollback.
     *
     * @param int $termId
     * @return void
     */
    private function clear_current_term_image_media(int $termId): void
    {
        Fifu_Developer_Media_Service::set_category_image($termId, '');
        delete_term_meta($termId, 'fifu_image_alt');
    }

    /**
     * Remove residual legacy term ALT metadata for category image entries deleted via SU.
     *
     * This must run even when thumbnail restoration is skipped, because the cloud delete
     * flow can still revert/remove fifu_image_url without entering the local thumbnail branch.
     *
     * @param array $thumbnails
     * @return void
     */
    private function cleanup_legacy_term_image_alt_after_speedup_delete(array $thumbnails): void
    {
        global $wpdb;

        foreach ($thumbnails as $thumbnail) {
            $metaKey = $this->resolve_featured_source_meta_key($thumbnail);
            if ($metaKey !== 'fifu_image_url') {
                continue;
            }

            $termId = $this->extract_post_id($thumbnail);
            if ($termId <= 0) {
                $storageId = $this->extract_storage_id($thumbnail);
                if ($storageId === '' || !class_exists('WooCommerce')) {
                    continue;
                }

                $termId = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT tm.term_id
                        FROM {$wpdb->termmeta} tm
                        INNER JOIN {$wpdb->terms} t ON t.term_id = tm.term_id
                        WHERE tm.meta_key = 'fifu_image_url'
                          AND tm.meta_value LIKE %s
                        LIMIT 1",
                        '%https://cdn.fifu.app/%' . $storageId
                    )
                );
                if ($termId <= 0) {
                    continue;
                }
            }

            delete_term_meta($termId, 'fifu_image_alt');
        }
    }

    /**
     * Backup attachments for the provided posts.
     *
     * @param array|int|string $postIds
     * @return void
     */
    public function backup_post_attachments(array|int|string $postIds): void
    {
        $this->repository->backup_post_attachment_ids($postIds);
    }

    /**
     * Backup attachments for the provided terms.
     *
     * @param array|int|string $termIds
     * @return void
     */
    public function backup_term_attachments(array|int|string $termIds): void
    {
        $this->repository->backup_term_attachment_ids($termIds);
    }

    /**
     * Delete post attachment IDs and clear related caches.
     *
     * @param array|int|string $postIds
     * @return void
     */
    public function delete_post_attachment_ids_with_cache(array|int|string $postIds): void
    {
        $this->repository->delete_post_attachment_ids($postIds);
        foreach ($this->normalize_ids($postIds) as $postId) {
            wp_cache_delete($postId, 'post_meta');
        }
    }

    /**
     * Delete only featured attachment IDs for the provided posts and clear related caches.
     *
     * @param array|int|string $postIds
     * @return void
     */
    public function delete_post_featured_attachment_ids_with_cache(array|int|string $postIds): void
    {
        $this->repository->delete_post_featured_attachment_ids($postIds);
        foreach ($this->normalize_ids($postIds) as $postId) {
            wp_cache_delete($postId, 'post_meta');
        }
    }

    /**
     * Delete term attachment IDs and clear related caches.
     *
     * @param array|int|string $termIds
     * @return void
     */
    public function delete_term_attachment_ids_with_cache(array|int|string $termIds): void
    {
        $this->repository->delete_term_attachment_ids($termIds);
        foreach ($this->normalize_ids($termIds) as $termId) {
            wp_cache_delete($termId, 'term_meta');
        }
    }

    /**
     * Wraps a callable inside a SQL transaction.
     *
     * @param callable $callback
     */
    private function run_within_transaction(callable $callback): bool
    {
        $wpdb = $this->repository->get_wpdb();
        $wpdb->query('START TRANSACTION');

        try {
            $callback();
            $wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            $wpdb->query('ROLLBACK');
            return false;
        }
    }

    /**
     * Placeholder for restoring attachments via insert_postmeta2/related operations.
     *
     * @param string $valuesSql
     * @param array|int|string $postIds
     */
    public function restore_post_attachment_meta(string $valuesSql, array|int|string $postIds): void
    {
        if ($valuesSql === '') {
            return;
        }

        $repository = $this->repository;
        $normalizedPostIds = $this->normalize_object_ids($postIds);
        $this->run_within_transaction(static function () use ($valuesSql, $postIds, $repository): void {
            $repository->insert_post_attachment_meta($valuesSql, $postIds);
        });
        foreach ($normalizedPostIds as $postId) {
            clean_post_cache($postId);
        }
    }

    /**
     * Placeholder for cleaning up FIFU-created attachment metadata.
     *
     * @param array|int|string $attachmentIds
     */
    public function cleanup_post_attachment_meta(array|int|string $attachmentIds): void
    {
        $repository = $this->repository;
        $this->run_within_transaction(static function () use ($attachmentIds, $repository): void {
            $repository->delete_attachment_meta($attachmentIds);
        });
    }

    /**
     * Placeholder for cleaning term attachments metadata.
     *
     * @param array|int|string $termIds
     */
    public function cleanup_term_attachments(array|int|string $termIds): void
    {
        $repository = $this->repository;
        $this->run_within_transaction(static function () use ($termIds, $repository): void {
            $repository->delete_term_thumbnail_meta($termIds);
        });
    }

    /**
     * Placeholder for restoring term attachment metadata.
     *
     * @param string $valuesSql
     * @param array|int|string $termIds
     */
    public function restore_term_attachments(string $valuesSql, array|int|string $termIds): void
    {
        if ($valuesSql === '') {
            return;
        }

        $repository = $this->repository;
        $this->run_within_transaction(static function () use ($valuesSql, $termIds, $repository): void {
            $repository->insert_term_attachment_meta($valuesSql, $termIds);
        });
    }

    /**
     * @param array|int|string $ids
     * @return int[]
     */
    private function normalize_object_ids(array|int|string $ids): array
    {
        if (is_array($ids)) {
            $values = $ids;
        } elseif (is_string($ids)) {
            $values = array_map('trim', explode(',', $ids));
        } else {
            $values = [$ids];
        }

        $normalized = [];
        foreach ($values as $value) {
            $id = (int) $value;
            if ($id <= 0) {
                continue;
            }

            $normalized[$id] = $id;
        }

        return array_values($normalized);
    }

    /**
     * Normalize ID inputs into an array of integers.
     *
     * @param array|int|string $ids
     * @return int[]
     */
    private function normalize_ids(array|int|string $ids): array
    {
        if (is_array($ids)) {
            return array_values(array_filter(array_map('intval', $ids), static function ($value) {
                return $value > 0;
            }));
        }

        if (is_int($ids)) {
            return $ids > 0 ? [$ids] : [];
        }

        $parts = array_filter(array_map('trim', explode(',', (string) $ids)));
        return array_values(array_filter(array_map('intval', $parts), static function ($value) {
            return $value > 0;
        }));
    }

    /**
     * Extract post ID from a thumbnail item.
     *
     * @param object|array $item
     * @return int
     */
    private function extract_post_id($item): int
    {
        if (is_array($item)) {
            return isset($item['post_id']) ? (int) $item['post_id'] : 0;
        }
        return isset($item->post_id) ? (int) $item->post_id : 0;
    }

    /**
     * Extract storage ID from a thumbnail item when available.
     *
     * @param object|array $item
     * @return string|null
     */
    private function extract_storage_id($item): ?string
    {
        $value = null;
        if (is_array($item)) {
            $value = $item['storage_id'] ?? null;
        } elseif (is_object($item)) {
            $value = $item->storage_id ?? null;
        }

        $value = is_string($value) ? trim($value) : '';
        return $value !== '' ? $value : null;
    }
}
