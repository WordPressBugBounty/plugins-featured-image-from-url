<?php

defined('ABSPATH') || exit;

/**
 * Hosts the new attachment file/url filters planned for the FIFU migration.
 */
class Fifu_Attachment_File_Filters {

    /**
     * Filters the attached file path, supplanting the legacy attachment file hook now handled by Fifu_Featured_Image_Filter.
     *
     * @param mixed $file
     * @param mixed $attachment_id
     * @return mixed
     */
    public static function filter_get_attached_file($file, $attachment_id) {
        if (!is_string($file) || $file === '') {
            return $file;
        }

        $attachment_id = self::normalize_attachment_id($attachment_id);

        if ($attachment_id <= 0) {
            return $file;
        }

        return self::process_url($file, $attachment_id);
    }

    /**
     * Filters the attachment URL, replacing the legacy FIFU attachment URL hook now centralized in Fifu_Featured_Image_Filter.
     *
     * @param mixed $url
     * @param mixed $attachment_id
     * @return mixed
     */
    public static function filter_attachment_url($url, $attachment_id) {
        if (!is_string($url) || $url === '') {
            return $url;
        }

        $attachment_id = self::normalize_attachment_id($attachment_id);

        if ($attachment_id <= 0) {
            return $url;
        }

        return self::process_url($url, $attachment_id);
    }

    private static function normalize_attachment_id($attachment_id): int {
        if (is_int($attachment_id)) {
            return $attachment_id;
        }

        if (is_string($attachment_id)) {
            $attachment_id = trim($attachment_id);

            if ($attachment_id === '' || !ctype_digit($attachment_id)) {
                return 0;
            }

            return (int) $attachment_id;
        }

        return 0;
    }

    /**
     * Processes an attachment URL according to FIFU session/enrichment expectations.
     *
     * This mirrors the logic in fifu_process_url() and delegates to the local media renderer
     * for external URL enrichment.
     *
     * @param string $url
     * @param int    $attachment_id
     * @return string
     */
    private static function process_url(string $url, int $attachment_id): string {
        if (self::should_bypass_processing($url, $attachment_id)) {
            return $url;
        }

        if (
            Fifu_Image_Url_Utils::is_cdn_url($url)
            || strpos($url, '//wp.fifu.app') === 0
        ) {
            return $url;
        }

        $att_post = get_post($attachment_id);
        if (!$att_post) {
            return $url;
        }

        if (!self::is_fifu_managed_attachment($attachment_id, $att_post)) {
            Fifu_Local_Media_Renderer::register_image($att_post->guid ?: $url, $attachment_id);
            return $url;
        }

        $raw_url = '';
        if (function_exists('fifu_get_raw_remote_attached_file')) {
            $raw_url = fifu_get_raw_remote_attached_file($attachment_id);
        }
        if ($raw_url === '') {
            $raw_url = trim((string) get_post_meta($attachment_id, '_wp_attached_file', true));
        }

        if ($raw_url === '') {
            Fifu_Local_Media_Renderer::register_image($att_post->guid ?: $url, $attachment_id);
            return $url;
        }

        $fixed_url = Fifu_Attachment_Legacy_Fixer::maybe_fix_legacy_url($raw_url, $attachment_id);
        if (is_string($fixed_url) && trim($fixed_url) !== '') {
            $raw_url = $fixed_url;
        }

        $is_remote_url = preg_match('~^(https?://|//)~i', $raw_url) === 1;
        $is_legacy_remote_url = preg_match('~^;(https?://|//|/)~i', $raw_url) === 1;

        if (!$is_remote_url && !$is_legacy_remote_url) {
            Fifu_Local_Media_Renderer::register_image($att_post->guid ?: $url, $attachment_id);
            return $url;
        }

        return self::process_external_url($raw_url, $attachment_id);
    }

    /**
     * Enriches an external FIFU URL using the Local Media Renderer session hooks.
     *
     * Mirrors the previous fifu_process_external_url and fifu_add_url_parameters sequence.
     *
     * @param string $url
     * @param int    $attachment_id
     * @param mixed  $size
     * @return string
     */
    private static function process_external_url(string $url, int $attachment_id, $size = null): string {
        return Fifu_Local_Media_Renderer::enrich_attachment_url($url, $attachment_id, $size);
    }

    private static function is_fifu_managed_attachment(int $attachment_id, $att_post = null): bool {
        if ($attachment_id <= 0) {
            return false;
        }

        if (function_exists('fifu_is_fifu_attachment') && fifu_is_fifu_attachment($attachment_id)) {
            return true;
        }

        if (class_exists('Fifu_Attachment_Update_Service', false) && method_exists('Fifu_Attachment_Update_Service', 'is_fifu_owned')) {
            return Fifu_Attachment_Update_Service::is_fifu_owned($attachment_id);
        }

        $author = null;
        if ($att_post && isset($att_post->post_author)) {
            $author = (int) $att_post->post_author;
        } elseif (function_exists('get_post_field')) {
            $author = (int) get_post_field('post_author', $attachment_id);
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

    /**
     * Determines whether a given attachment should be excluded from FIFU URL processing.
     *
     * This mirrors the early-return checks currently located inside fifu_process_url.
     *
     * @param string $url
     * @param int    $attachment_id
     * @return bool
     */
    private static function should_bypass_processing(string $url, int $attachment_id): bool {
        return $url === '' || $attachment_id <= 0;
    }
}
