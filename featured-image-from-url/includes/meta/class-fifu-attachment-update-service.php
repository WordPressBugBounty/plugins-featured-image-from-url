<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

final class Fifu_Attachment_Update_Service
{
    public static function get_attachment_remote_url(int $attachment_id): string
    {
        if ($attachment_id <= 0) {
            return '';
        }

        $file = trim((string) get_post_meta($attachment_id, '_wp_attached_file', true));
        if ($file !== '') {
            return htmlspecialchars_decode($file);
        }

        $content_filtered = trim((string) get_post_field('post_content_filtered', $attachment_id));
        return $content_filtered !== '' ? htmlspecialchars_decode($content_filtered) : '';
    }

    public static function is_fifu_owned(int $attachment_id): bool
    {
        if ($attachment_id <= 0) {
            return false;
        }

        if (function_exists('fifu_is_fifu_attachment')) {
            return fifu_is_fifu_attachment($attachment_id);
        }

        if (class_exists('Fifu_Attachment_Repository', false) && method_exists('Fifu_Attachment_Repository', 'is_fifu_attachment')) {
            $repository = new Fifu_Attachment_Repository();
            if ($repository->is_fifu_attachment($attachment_id)) {
                return true;
            }
        }

        $author = null;
        if (function_exists('get_post')) {
            $post = get_post($attachment_id);
            if (is_object($post) && isset($post->post_author) && $post->post_author !== '') {
                $author = (int) $post->post_author;
            }
        }

        if ($author === null && function_exists('get_post_field')) {
            $post_author = get_post_field('post_author', $attachment_id);
            if ($post_author !== null && $post_author !== '') {
                $author = (int) $post_author;
            }
        }

        if ($author === null || $author <= 0) {
            return false;
        }

        $candidates = [];
        if (function_exists('fifu_get_fifu_author_candidates')) {
            $candidates = fifu_get_fifu_author_candidates();
        }

        if (!is_array($candidates)) {
            $candidates = [];
        }

        if (function_exists('fifu_get_author')) {
            $resolved = (int) fifu_get_author();
            if ($resolved > 0) {
                $candidates[] = $resolved;
            }
        }

        if (class_exists('Fifu_Options_Utils', false) && method_exists('Fifu_Options_Utils', 'get_author')) {
            $resolved_author = (int) Fifu_Options_Utils::get_author();
            if ($resolved_author > 0) {
                $candidates[] = $resolved_author;
            }
        }

        if (defined('FIFU_AUTHOR')) {
            $const_author = (int) FIFU_AUTHOR;
            if ($const_author > 0) {
                $candidates[] = $const_author;
            }
        }

        $candidates[] = 7777777777;
        $candidates[] = 77777;

        $candidates = array_values(array_unique(array_filter(array_map('intval', $candidates), static fn(int $candidate): bool => $candidate > 0)));

        return in_array($author, $candidates, true);
    }

    public static function is_youtube_thumbnail_quality_fallback(string $previous_url, string $new_url): bool
    {
        $previous_url = trim(htmlspecialchars_decode($previous_url));
        $new_url = trim(htmlspecialchars_decode($new_url));
        if ($previous_url === '' || $new_url === '' || $previous_url === $new_url) {
            return false;
        }

        $previous_parts = wp_parse_url($previous_url);
        $new_parts = wp_parse_url($new_url);
        if (!is_array($previous_parts) || !is_array($new_parts)) {
            return false;
        }

        $host = strtolower((string) ($previous_parts['host'] ?? ''));
        $new_host = strtolower((string) ($new_parts['host'] ?? ''));
        if ($host !== $new_host || !in_array($host, ['img.youtube.com', 'i.ytimg.com'], true)) {
            return false;
        }

        $previous_path = (string) ($previous_parts['path'] ?? '');
        $new_path = (string) ($new_parts['path'] ?? '');
        if ($previous_path === '' || $new_path === '') {
            return false;
        }

        if (!preg_match('#^/vi/([^/]+)/([^/]+)\.jpg$#i', $previous_path, $previous_matches)) {
            return false;
        }

        if (!preg_match('#^/vi/([^/]+)/([^/]+)\.jpg$#i', $new_path, $new_matches)) {
            return false;
        }

        if (strtolower($previous_matches[1]) !== strtolower($new_matches[1])) {
            return false;
        }

        return strtolower($previous_matches[2]) === 'maxresdefault'
            && strtolower($new_matches[2]) === 'mqdefault';
    }

