<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

final class Fifu_Attachment_Update_Service
{
    public static function is_valid_remote_attachment_url(string $value): bool
    {
        $value = trim(htmlspecialchars_decode($value));
        if ($value === '' || preg_match('/[\x00-\x20\\\\]/', $value)) {
            return false;
        }

        if (preg_match('~^(https?://|//)~i', $value) !== 1) {
            return false;
        }

        $parts = wp_parse_url($value);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }

        return !isset($parts['scheme'])
            || in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true);
    }

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

    /**
     * Virtual attachments have no local derivatives or image-editor backups.
     * Preserve contradictory records instead of allowing core to delete files.
     */
    public static function is_safe_to_delete_remote_attachment(int $attachment_id): bool
    {
        if (get_post_type($attachment_id) !== 'attachment' || !self::is_fifu_owned($attachment_id)) {
            return false;
        }

        $primary = (string) get_post_meta($attachment_id, '_wp_attached_file', true);
        $recovery = trim((string) get_post_field('post_content_filtered', $attachment_id));
        if (
            !self::is_valid_remote_attachment_url($primary)
            || ($recovery !== '' && !self::is_valid_remote_attachment_url($recovery))
            || !self::is_valid_remote_attachment_url((string) get_attached_file($attachment_id))
        ) {
            return false;
        }

        // Core resolves backups independently of the primary URL, using the
        // directory in metadata['file']. Virtual records must have no backups.
        $backup_sizes = get_post_meta($attachment_id, '_wp_attachment_backup_sizes', true);
        if (is_array($backup_sizes) || !empty($backup_sizes)) {
            return false;
        }

        foreach ([
            get_post_meta($attachment_id, '_wp_attachment_metadata', true),
            wp_get_attachment_metadata($attachment_id),
        ] as $metadata) {
            if ($metadata === '' || $metadata === false || $metadata === []) {
                continue;
            }
            if (!is_array($metadata)) {
                return false;
            }
            if (
                isset($metadata['file'])
                && (!is_string($metadata['file']) || !self::is_valid_remote_attachment_url($metadata['file']))
            ) {
                return false;
            }
            foreach (['thumb', 'sizes', 'original_image', 'source_image', 'animated_video', 'animated_video_poster'] as $key) {
                if (!empty($metadata[$key])) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function has_remote_file_metadata(int $attachment_id, string $expected_url): bool
    {
        if ($attachment_id <= 0) {
            return false;
        }

        $expected_url = trim(htmlspecialchars_decode($expected_url));
        $attached_file = trim(htmlspecialchars_decode((string) get_post_meta($attachment_id, '_wp_attached_file', true)));

        return $expected_url !== ''
            && $attached_file !== ''
            && $attached_file === $expected_url;
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
