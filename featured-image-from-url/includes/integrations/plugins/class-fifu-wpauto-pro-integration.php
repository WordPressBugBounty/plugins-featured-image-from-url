<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

final class Fifu_Wpauto_Pro_Integration {
    /** @var array<int,string> */
    private static array $pending_images = [];

    /** @var array<int,bool> */
    private static array $finalizing_posts = [];

    /** @var array<string,int[]> */
    private static array $suppressed_downloads = [];

    /** @var array<int,array<string,bool>> */
    private static array $suppression_posts = [];

    /** @var object|null */
    private static $original_image_importer = null;

    private static bool $download_proxy_installed = false;

    public static function register_hooks(): void {
        if (!class_exists('Fifu_Plugin_Detector') || !Fifu_Plugin_Detector::is_wpauto_pro_active()) {
            return;
        }

        add_filter(
            'wpauto_extracted_img_args',
            [self::class, 'capture_image_args'],
            10,
            1
        );

        add_filter(
            'wpauto_extracted_content',
            [self::class, 'finalize_content'],
            10,
            1
        );
    }

    public static function capture_image_args($imgArgs) {
        if (!is_array($imgArgs)) {
            return $imgArgs;
        }

        $postId = self::positive_id($imgArgs['post_id'] ?? 0);
        $src = $imgArgs['src'] ?? null;

        if ($postId <= 0 || !is_string($src) || !self::is_eligible_remote_url($src)) {
            return $imgArgs;
        }

        if (self::is_excluded_by_options()) {
            return $imgArgs;
        }

        if (isset(self::$pending_images[$postId])) {
            return $imgArgs;
        }

        self::$pending_images[$postId] = $src;
        self::register_suppression_claim($postId, $src);
        self::install_download_proxy();

        return $imgArgs;
    }

    public static function finalize_content($content) {
        $single = self::get_campaign_single();
        $postId = self::positive_id(is_object($single) ? ($single->process_post_id ?? 0) : 0);

        if ($postId <= 0 || isset(self::$finalizing_posts[$postId])) {
            return $content;
        }

        self::$finalizing_posts[$postId] = true;

        try {
            if (!self::is_excluded_by_options()) {
                $featuredUrl = self::$pending_images[$postId] ?? null;

                if (!is_string($featuredUrl) || !self::is_eligible_remote_url($featuredUrl)) {
                    $featuredUrl = is_object($single) && isset($single->featured_image)
                        ? $single->featured_image
                        : null;
                }

                if (is_string($featuredUrl) && self::is_eligible_remote_url($featuredUrl)) {
                    if (class_exists('Fifu_Developer_Media_Service') && method_exists('Fifu_Developer_Media_Service', 'set_image')) {
                        Fifu_Developer_Media_Service::set_image($postId, $featuredUrl);
                    }
                }
            }
        } catch (Throwable $e) {
            // WPAuto's content filter must remain non-fatal when FIFU cannot persist the image.
        } finally {
            self::clear_post_state($postId);
            unset(self::$finalizing_posts[$postId]);
        }

        return $content;
    }

