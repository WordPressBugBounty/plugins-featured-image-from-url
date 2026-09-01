<?php

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the WooCommerce callbacks still required by FIFU Free.
 */
class Fifu_Woocommerce_Gallery_Integration {

    /**
     * Filters the HTML output for a single product image.
     *
     * @param string $html        Original image HTML.
     * @param mixed  $product     Product object.
     * @param string $woosize     WooCommerce image size.
     * @param array  $attr        HTML attributes for the image.
     * @param string $placeholder Placeholder HTML when no image.
     */
    public static function filter_product_image( $html, $product, $woosize, $attr, $placeholder ) {
        if ( empty( $product ) || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
            return $html;
        }

        return Fifu_Featured_Image_Filter::filter_post_thumbnail_html( $html, $product->get_id(), null, null, null );
    }

    /**
     * Normalizes a native WooCommerce product duplicate into independent
     * FIFU featured-image state.
     *
     * WooCommerce copies normal product metadata, including the featured
     * attachment reference, but FIFU DB2 mappings live outside post meta.
     * Resolve the image from the source product and let FIFU create
     * independent DB2 and attachment state for the duplicate.
     *
     * @param mixed $duplicate Duplicated WooCommerce product object.
     * @param mixed $source    Source WooCommerce product object.
     * @return void
     */
    public static function on_product_duplicate(
        $duplicate,
        $source
    ): void {
        if (
            !is_object($duplicate)
            || !method_exists($duplicate, 'get_id')
            || !is_object($source)
            || !method_exists($source, 'get_id')
        ) {
            return;
        }

        $duplicate_post_id = $duplicate->get_id();
        $duplicate_post_id = is_numeric($duplicate_post_id)
            ? (int) $duplicate_post_id
            : 0;

        $source_post_id = $source->get_id();
        $source_post_id = is_numeric($source_post_id)
            ? (int) $source_post_id
            : 0;

        if (
            $duplicate_post_id <= 0
            || $source_post_id <= 0
            || $duplicate_post_id === $source_post_id
        ) {
            return;
        }

        $url = Fifu_Post_Image_Url_Read_Service::get_image_url(
            $source_post_id
        );

        /*
         * A product using a normal local WordPress featured image has no
         * FIFU URL. Leave WooCommerce's copied thumbnail reference alone.
         */
        if (
            $url === null
            || trim($url) === ''
        ) {
            return;
        }

        $alt = Fifu_Post_Image_Alt_Read_Service::get_image_alt(
            $source_post_id
        );

        /*
         * WooCommerce may have copied the source FIFU attachment ID.
         *
         * Remove only the duplicate's reference before asking FIFU to create
         * its own attachment. Never delete or modify the source attachment.
         */
        delete_post_meta(
            $duplicate_post_id,
            '_thumbnail_id'
        );

        if (
            !Fifu_Developer_Media_Service::set_image(
                $duplicate_post_id,
                $url,
                true
            )
        ) {
            return;
        }

        $alt_persisted = false;

        if (
            $alt !== null
            && trim($alt) !== ''
            && function_exists('fifu_db2_manager')
        ) {
            $manager = fifu_db2_manager();

            if (
                $manager instanceof Fifu_Db2_Manager
                && method_exists(
                    $manager,
                    'savePostAlt'
                )
            ) {
                $alt_persisted =
                    $manager->savePostAlt(
                        $duplicate_post_id,
                        'image',
                        0,
                        $alt
                    );
            }
        }

        /*
         * WooCommerce may also have copied legacy FIFU post meta.
         *
         * The duplicate has now been normalized into DB2, so remove only
         * the duplicate's legacy copies. Do not alter the source product.
         */
        delete_post_meta(
            $duplicate_post_id,
            'fifu_image_url'
        );

        if (
            $alt === null
            || trim($alt) === ''
            || $alt_persisted
        ) {
            delete_post_meta(
                $duplicate_post_id,
                'fifu_image_alt'
            );
        }
    }

}
