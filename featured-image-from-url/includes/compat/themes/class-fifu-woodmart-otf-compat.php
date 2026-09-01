<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Prevents WoodMart OTF thumbnail generation from treating FIFU remote
 * attachments as local filesystem images.
 */
class Fifu_Woodmart_Otf_Compat
{
    private const WOODMART_OTF_CALLBACK = 'gambit_otf_regen_thumbs_media_downsize';

    public static function register(): void
    {
        add_filter(
            'image_downsize',
            [self::class, 'filter_image_downsize'],
            9,
            3
        );
    }

    /**
     * @param mixed $out
     * @param mixed $attachment_id
     * @param mixed $size
     * @return mixed
     */
    public static function filter_image_downsize($out, $attachment_id, $size)
    {
        if (!is_numeric($attachment_id)) {
            return $out;
        }

        $attachment_id = absint($attachment_id);

        if ($attachment_id <= 0) {
            return $out;
        }

        $woodmart_priority = has_filter(
            'image_downsize',
            self::WOODMART_OTF_CALLBACK
        );

        if (false === $woodmart_priority) {
            return $out;
        }

        if (!Fifu_Post_Meta_Utils::is_remote_image($attachment_id)) {
            return $out;
        }

        $remote_url = self::get_remote_url($attachment_id);

        if (!self::is_remote_url($remote_url)) {
            return $out;
        }

        [$width, $height] = self::get_dimensions(
            $attachment_id,
            $size
        );

        if ($width <= 0 || $height <= 0) {
            return $out;
        }

        $removed = remove_filter(
            'image_downsize',
            self::WOODMART_OTF_CALLBACK,
            (int) $woodmart_priority
        );

        if (!$removed) {
            return $out;
        }

        return [
            $remote_url,
            $width,
            $height,
            false,
        ];
    }

    private static function get_remote_url(int $attachment_id): string
    {
        $remote_url = '';

        if (function_exists('fifu_get_raw_remote_attached_file')) {
            $remote_url = fifu_get_raw_remote_attached_file($attachment_id);
        }

        if ($remote_url === '') {
            $remote_url = trim(
                (string) get_post_meta(
                    $attachment_id,
                    '_wp_attached_file',
                    true
                )
            );
        }

        return trim($remote_url);
    }

    private static function is_remote_url(string $url): bool
    {
        return preg_match(
            '~^(https?://|//)~i',
            $url
        ) === 1;
    }

    /**
     * @param mixed $size
     * @return array{0:int,1:int}
     */
    private static function get_dimensions(
        int $attachment_id,
        $size
    ): array {
        $size_details = Fifu_Image_Size_Usage_Tracker::get_image_size_details(
            $size
        );

        $requested_width = max(
            0,
            (int) ($size_details['width'] ?? 0)
        );

        $requested_height = max(
            0,
            (int) ($size_details['height'] ?? 0)
        );

        $metadata = wp_get_attachment_metadata($attachment_id);

        $original_width = 0;
        $original_height = 0;

        if (is_array($metadata)) {
            if (isset($metadata['width']) && is_numeric($metadata['width'])) {
                $original_width = max(
                    0,
                    (int) $metadata['width']
                );
            }

            if (isset($metadata['height']) && is_numeric($metadata['height'])) {
                $original_height = max(
                    0,
                    (int) $metadata['height']
                );
            }
        }

        if ($requested_width > 0 && $requested_height > 0) {
            return [
                $requested_width,
                $requested_height,
            ];
        }

        if (
            $requested_width > 0
            && $original_width > 0
            && $original_height > 0
        ) {
            return [
                $requested_width,
                max(
                    1,
                    (int) round(
                        $original_height
                        * ($requested_width / $original_width)
                    )
                ),
            ];
        }

        if (
            $requested_height > 0
            && $original_width > 0
            && $original_height > 0
        ) {
            return [
                max(
                    1,
                    (int) round(
                        $original_width
                        * ($requested_height / $original_height)
                    )
                ),
                $requested_height,
            ];
        }

        if ($original_width > 0 && $original_height > 0) {
            return [
                $original_width,
                $original_height,
            ];
        }

        return [0, 0];
    }
}
