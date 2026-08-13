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
     * Responds to product duplication events to copy FIFU metadata.
     *
     * @param mixed $array Product duplication context.
     * @return void
     */
    public static function on_product_duplicate( $array ): void {
        if ( ! is_object( $array ) || ! method_exists( $array, 'get_meta_data' ) || ! method_exists( $array, 'get_id' ) ) {
            return;
        }

        $meta_data = $array->get_meta_data();
        if ( empty( $meta_data ) || ! is_iterable( $meta_data ) ) {
            return;
        }

        $post_id = $array->get_id();
        $post_id = is_numeric( $post_id ) ? (int) $post_id : 0;
        if ( $post_id <= 0 ) {
            return;
        }

        foreach ( $meta_data as $meta_item ) {
            if (
                !is_object($meta_item)
                || !is_callable([$meta_item, 'get_data'])
            ) {
                continue;
            }

            $data = $meta_item->get_data();

            if (!is_array($data)) {
                continue;
            }

            $key = $data['key'] ?? '';
            if ( in_array( $key, array( 'fifu_image_url' ), true ) ) {
                delete_post_meta( $post_id, '_thumbnail_id' );
            }
        }
    }

}
