<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Responsible for Jetpack/Photon CDN interactions and feature parity with legacy helpers.
 */
final class Fifu_Jetpack_Cdn_Service
{
    /**
     * Holds the legacy Jetpack size options that powered the srcset generation.
     *
     * @var int[]
     */
    public const JETPACK_SIZES = [75, 100, 150, 240, 320, 500, 640, 800, 1024, 1280, 1600];

    /**
     * Domains that identify Jetpack Photon URLs.
     *
     * @var string[]
     */
    private const PHOTON_DOMAINS = [
        'i0.wp.com', 'i1.wp.com', 'i2.wp.com', 'i3.wp.com', 'wp.fifu.app',
    ];

    /**
     * Cloudinary Fetch endpoint used for source hosts that WordPress.com
     * cannot reliably fetch directly.
     */
    private const CLOUDINARY_FETCH_URL_PREFIX = 'https://res.cloudinary.com/glide/image/fetch/';

    /**
     * Source hosts that must be fetched through Cloudinary before being
     * passed to the direct WordPress.com image CDN.
     *
     * Matching must support the exact host and true subdomains only.
     *
     * @var string[]
     */
    private const CLOUDINARY_FETCH_HOSTS = [
        'bp.blogspot.com',
        'staticflickr.com',
    ];

    private const PROXY_URL_PREFIXES = [
        'https://drive.google.com', 'https://drive.usercontent.google.com', 'https://lh3.googleusercontent.com',
        'https://s.yimg.com', 'https://s1.yimg.com', 'https://blockworks.co', 'https://coincodex.com',
        'https://www.ft.com', 'https://cdn.sellio.net', 'https://cf.bstatic.com', 'https://media-cdn.oriflame.com',
        'https://i.ytimg.com', 'https://cdn.myshoptet.com', 'https://i.imgur.com', 'https://a1.espncdn.com',
        'https://books.google.com', 'https://embed-cdn.gettyimages.com', 'https://media.gettyimages.com',
        'https://forum.rolug.ro', 'https://assets.ellosgroup.com', 'https://www.nzherald.co.nz',
        'https://img.youtube.com', 'https://cdn.diariodeavisos.com', 'https://i.guim.co.uk', 'https://www.liveaction.org',
    ];

    /**
     * Blocked hostnames that should never be routed through the public FIFU CDN.
     *
     * @var string[]
     */
    private const BLOCKED_HOSTS = [
        'localhost',
        'amazon-adsystem.com',
        'sapo.io',
        'image.influenster.com',
        'api.screenshotmachine.com',
        'img.brownsfashion.com',
        'fbcdn.net',
        'nitrocdn.com',
        'brightspotcdn.com',
        'realtysouth.com',
        'tiktokcdn.com',
        'fdcdn.akamaized.net',
        'blockchainstock.azureedge.net',
        'aa.com.tr',
        'cdn.discordapp.com',
        'download.schneider-electric.com',
        'cdn.fbsbx.com',
        'canva.com',
        'cdn.fifu.app',
        'cloud.fifu.app',
        'images.placeholders.dev',
        'images.twojjs.com',
        'preview.redd.it',
        'external-preview.redd.it',
        'i.redd.it',
    ];

