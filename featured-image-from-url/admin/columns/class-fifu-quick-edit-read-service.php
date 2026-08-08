<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

final class Fifu_Quick_Edit_Read_Service
{
    private const COLUMN_HEIGHT = 40;

    /**
     * Returns lightweight thumbnail data for the variable selector.
     *
     * @return array{
     *     border:string,
     *     height:int,
     *     width:float,
     *     image_url:string,
     *     url:string
     * }
     */
    public static function get_display(int $post_id): array
    {
        $border = '';
        $height = self::COLUMN_HEIGHT;
        $width = $height * 1.0;

        $image_url = Fifu_Post_Main_Image_Resolver::get_main_image_url($post_id, true);

        if ($image_url === '' || $image_url === null) {
            $attachment_url = wp_get_attachment_url(get_post_thumbnail_id($post_id));

            $image_url = $attachment_url === false ? '' : (string) $attachment_url;

            $border = 'border-color: #ca4a1f !important; border: 2px; border-style: dotted; border-radius: 8px;';
        }

        $url = Fifu_Cdn_Thumbnail_Resolver::get_optimized_thumbnail_url((string) $image_url, get_post_thumbnail_id($post_id), 150, 1);

        return [
            'border' => $border,
            'height' => $height,
            'width' => $width,
            'image_url' => (string) $image_url,
            'url' => (string) $url,
        ];
    }

    /**
     * Returns the featured-image fields consumed by FIFU Free Quick Edit.
     *
     * @return array{fifu_image_url:string,fifu_image_alt:string}
     */
    public static function get_payload(int $post_id): array
    {
        return [
            'fifu_image_url' => (string) Fifu_Post_Image_Url_Read_Service::get_image_url($post_id),
            'fifu_image_alt' => (string) Fifu_Post_Image_Alt_Read_Service::get_image_alt($post_id),
        ];
    }

    /**
     * @return array{
     *     post_id:int,
     *     parent_id:int,
     *     display:array<string,mixed>,
     *     payload:array<string,mixed>
     * }
     */
    public static function get_item_response(int $post_id): array
    {
        return [
            'post_id' => $post_id,
            'parent_id' => (int) wp_get_post_parent_id($post_id),
            'display' => self::get_display($post_id),
            'payload' => self::get_payload($post_id),
        ];
    }
}
