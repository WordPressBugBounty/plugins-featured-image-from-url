<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Datafeedr_Integration
{
    public static function register_hooks(): void
    {
        add_filter(
            'dfrps_do_import_product_thumbnail/do_import',
            [self::class, 'on_do_import_thumbnail'],
            10,
            3
        );
    }

    /**
     * Save only Datafeedr's primary image as the Free featured image.
     *
     * Alternate images and product-gallery creation are Premium-only.
     *
     * @param bool $doImport
     * @param WP_Post $post
     * @param array $product
     */
    public static function on_do_import_thumbnail(
        bool $doImport,
        WP_Post $post,
        array $product
    ): bool {
        $imageUrl = isset($product['image'])
            ? trim((string) $product['image'])
            : '';

        if ($imageUrl === '') {
            return $doImport;
        }

        Fifu_Developer_Media_Service::set_image(
            (int) $post->ID,
            $imageUrl
        );

        return false;
    }
}