    /**
     * Normalize various attachment identifier inputs into a positive integer.
     *
     * @param int|string|null $attachment_id
     * @return int|null
     */
    private static function normalize_attachment_id(int|string|null $attachment_id): ?int
    {
        if (is_int($attachment_id)) {
            return $attachment_id > 0 ? $attachment_id : null;
        }

        if (is_string($attachment_id)) {
            $normalized = filter_var(
                $attachment_id,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($normalized !== false) {
                return $normalized;
            }
        }

        return null;
    }

    private static function is_pubcdn_url(string $url): bool
    {
        return $url !== '' && strpos($url, 'wp.fifu.app') !== false;
    }

    private static function is_direct_photon_url(string $url): bool
    {
        if ($url === '') return false;
        foreach (['i0.wp.com', 'i1.wp.com', 'i2.wp.com', 'i3.wp.com'] as $domain) {
            if (strpos($url, $domain) !== false) return true;
        }
        return false;
    }

    private static function is_proxy_source(string $url): bool
    {
        foreach (self::PROXY_URL_PREFIXES as $prefix) {
            if (strpos($url, $prefix) === 0) return true;
        }
        return false;
    }

    private static function is_cloudinary_fetch_source(string $url): bool
    {
        $host = wp_parse_url($url, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        foreach (self::CLOUDINARY_FETCH_HOSTS as $domain) {
            $domain = strtolower($domain);

            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    private static function build_cloudinary_fetch_url(string $url): string
    {
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        return self::CLOUDINARY_FETCH_URL_PREFIX . rawurlencode($url);
    }

    private static function is_legacy_screenshot_source(string $url): bool
    {
        return strpos($url, 'wp.com/mshots') !== false
            || strpos($url, 'screenshot.fifu.app') !== false;
    }

    private static function normalize_direct_photon_args(string $url, array|string|null $args): array
    {
        if ($args === null || $args === '' || $args === []) return [];
        if (is_string($args)) {
            $parsed = [];
            parse_str(ltrim($args, '?'), $parsed);
            $args = $parsed;
        }
        if (array_key_exists('resize', $args)) return $args;
        if (array_key_exists('w', $args) || array_key_exists('h', $args) || array_key_exists('c', $args)) {
            $width = (int) ($args['w'] ?? 0);
            $height = (int) ($args['h'] ?? 0);
            $crop = (int) ($args['c'] ?? 0);
            if (self::is_legacy_screenshot_source($url)) {
                return [
                    'w' => $width,
                    'crop' => "0px,0px,{$width}px,{$height}px",
                ];
            }
            $direct_args = ['w' => $width];

            if (
                $crop !== 1
                && $width > 0
                && $height > 0
                && $width !== 9999
                && $height !== 9999
            ) {
                $direct_args['fit'] = "{$width},{$height}";
                return $direct_args;
            }

            $direct_args['resize'] = $height === 9999 ? (string) $width : "{$width},{$height}";
            return $direct_args;
        }
        return $args;
    }

    private static function build_attachment_photon_args(string $source_url, int $width, int $height, int $crop): array|string|null
    {
        if (self::is_proxy_source($source_url)) return "?w={$width}&h={$height}&c={$crop}";
        if ($width > 0 && $height > 0) {
            return $crop === 1
                ? ['resize' => "{$width},{$height}"]
                : ['fit' => "{$width},{$height}"];
        }
        if ($width > 0) return ['resize' => (string) $width, 'w' => $width];
        if ($height > 0) return ['resize' => (string) $height, 'h' => $height];
        return null;
    }

    private static function build_direct_photon_url(string $url, array|string|null $args): string
    {
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['path'])) return $url;
        $host = (string) $parts['host'];
        $path = (string) $parts['path'];
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $photon_url = 'https://i' . abs(crc32($url) % 4) . '.wp.com/' . $host . $path . $query;
        $direct_args = self::normalize_direct_photon_args($url, $args);
        $direct_args['ssl'] = 1;
        return add_query_arg($direct_args, $photon_url);
    }

    public static function is_jetpack_cdn_url(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        foreach (self::PHOTON_DOMAINS as $domain) {
            if (strpos($url, $domain) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function is_blocked_source(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        if (strlen($url) >= 5 && substr($url, -5) === '.avif') {
            return true;
        }

        if (self::is_jetpack_cdn_url($url)) {
            return true;
        }

        foreach (self::BLOCKED_HOSTS as $domain) {
            if (strpos($url, $domain) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function resize($size, string $url): string
    {
        if (self::is_pubcdn_url($url)) {
            $parts = parse_url($url);
            $path_parts = explode('/', trim($parts['path'] ?? '', '/'));
            $path_count = count($path_parts);
            $query_params = [];
            parse_str($parts['query'] ?? '', $query_params);
            $query_params['w'] = $size;
            $query_params['h'] = 0;
            $query_params['c'] = 0;
            if ($path_count < 4) return $url;
            $signature_index = $path_count - 2;
            unset($path_parts[$signature_index]);
            $new_path = '/' . implode('/', $path_parts);
            $new_query = http_build_query($query_params);
            $unsigned_url = '//' . ($parts['host'] ?? '') . $new_path . ($new_query ? '?' . $new_query : '');
            $new_signature = Fifu_Cdn_Signature_Service::get_signature($unsigned_url, 'fifu');
            array_splice($path_parts, $signature_index, 0, $new_signature);
            return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . '/' . implode('/', $path_parts) . ($new_query ? '?' . $new_query : '');
        }

        if (!self::is_direct_photon_url($url)) return $url;
        $size = (int) $size;
        if ($size <= 0) return $url;
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['path'])) return $url;
        $query_params = [];
        parse_str($parts['query'] ?? '', $query_params);
        $clean_url = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . $parts['path'];
        if (isset($query_params['resize'])) {
            $resize = explode(',', (string) $query_params['resize']);
            $width = isset($resize[0]) ? (int) $resize[0] : 0;
            $height = isset($resize[1]) ? (int) $resize[1] : 0;
            $new_height = $width ? (int) ($size * $height / $width) : 0;
            if ($new_height === 0) return "{$clean_url}?w={$size}&ssl=1";
            return "{$clean_url}?resize={$size},{$new_height}&ssl=1";
        }
        $delimiter = strpos($url, '?') !== false ? '&' : '?';
        return "{$url}{$delimiter}w={$size}&resize={$size}&ssl=1";
    }

    public static function build_srcset(string $url): string
    {
        $set = '';
        $count = 0;

        foreach (self::JETPACK_SIZES as $size) {
            $resized = self::resize($size, $url);
            $set .= ($count++ !== 0 ? ', ' : '') . $resized . ' ' . $size . 'w';
        }

        return $set;
    }

    public static function crop(string $url, float $w, float $h, float $p, float $q): string
    {
        $x = 0.0;
        $y = 0.0;
        $a = 0.0;
        $b = 0.0;

        if ($p !== $q) {
            if (($p / $q) >= ($w / $h)) {
                $a = $w;
                $b = $w * $q / $p;
                $y = ($h - $b) / 2;
            } else {
                $b = $h;
                $a = $h * $p / $q;
                $x = ($w - $a) / 2;
            }
        } else {
            if ($w >= $h) {
                $b = $h;
                $a = $h;
                $x = ($w - $a) / 2;
            } else {
                $a = $w;
                $b = $w;
                $y = ($h - $b) / 2;
            }
        }

        return sprintf('%s&crop=%spx,%spx,%spx,%spx', $url, $x, $y, $a, $b);
    }

    public static function build_photon_url(string $url, array|string|null $args, int|string|null $att_id): string
    {
        if (self::is_blocked_source($url)) {
            return $url;
        }

        if (Fifu_Generic_Utils::ends_with($url, '.svg')) {
            return $url;
        }

        $attachment_id = self::normalize_attachment_id($att_id);

        if (self::is_cloudinary_fetch_source($url)) {
            $url = self::build_cloudinary_fetch_url($url);
        }

        if (self::is_proxy_source($url)) {
            $arguments = '';
            if ($args) $arguments = is_array($args) ? add_query_arg($args, '?') : $args;
            return Fifu_Pubcdn_Url_Service::get_image_url($attachment_id, $url, $arguments);
        }

        return self::build_direct_photon_url($url, $args);
    }

    /**
     * Placeholder for the legacy fifu_get_photon_url behavior.
     *
     * This will eventually call Fifu_Attachment_Image_Src_Filter::apply_registered_size(),
     * Fifu_Jetpack_Cdn_Service::build_photon_url(), and delegate to
     * Fifu_Local_Media_Renderer::enrich_attachment_url().
     *
     * @param array    $image
     * @param mixed    $size
     * @param int      $attachment_id
     * @return array
     */
    public static function filter_photon_image(array $image, $size, int $attachment_id): array
    {
        $source_url = (string) ($image[0] ?? '');

        $result = Fifu_Attachment_Image_Src_Filter::apply_registered_size($image, $size);
        $image = $result['image'] ?? $image;
        $w = $image[1] ?? 0;
        $h = $image[2] ?? 0;
        $c = ($result['crop'] ?? false) ? 1 : 0;

        $arguments = self::build_attachment_photon_args($source_url, (int) $w, (int) $h, (int) $c);
        $optimized_url = self::build_photon_url($source_url, $arguments, $attachment_id);
        self::record_cdn_source_mapping($optimized_url, $source_url);

        $image[0] = Fifu_Local_Media_Renderer::enrich_attachment_url($optimized_url, $attachment_id, $size);
        self::record_cdn_source_mapping((string) ($image[0] ?? ''), $source_url);

        return $image;
    }

    /**
     * Records the original source URL for an optimized/CDN URL.
     *
     * Frontend video detection uses this map to resolve optimized image URLs
     * back to provider thumbnails such as i.vimeocdn.com, img.youtube.com or
     * Speed Up video thumbnails before deciding whether to render a play button.
     */
    private static function record_cdn_source_mapping(string $cdn_url, string $source_url): void
    {
        if ($cdn_url === '' || $source_url === '' || $cdn_url === $source_url) {
            return;
        }

        global $FIFU_SESSION;

        if (!isset($FIFU_SESSION) || !is_array($FIFU_SESSION)) {
            $FIFU_SESSION = [];
        }

        if (!isset($FIFU_SESSION['cdn-new-old']) || !is_array($FIFU_SESSION['cdn-new-old'])) {
            $FIFU_SESSION['cdn-new-old'] = [];
        }

        $FIFU_SESSION['cdn-new-old'][$cdn_url] = $source_url;
    }

    public static function get_original_image_url(string $url): string
    {
        if (!self::is_pubcdn_url($url)) {
            return $url;
        }

        return Fifu_Pubcdn_Url_Service::decode_pubcdn_url($url);
    }

    public static function replace_src(string $photon_url, string $error_url, ?int $att_id): string
    {
        if (strpos($photon_url, 'wp.fifu.app/') === false) {
            return $error_url;
        }

        $query_parameters = explode('?', $photon_url)[1] ?? '';
        $query_parameters = explode('&p=', $query_parameters)[0] ?? $query_parameters;
        $qp = $query_parameters ? '?' . $query_parameters : '';
        return Fifu_Pubcdn_Url_Service::get_image_url($att_id, $error_url, $qp);
    }

    public static function filter_jetpack_photon_skip_image($skip, $image_url, $args = null): bool
    {
        if (is_string($image_url) && Fifu_Image_Url_Utils::is_remote_image_url($image_url)) {
            return true;
        }

        return (bool) $skip;
    }

    /**
     * Provides the square resize arguments that mirror the legacy photon helper.
     *
     * These arguments are consumed by the public FIFU CDN path to ensure a square
     * thumbnail is always requested.
     *
     * @param int $size Width and height for the square image.
     * @param int $crop Flag that describes crop behaviour.
     * @return array<string,int>
     */
    public static function get_square_args(int $size, int $crop): array
    {
        return [
            'resize' => "{$size},{$size}",
        ];
    }

    /**
     * Returns a Photon/CDN URL for a given image and records the legacy session map.
     *
     * @param string|false|null $url Source image URL.
     * @param int|string|null $attachment_id Optional attachment reference.
     * @param int|null $width Requested side length.
     * @param int|null $crop Crop indicator.
     * @return string
     */
    public static function get_cdn_url(
        string|false|null $url,
        int|string|null $attachment_id,
        ?int $width,
        ?int $crop
    ): string {
        if ( ! $url ) {
            return '';
        }
        if (Fifu_Options_Utils::is_off('fifu_photon')) {
            return $url;
        }

        global $FIFU_SESSION;

        $args = null;

        $new_url = self::build_photon_url($url, $args, $attachment_id);
        $FIFU_SESSION['cdn-new-old'][$new_url] = $url;

        return $new_url;
    }
}
