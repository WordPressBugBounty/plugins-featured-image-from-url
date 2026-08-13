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
     * @param mixed $doImport
     * @param mixed $post
     * @param mixed $product
     */
    public static function on_do_import_thumbnail(
        $doImport,
        $post,
        $product
    ) {
        if (!$post instanceof \WP_Post || !is_array($product)) {
            return $doImport;
        }

        $postId = is_numeric($post->ID ?? null) ? (int) $post->ID : 0;
        if ($postId <= 0) {
            return $doImport;
        }

        $imageValue = $product['image'] ?? null;

        if (
            $imageValue === null
            || !is_scalar($imageValue)
        ) {
            return $doImport;
        }

        $imageUrl = trim((string) $imageValue);

        if ($imageUrl === '') {
            return $doImport;
        }

        Fifu_Developer_Media_Service::set_image(
            $postId,
            $imageUrl
        );

        return false;
    }
}