    public static function update_attachment_alt_only(int $attachment_id, ?string $alt, bool $preserve_alt_when_null = false): void
    {
        if ($attachment_id <= 0 || !self::is_fifu_owned($attachment_id)) {
            return;
        }

        if ($alt === null && $preserve_alt_when_null) {
            return;
        }

        global $wpdb;
        $alt_value = $alt === null ? '' : trim((string) $alt);
        $wpdb->update(
            $wpdb->posts,
            [
                'post_title'   => $alt_value,
                'post_excerpt' => $alt_value,
            ],
            ['id' => $attachment_id]
        );

        if ($alt === null) {
            delete_post_meta($attachment_id, '_wp_attachment_image_alt');
        } elseif ($alt_value !== '') {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_value);
        } else {
            delete_post_meta($attachment_id, '_wp_attachment_image_alt');
        }

        wp_cache_delete($attachment_id, 'post_meta');
        clean_post_cache($attachment_id);
    }

    public static function initialize_remote_attachment(
        int $attachment_id,
        string $url,
        ?string $alt = null,
        ?int $width = null,
        ?int $height = null,
        bool $delete_alt_when_null = false
    ): void {
        if ($attachment_id <= 0) {
            return;
        }

        $url = trim(htmlspecialchars_decode($url));
        if ($url === '') {
            return;
        }

        update_post_meta($attachment_id, '_wp_attached_file', $url);
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            [
                'post_content_filtered' => $url,
            ],
            ['id' => $attachment_id]
        );

        if ($alt !== null) {
            self::update_attachment_alt_only($attachment_id, $alt);
        } elseif ($delete_alt_when_null) {
            self::update_attachment_alt_only($attachment_id, null);
        }

        if ($width !== null && $height !== null && $width > 0 && $height > 0) {
            wp_update_attachment_metadata($attachment_id, [
                'width' => (int) $width,
                'height' => (int) $height,
                'file' => $url,
            ]);
        }

        wp_cache_delete($attachment_id, 'post_meta');
        clean_post_cache($attachment_id);
    }

    public static function update_attachment_details(int $attachment_id, string $url, ?string $alt): void
    {
        self::update_remote_attachment($attachment_id, $url, $alt);
    }

    public static function update_remote_attachment(
        int $attachment_id,
        string $url,
        ?string $alt = null,
        ?int $width = null,
        ?int $height = null,
        bool $preserve_alt_when_null = false
    ): void {
        if ($attachment_id <= 0) {
            return;
        }

        $url = trim(htmlspecialchars_decode($url));
        if ($url === '') {
            return;
        }

        if (!self::is_fifu_owned($attachment_id)) {
            return;
        }

        $current_url = self::get_attachment_remote_url($attachment_id);
        if ($current_url !== '' && $current_url !== $url && !self::is_youtube_thumbnail_quality_fallback($current_url, $url)) {
            return;
        }

        if ($alt !== null) {
            if ($current_url === '' || $current_url === $url || self::is_youtube_thumbnail_quality_fallback($current_url, $url)) {
                self::initialize_remote_attachment($attachment_id, $url, $alt);
            }
            return;
        }

        if ($preserve_alt_when_null) {
            self::initialize_remote_attachment($attachment_id, $url, null, $width, $height);
            return;
        }

        self::initialize_remote_attachment($attachment_id, $url, null, $width, $height);
    }
}
