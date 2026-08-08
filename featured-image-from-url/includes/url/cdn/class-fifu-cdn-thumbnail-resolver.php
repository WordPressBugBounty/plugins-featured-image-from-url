<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Neutral resolver that orchestrates FIFU CDN providers for thumbnail URLs.
 */
final class Fifu_Cdn_Thumbnail_Resolver
{
    /**
     * Resolve the best thumbnail URL for a specific image while keeping provider details hidden.
     *
     * This method replaces the legacy fifu_optimized_column_image helper by delegating to
     * FIFU Cloud (Speedup) or Jetpack Photon depending on availability.
     *
     * @param string $url Original image URL.
     * @param int|null $attachment_id Optional attachment reference for signed URLs.
     * @param int $size Target size used when resizing via Photon.
     * @param int|null $crop Crop flag for square Photon requests.
     * @return string
     */
    public static function get_optimized_thumbnail_url(
        string $url,
        ?int $attachment_id,
        int $size = 150,
        ?int $crop = 1
    ): string {
        if (Fifu_Speedup_Url_Service::is_speedup_url($url)) {
            $parts = explode('?', $url);
            $clean_url = $parts[0] ?? $url;
            return Fifu_Speedup_Url_Service::get_signed_url($clean_url, 128, 128, null, null, false);
        }

        if (Fifu_Options_Utils::is_on('fifu_photon')) {
            $args = "?w={$size}&h={$size}&c=" . (int) ($crop ?? 1);

            return Fifu_Jetpack_Cdn_Service::build_photon_url($url, $args, $attachment_id);
        }

        return $url;
    }
}
