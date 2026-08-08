<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Content_Url_Scanner
{
    /**
     * Finds the first image media tag and returns its normalized URL and alt.
     *
     * @return array{url:string,alt:?string,tag:string}|null
     */
    public static function find_first_image_media(int $post_id, ?string $content): ?array
    {
        $content = $content ?: get_post_field('post_content', $post_id);
        if (!$content) {
            return null;
        }

        $media = self::find_first_media($post_id, $content, 'image');
        if (!$media || ($media['type'] ?? '') !== 'image') {
            return null;
        }

        return [
            'url' => (string) ($media['url'] ?? ''),
            'alt' => $media['alt'] ?? null,
            'tag' => (string) ($media['tag'] ?? ''),
        ];
    }

    /**
     * Finds the first media URL inside the supplied content.
     */
    public static function find_first_media_url(int $post_id, ?string $content, bool $is_video): ?string
    {
        $media = self::find_first_media($post_id, $content, 'image');
        return is_array($media) ? (string) ($media['url'] ?? null) : null;
    }

    /**
     * @return array{type:string,url:string,alt?:?string,tag?:string}|null
     */
    public static function find_first_media(int $post_id, ?string $content, string $media_type = 'all'): ?array
    {
        $content = $content ?: get_post_field('post_content', $post_id);
        if (!$content) {
            return null;
        }

        if ($media_type === 'video') {
            return null;
        }

        $image = self::find_first_image_candidate($post_id, $content);
        if ($image) {
            return ['type' => 'image'] + $image;
        }

        return null;
    }

    private static function find_first_image_candidate(int $post_id, string $content): ?array
    {
        preg_match_all('/<img[^>]*>/i', $content, $matches);
        foreach (($matches[0] ?? []) as $candidate) {
            $image = self::normalize_image_candidate($post_id, $candidate);
            if ($image) {
                return $image;
            }
        }

        return null;
    }

    private static function normalize_image_candidate(int $post_id, string $candidate): ?array
    {
        if ($candidate && preg_match('~data:image/[^;]+;base64~i', $candidate)) {
            return null;
        }

        $src = Fifu_Html_Attribute_Utils::get_attribute('src', $candidate);
        if (!is_string($src)) {
            return null;
        }

        $src = trim(html_entity_decode($src));
        if ($src === '') {
            return null;
        }

        $abs = Fifu_Url_Resolver::resolve_absolute($post_id, $src);
        if (!$abs) {
            return null;
        }

        if (self::should_skip_candidate($candidate)) {
            return null;
        }

        return [
            'url' => $abs,
            'alt' => self::normalize_image_alt(
                Fifu_Html_Attribute_Utils::get_attribute('alt', $candidate)
            ),
            'tag' => $candidate,
        ];
    }

    private static function should_skip_candidate(string $candidate): bool
    {
        $skip_list = get_option('fifu_skip');
        if (!$skip_list) {
            return false;
        }

        foreach (explode(',', (string) $skip_list) as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }

            if (strpos($candidate, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function normalize_image_alt(?string $alt): ?string
    {
        if ($alt === null) {
            return null;
        }

        $alt = html_entity_decode($alt);
        if (function_exists('wp_strip_all_tags')) {
            $alt = wp_strip_all_tags($alt);
        } else {
            $alt = strip_tags($alt);
        }

        $alt = trim($alt);
        return $alt === '' ? null : $alt;
    }
}
