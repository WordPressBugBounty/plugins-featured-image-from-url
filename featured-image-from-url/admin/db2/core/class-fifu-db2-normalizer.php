<?php
declare(strict_types=1);

final class Fifu_Db2_Normalizer {
    public static function normalize_url(?string $url): ?string {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (preg_match('/^(null|undefined)$/i', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === '' || !in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if ($host === '' || in_array($host, ['null', 'undefined'], true)) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }

    public static function normalize_alt(?string $alt): ?string {
        if ($alt === null) {
            return null;
        }

        $alt = trim($alt);
        if ($alt === '') {
            return null;
        }

        if (preg_match('/^(null|undefined)$/i', $alt) === 1) {
            return null;
        }

        $clean = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($alt) : strip_tags($alt);
        $clean = trim((string) $clean);
        if ($clean === '') {
            return null;
        }

        if (preg_match('/^(null|undefined)$/i', $clean) === 1) {
            return null;
        }

        return $clean;
    }

    public static function is_valid_url(?string $url): bool {
        return self::normalize_url($url) !== null;
    }

    public static function is_valid_alt(?string $alt): bool {
        return self::normalize_alt($alt) !== null;
    }
}
