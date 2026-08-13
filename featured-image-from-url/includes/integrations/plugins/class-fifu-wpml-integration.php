<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

final class Fifu_WPML_Integration
{
    /** @var array<int,string> */
    private static array $deferred_image_sync = [];
    private static bool $shutdown_hook_registered = false;

    public static function register_hooks(): void
    {
        add_action(
            'wcml_after_duplicate_product_post_meta',
            [self::class, 'after_duplicate_product_post_meta'],
            10,
            3
        );
        add_action(
            'wcml_after_sync_product_data',
            [self::class, 'after_sync_product_data'],
            10,
            3
        );
        add_action('icl_make_duplicate', [self::class, 'on_make_duplicate'], 10, 4);
        add_action('wpml_after_copy_custom_field', [self::class, 'after_copy_custom_field'], PHP_INT_MAX, 3);
    }

    public static function after_copy_custom_field($from_id, $to_id, $meta_key): void
    {
        if (!is_string($meta_key) || 'fifu_image_url' !== $meta_key) {
            return;
        }

        $to_id = is_numeric($to_id) ? (int) $to_id : 0;

        if ($to_id <= 0) {
            return;
        }

        $postType = get_post_type($to_id);
        if ($postType && in_array($postType, ['product', 'product_variation'], true) && Fifu_Plugin_Detector::is_wcml_active()) {
            return;
        }

        $url = get_post_meta($to_id, 'fifu_image_url', true);
        if (is_array($url)) {
            $url = reset($url);
        }

        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return;
        }

        self::$deferred_image_sync[$to_id] = $url;

        if (!self::$shutdown_hook_registered) {
            add_action('shutdown', [self::class, 'flush_deferred_image_sync'], PHP_INT_MAX);
            self::$shutdown_hook_registered = true;
        }
    }

    public static function flush_deferred_image_sync(): void
    {
        if (empty(self::$deferred_image_sync)) {
            return;
        }

        foreach (self::$deferred_image_sync as $postId => $url) {
            Fifu_Developer_Media_Service::set_image((int) $postId, (string) $url);
        }

        self::$deferred_image_sync = [];
    }

    public static function after_duplicate_product_post_meta($originalId, $translatedId, $data): void
    {
        $originalId = is_numeric($originalId) ? (int) $originalId : 0;
        $translatedId = is_numeric($translatedId) ? (int) $translatedId : 0;

        if ($originalId <= 0 || $translatedId <= 0) {
            return;
        }

        self::copy_prefixed_post_meta($originalId, $translatedId);
    }

    public static function after_sync_product_data($originalId, $translatedId, $language): void
    {
        $originalId = is_numeric($originalId) ? (int) $originalId : 0;
        $translatedId = is_numeric($translatedId) ? (int) $translatedId : 0;

        if ($originalId <= 0 || $translatedId <= 0) {
            return;
        }

        self::copy_prefixed_post_meta($originalId, $translatedId);
    }

    public static function on_make_duplicate($sourceId, $lang, $postArray, $duplicateId): void
    {
        $sourceId = is_numeric($sourceId) ? (int) $sourceId : 0;
        $duplicateId = is_numeric($duplicateId) ? (int) $duplicateId : 0;

        if ($sourceId <= 0 || $duplicateId <= 0) {
            return;
        }

        $postType = get_post_type($sourceId);
        if (!$postType) {
            return;
        }

        if (in_array($postType, ['product', 'product_variation'], true) && Fifu_Plugin_Detector::is_wcml_active()) {
            return;
        }

        self::copy_prefixed_post_meta($sourceId, $duplicateId);
    }

    private static function copy_prefixed_post_meta(int $sourceId, int $targetId): void
    {
        if (!$sourceId || !$targetId || $sourceId === $targetId) {
            return;
        }

        $sourceMeta = get_post_meta($sourceId);
        $featuredValue = self::get_effective_featured_image_value($sourceId, $sourceMeta);

        if ($featuredValue !== null && $featuredValue !== '') {
            self::copy_effective_fifu_image_state($targetId, $featuredValue);
        }
    }

    private static function copy_effective_fifu_image_state(int $targetId, string $featuredValue): void
    {
        Fifu_Developer_Media_Service::set_image($targetId, $featuredValue);

        self::persist_db2_featured_image_fallback($targetId, $featuredValue);
    }

    private static function persist_db2_featured_image_fallback(int $postId, string $featuredValue): void
    {
        $manager = function_exists('fifu_db2_manager') ? fifu_db2_manager() : null;
        if (!$manager instanceof Fifu_Db2_Manager) {
            return;
        }

        if (method_exists($manager, 'deletePostMappings')) {
            $manager->deletePostMappings($postId, 'image', 0);
        }

        $manager->savePostUrl($postId, 'image', 0, $featuredValue);
        clean_post_cache($postId);
    }


    private static function get_effective_featured_image_value(int $sourceId, array $sourceMeta): ?string
    {
        $readUrl = Fifu_Post_Image_Url_Read_Service::get_image_url($sourceId);
        $readUrl = is_string($readUrl) ? trim($readUrl) : '';
        if ($readUrl !== '') {
            return $readUrl;
        }

        $value = self::first_scalar_meta_value($sourceMeta['fifu_image_url'] ?? null);

        return $value !== '' ? $value : null;
    }

    private static function first_scalar_meta_value($value): string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    $nested = self::first_scalar_meta_value($item);
                    if ($nested !== '') {
                        return $nested;
                    }
                    continue;
                }

                if (is_scalar($item)) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        return $item;
                    }
                }
            }

            return '';
        }

        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

}
