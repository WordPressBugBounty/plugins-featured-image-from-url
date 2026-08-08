<?php

defined('ABSPATH') || exit;

/**
 * Ensures FIFU CDN images include signed URLs, srcset, and lazy-loading attributes.
 */
class Fifu_Attachment_Image_Attributes_Filter {

    /**
     * Filters attachment image attributes for FIFU CDN images.
     *
     * @param array      $attr
     * @param \WP_Post   $attachment
     * @param string|int $size
     * @return array
     */
    public static function filter_attributes(array $attr, $attachment, $size): array {
        // ignore themes
        if (in_array(strtolower(get_option('template')), array('jnews'))) {
            return $attr;
        }

        if (!isset($attr['src'])) {
            return $attr;
        }

        $original_src = $attr['src'];
        if (strpos($original_src, 'cdn.fifu.app') === false) {
            return $attr;
        }

        $current_screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($current_screen && (($current_screen->parent_file ?? '') == 'edit.php?post_type=product')) {
            $attr['src'] = Fifu_Cdn_Thumbnail_Resolver::get_optimized_thumbnail_url($original_src, $attachment->ID ?? null);
            return $attr;
        }

        $sizes = Fifu_Speedup_Url_Service::parse_sizes($original_src);
        $width = $sizes[0] ?? 0;
        $height = $sizes[1] ?? 0;
        $is_video_thumbnail = self::is_video_thumbnail_url($original_src);
        $signed_src = Fifu_Speedup_Url_Service::get_signed_url($original_src, $width, $height, null, null, $is_video_thumbnail);

        $attr['src'] = $signed_src;
        $attr['loading'] = 'lazy';
        self::register_cdn_mapping($signed_src, $original_src);

        $attr['srcset'] = self::sign_srcset_and_register_mappings(
            $original_src,
            Fifu_Speedup_Url_Service::get_srcset($original_src)
        );

        return $attr;
    }

    private static function is_video_thumbnail_url(string $url): bool {
        return Fifu_Speedup_Url_Service::has_video_thumb($url);
    }

    private static function register_cdn_mapping(string $new_url, string $old_url): void {
        global $FIFU_SESSION;

        if (!$new_url || !$old_url || $new_url === $old_url) {
            return;
        }

        $FIFU_SESSION['cdn-new-old'] = $FIFU_SESSION['cdn-new-old'] ?? array();
        $FIFU_SESSION['cdn-new-old'][$new_url] = self::canonicalize_lookup_url($old_url);
    }

    private static function sign_srcset_and_register_mappings(string $original_src, string $srcset): string {
        if (!$srcset) {
            return $srcset;
        }

        $sizes = Fifu_Speedup_Url_Service::parse_sizes($original_src);
        $original_width = $sizes[0] ?? 0;
        $original_height = $sizes[1] ?? 0;
        $base_original_src = self::remove_query_parameter_preserving_format($original_src, 'resize');
        $signed_candidates = array();
        foreach (preg_split('/\s*,\s*/', trim($srcset)) ?: array() as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if (!preg_match('/^(\S+)(?:\s+(.*))?$/', $candidate, $matches)) {
                $signed_candidates[] = $candidate;
                continue;
            }

            $signed_url = $matches[1];
            $descriptor = isset($matches[2]) ? trim($matches[2]) : '';
            $original_candidate = self::build_original_srcset_candidate(
                $base_original_src,
                $original_width,
                $original_height,
                $descriptor
            );

            self::register_cdn_mapping($signed_url, $original_candidate);
            $signed_candidates[] = $descriptor ? "{$signed_url} {$descriptor}" : $signed_url;
        }

        return implode(', ', $signed_candidates);
    }

    private static function build_original_srcset_candidate(
        string $base_original_src,
        int $original_width,
        int $original_height,
        string $descriptor
    ): string {
        if (!$descriptor || !$original_width || !$original_height) {
            return $base_original_src;
        }

        if (!preg_match('/^(\d+)w$/', $descriptor, $matches)) {
            return $base_original_src;
        }

        $width = (int) ($matches[1] ?? 0);
        if (!$width) {
            return $base_original_src;
        }

        $height = (int) round($width * $original_height / max(1, $original_width));
        return self::append_query_parameter_preserving_format($base_original_src, 'resize', "{$width},{$height}");
    }

    private static function canonicalize_lookup_url(string $url): string {
        if (!$url || strpos($url, '?') === false) {
            return $url;
        }

        [$base, $query, $fragment] = self::split_url_query_and_fragment($url);
        if ($query === '') {
            return $url;
        }

        $canonical_pairs = array();
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            $parts = explode('=', $pair, 2);
            $key = $parts[0] ?? '';
            $value = $parts[1] ?? null;
            if ($value === null) {
                $canonical_pairs[] = $pair;
                continue;
            }

            if ($key === 'video-thumb' || $key === 'resize') {
                $value = rawurldecode($value);
            }

            $canonical_pairs[] = $key . '=' . $value;
        }

        return self::rebuild_url($base, $canonical_pairs, $fragment);
    }

    private static function remove_query_parameter_preserving_format(string $url, string $parameter): string {
        [$base, $query, $fragment] = self::split_url_query_and_fragment($url);
        if ($query === '') {
            return $url;
        }

        $filtered = array();
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            $key = explode('=', $pair, 2)[0] ?? '';
            if ($key === $parameter) {
                continue;
            }

            $filtered[] = $pair;
        }

        return self::rebuild_url($base, $filtered, $fragment);
    }

    private static function append_query_parameter_preserving_format(string $url, string $parameter, string $value): string {
        [$base, $query, $fragment] = self::split_url_query_and_fragment($url);
        $pairs = $query === '' ? array() : array_filter(explode('&', $query), static fn($pair): bool => $pair !== '');
        $pairs[] = $parameter . '=' . $value;
        return self::rebuild_url($base, $pairs, $fragment);
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private static function split_url_query_and_fragment(string $url): array {
        $fragment = '';
        $fragment_index = strpos($url, '#');
        if ($fragment_index !== false) {
            $fragment = substr($url, $fragment_index + 1);
            $url = substr($url, 0, $fragment_index);
        }

        $query = '';
        $query_index = strpos($url, '?');
        if ($query_index !== false) {
            $query = substr($url, $query_index + 1);
            $url = substr($url, 0, $query_index);
        }

        return array($url, $query, $fragment);
    }

    /**
     * @param string[] $query_pairs
     */
    private static function rebuild_url(string $base, array $query_pairs, string $fragment): string {
        $rebuilt = $base;
        if (!empty($query_pairs)) {
            $rebuilt .= '?' . implode('&', $query_pairs);
        }
        if ($fragment !== '') {
            $rebuilt .= '#' . $fragment;
        }
        return $rebuilt;
    }
}
