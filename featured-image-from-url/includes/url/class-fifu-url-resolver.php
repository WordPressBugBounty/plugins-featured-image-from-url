<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Url_Resolver
{
    /**
     * Resolves a URL to an absolute value using post context when needed.
     */
    public static function resolve_absolute(int $post_id, string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (stripos($url, 'data:') === 0) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        if (preg_match('/^(?:www\.)?[a-z0-9.-]+\.[a-z]{2,}(?::\d+)?(?:\/.+)?$/i', $url)) {
            $scheme = is_ssl() ? 'https' : 'http';
            return $scheme . '://' . ltrim($url, '/');
        }

        if (strpos($url, '//') === 0) {
            $scheme = is_ssl() ? 'https:' : 'http:';
            return $scheme . $url;
        }

        $base = get_permalink($post_id);
        if (!$base) {
            $base = home_url('/');
        }

        $base = self::normalize_base_url($base);
        $base_parts = wp_parse_url($base);
        if (!$base_parts || empty($base_parts['host'])) {
            return null;
        }

        $scheme = $base_parts['scheme'] ?? (is_ssl() ? 'https' : 'http');
        $host = $base_parts['host'];
        $port = isset($base_parts['port']) ? ':' . $base_parts['port'] : '';
        $base_path = $base_parts['path'] ?? '/';

        if (!isset($base_parts['scheme']) && isset($base_parts['host'])) {
            $scheme = is_ssl() ? 'https' : 'http';
        }

        if (isset($url[0]) && $url[0] === '/') {
            $path = self::remove_dot_segments($url);
            return $scheme . '://' . $host . $port . $path;
        }

        if (isset($url[0]) && ($url[0] === '?' || $url[0] === '#')) {
            return $scheme . '://' . $host . $port . $base_path . $url;
        }

        $dir = substr($base_path, -1) === '/' ? rtrim($base_path, '/') : rtrim(dirname($base_path), '/');
        if ($dir === '/' || $dir === '\\') {
            $dir = '';
        }
        $path = ($dir ? $dir : '') . '/' . $url;
        $path = self::remove_dot_segments($path);

        return $scheme . '://' . $host . $port . $path;
    }

    private static function normalize_base_url(string $base): string
    {
        $base = trim($base);
        if ($base === '') {
            return $base;
        }

        if (preg_match('/^https?:\/\//i', $base) || strpos($base, '//') === 0) {
            return $base;
        }

        if (preg_match('/^(?:[a-z0-9.-]+\.)+[a-z]{2,}(?::\d+)?(?:\/.*)?$/i', $base)) {
            return (is_ssl() ? 'https://' : 'http://') . ltrim($base, '/');
        }

        return $base;
    }

    /**
     * Normalizes path segments to remove "." and ".." references.
     */
    private static function remove_dot_segments(string $path): string
    {
        $leading_slash = (strlen($path) > 0 && $path[0] === '/');
        $segments = explode('/', $path);
        $output = [];
        foreach ($segments as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($output);
                continue;
            }
            $output[] = $seg;
        }
        $normalized = ($leading_slash ? '/' : '') . implode('/', $output);
        if ($normalized !== '/' && substr($path, -1) === '/') {
            $normalized .= '/';
        }
        return $normalized === '' ? '/' : $normalized;
    }
}
