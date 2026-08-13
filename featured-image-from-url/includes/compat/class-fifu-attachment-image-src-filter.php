<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides WordPress attachment image src filtering utilities.
 */
final class Fifu_Attachment_Image_Src_Filter
{
    /**
     * Filter callback for {@see 'wp_get_attachment_image_src'}.
     *
     * @param mixed $image
     * @param int|string $att_id
     * @param mixed $size
     *
     * @return mixed
     */
    public static function filter_attachment_image_src($image, $att_id, $size)
    {
        $att_id = absint( $att_id );

        if ( ! $att_id ) {
            return $image;
        }

        if (!is_array($image)) {
            return $image;
        }

        if (
            !$image
            || Fifu_Theme_Detector::is_houzez_active()
            || Fifu_Theme_Detector::is_wpresidence_active()
        ) {
            return $image;
        }

        if (!self::is_fifu_attachment($att_id)) {
            return $image;
        }

        global $FIFU_SESSION;
        $prev_url = null;

        if (
            isset($FIFU_SESSION['cdn-new-old'])
            && isset($image[0])
            && isset($FIFU_SESSION['cdn-new-old'][$image[0]])
        ) {
            $prev_url = $FIFU_SESSION['cdn-new-old'][$image[0]];
        }

        $FIFU_SESSION['att_img_src'] = $FIFU_SESSION['att_img_src'] ?? [];
        $image[0] = Fifu_Attachment_File_Filters::filter_attachment_url($image[0] ?? '', $att_id);

        $original_url = Fifu_Post_Main_Image_Resolver::get_main_image_url(get_queried_object_id(), true);
        if (
            Fifu_Image_Display_Policy::should_hide_featured_media()
            && (
                $original_url === $image[0]
                || ($prev_url && $prev_url === $original_url)
            )
        ) {
            if (!in_array($original_url, $FIFU_SESSION['att_img_src'])) {
                $aux = is_array($size) ? implode(',', $size) : $size;
                $FIFU_SESSION['att_img_src'][] = $original_url . $aux;
                return null;
            }
        }

        $FIFU_SESSION['att_img_src'][] = $original_url;

        if (Fifu_Speedup_Url_Service::is_speedup_url($image[0] ?? '')) {
            $image = Fifu_Speedup_Url_Service::get_speedup_image($image, $size, $att_id);
        }

        if (
            Fifu_Options_Utils::is_on('fifu_photon')
            && !Fifu_Jetpack_Cdn_Service::is_blocked_source($image[0] ?? '')
            && !Fifu_Wp_Context::is_editor_screen()
        ) {
            $image = Fifu_Jetpack_Cdn_Service::filter_photon_image($image, $size, $att_id);
        }

        if (($image[1] ?? 0) <= 1 && ($image[2] ?? 0) <= 1) {
            $result = self::apply_registered_size($image, $size);
            $image = $result['image'] ?? $image;
        }

        return $image;
    }

    /**
     * Applies the legacy size registration adjustments used by fifu_add_size().
     *
     * @param array $image
     * @param mixed $size
     *
     * @return array{image: array, crop: bool|null}
     */
    public static function apply_registered_size(array $image, $size): array
    {
        return self::normalize_size($image, $size);
    }

    private static function is_fifu_attachment(int $attachment_id): bool
    {
        if ($attachment_id <= 0) {
            return false;
        }

        if (function_exists('fifu_is_fifu_attachment')) {
            return fifu_is_fifu_attachment($attachment_id);
        }

        $att_post = get_post($attachment_id);
        if (!$att_post || !isset($att_post->post_author)) {
            return false;
        }

        $authors = [];

        if (function_exists('fifu_get_author')) {
            $authors[] = (int) fifu_get_author();
        }

        if (class_exists('Fifu_Options_Utils', false) && method_exists('Fifu_Options_Utils', 'get_author')) {
            $authors[] = (int) Fifu_Options_Utils::get_author();
        }

        if (defined('FIFU_AUTHOR')) {
            $authors[] = (int) FIFU_AUTHOR;
        }

        $authors[] = 77777;
        $authors[] = 7777777777;

        $authors = array_values(array_unique(array_filter($authors, static fn($id) => (int) $id > 0)));

        return in_array((int) $att_post->post_author, $authors, true);
    }

    /**
     * Registers the pixel dimensions and crop flags derived from the requested size.
     *
     * @param array $image
     * @param mixed $size
     *
     * @return array{image: array, crop: bool|null}
     */
    private static function normalize_size(array $image, $size): array
    {
        $size_details = Fifu_Image_Size_Usage_Tracker::get_image_size_details($size);
        if (!($size_details['width'] ?? false) && !($size_details['height'] ?? false)) {
            return [
                'image' => $image,
                'crop' => null,
            ];
        }

        $image[1] = $size_details['width'] ?? 0;
        $image[2] = $size_details['height'] ?? 0;

        return [
            'image' => $image,
            'crop' => $size_details['crop'] ?? false,
        ];
    }
}
