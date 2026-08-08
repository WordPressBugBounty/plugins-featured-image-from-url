<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Responsible for PubCDN-specific URL manipulation logic.
 */
final class Fifu_Pubcdn_Url_Service
{
    /**
     * Builds a PubCDN URL for the provided attachment while maintaining post slug and signatures.
     */
    public static function get_image_url(?int $att_id, string $image_url, ?string $qp): string
    {
        if (Fifu_Image_Url_Utils::is_cdn_url($image_url)) {
            return $image_url;
        }

        $image_url = Fifu_Jetpack_Cdn_Service::get_original_image_url($image_url);

        if ($att_id) {
            $alt = get_post_meta($att_id, '_wp_attachment_image_alt', true);
            $slug = $alt ? $alt : Fifu_Post_Meta_Utils::get_parent_slug($att_id);
            $post = get_post($att_id);
            $post_id = $post && isset($post->post_parent) ? $post->post_parent : null;
        } else {
            $slug = 'not-found';
            $post_id = null;
        }

        if ($post_id) {
            $qp = $qp ? $qp . '&' : '?';
            $qp .= 'p=' . $post_id;
        }

        $decoded_string = urldecode($slug);
        if (function_exists('transliterator_transliterate')) {
            $post_slug = sanitize_title(transliterator_transliterate('Any-Latin; Latin-ASCII', $decoded_string));
        } else {
            $fallback_slug = preg_replace('/[^\x20-\x7E]/u', '', $decoded_string);
            $post_slug = sanitize_title($fallback_slug);
        }

        $main_domain = trim((string) get_option('fifu_main_domain'));
        if ($main_domain === '') {
            $home_url = get_home_url();
            $main_domain = (string) parse_url($home_url, PHP_URL_HOST);
        }

        $post_slug = $post_slug ? $post_slug : 'image';
        $encoded_url = Fifu_Image_Url_Utils::base64($image_url);
        $query_string = $qp ? $qp : '';
        $new_url = "//wp.fifu.app/" . $main_domain . "/" . $encoded_url . "/" . $post_slug . ".webp" . $query_string;
        $signature = Fifu_Cdn_Signature_Service::get_signature($new_url, 'fifu');
        return 'https:' . str_replace($encoded_url, $encoded_url . '/' . $signature, $new_url);
    }

    /**
     * Restores the original source URL from a PubCDN-encoded URL.
     */
    public static function decode_pubcdn_url(string $url): string
    {
        $parts = explode('/', $url);
        if (!isset($parts[4])) {
            return $url;
        }

        $base64 = $parts[4];
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $decoded = base64_decode(strtr($base64, '-_', '+/'));
        return $decoded ? $decoded : $url;
    }
}