    private static function get_campaign_single() {
        if (!function_exists('wpauto_campaign_single_call')) {
            return null;
        }

        try {
            return wpauto_campaign_single_call();
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function get_campaign_options(): array {
        if (!function_exists('wpauto_campaign_base_call')) {
            return [];
        }

        try {
            $base = wpauto_campaign_base_call();
            return is_object($base) && isset($base->wpauto_options) && is_array($base->wpauto_options)
                ? $base->wpauto_options
                : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private static function is_excluded_by_options(): bool {
        $options = self::get_campaign_options();

        return ($options['image_option'] ?? null) === 'dall-e'
            || ($options['multiple_image_option'] ?? null) === 'dall-e-multi'
            || !empty($options['strip_featured_image']);
    }

    private static function is_eligible_remote_url($url): bool {
        if (!is_string($url)) {
            return false;
        }

        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        try {
            if (function_exists('attachment_url_to_postid') && attachment_url_to_postid($url) > 0) {
                return false;
            }
        } catch (Throwable $e) {
            // An unavailable media lookup must not prevent a valid remote URL from being used.
        }

        try {
            if (function_exists('wp_upload_dir')) {
                $uploadDir = wp_upload_dir();
                $baseUrl = is_array($uploadDir) ? ($uploadDir['baseurl'] ?? '') : '';
                if (is_string($baseUrl) && $baseUrl !== '') {
                    $baseUrl = rtrim($baseUrl, '/');
                    $normalizedUrl = preg_replace('/[?#].*$/', '', $url);
                    if (is_string($normalizedUrl) && ($normalizedUrl === $baseUrl || str_starts_with($normalizedUrl, $baseUrl . '/'))) {
                        return false;
                    }
                }
            }
        } catch (Throwable $e) {
            // Upload directory discovery is optional for defensive/test runtimes.
        }

        return true;
    }

    private static function register_suppression_claim(int $postId, string $url): void {
        if (!isset(self::$suppression_posts[$postId])) {
            self::$suppression_posts[$postId] = [];
        }

        if (!empty(self::$suppression_posts[$postId][$url])) {
            return;
        }

        self::$suppression_posts[$postId][$url] = true;
        self::$suppressed_downloads[$url] ??= [];
        self::$suppressed_downloads[$url][] = $postId;
    }

    private static function consume_suppression_claim($url): bool {
        if (!is_string($url) || empty(self::$suppressed_downloads[$url])) {
            return false;
        }

        $postId = (int) array_shift(self::$suppressed_downloads[$url]);
        if (self::$suppressed_downloads[$url] === []) {
            unset(self::$suppressed_downloads[$url]);
        }

        unset(self::$suppression_posts[$postId][$url]);
        if (self::$suppression_posts[$postId] === []) {
            unset(self::$suppression_posts[$postId]);
        }

        self::restore_download_proxy_if_unused();
        return true;
    }

    private static function clear_post_state(int $postId): void {
        if (isset(self::$suppression_posts[$postId])) {
            foreach (array_keys(self::$suppression_posts[$postId]) as $url) {
                if (isset(self::$suppressed_downloads[$url])) {
                    self::$suppressed_downloads[$url] = array_values(array_filter(
                        self::$suppressed_downloads[$url],
                        static fn (int $owner): bool => $owner !== $postId
                    ));

                    if (self::$suppressed_downloads[$url] === []) {
                        unset(self::$suppressed_downloads[$url]);
                    }
                }
            }
            unset(self::$suppression_posts[$postId]);
        }

        unset(self::$pending_images[$postId]);
        self::restore_download_proxy_if_unused();
    }

    private static function install_download_proxy(): void {
        if (self::$download_proxy_installed || !class_exists('WPAuto_Image_Import', false)) {
            return;
        }

        try {
            $reflection = new ReflectionClass('WPAuto_Image_Import');
            if (!$reflection->hasProperty('instance')) {
                return;
            }

            $property = $reflection->getProperty('instance');
            if (!$property->isStatic() || !$property->isPublic()) {
                return;
            }

            $original = $property->getValue();
            if (!is_object($original) && method_exists('WPAuto_Image_Import', 'instance')) {
                $original = WPAuto_Image_Import::instance();
            }

            if (!is_object($original) || !method_exists($original, 'fetch_remote_file')) {
                return;
            }

            $proxyPath = __DIR__ . '/class-fifu-wpauto-pro-image-import-proxy.php';
            if (!is_file($proxyPath)) {
                return;
            }

            require_once $proxyPath;
            if (!class_exists('Fifu_Wpauto_Pro_Image_Import_Proxy', false)) {
                return;
            }

            self::$original_image_importer = $original;
            $property->setValue(null, new Fifu_Wpauto_Pro_Image_Import_Proxy(
                $original,
                static fn ($url): bool => self::consume_suppression_claim($url)
            ));
            self::$download_proxy_installed = true;
        } catch (Throwable $e) {
            self::$original_image_importer = null;
            self::$download_proxy_installed = false;
        }
    }

    private static function restore_download_proxy_if_unused(): void {
        if (!self::$download_proxy_installed || self::$suppressed_downloads !== []) {
            return;
        }

        try {
            if (class_exists('WPAuto_Image_Import', false)) {
                $reflection = new ReflectionClass('WPAuto_Image_Import');
                if ($reflection->hasProperty('instance')) {
                    $property = $reflection->getProperty('instance');
                    if ($property->isStatic() && $property->isPublic()) {
                        $property->setValue(null, self::$original_image_importer);
                    }
                }
            }
        } catch (Throwable $e) {
            // Restoration is best effort when WPAuto's runtime changes underneath us.
        }

        self::$original_image_importer = null;
        self::$download_proxy_installed = false;
    }

    private static function positive_id($value): int {
        if (!(is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)))) {
            return 0;
        }

        $value = (int) $value;
        return $value > 0 ? $value : 0;
    }
}
