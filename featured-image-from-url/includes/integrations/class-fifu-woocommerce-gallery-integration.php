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
     * @return string
     */
    public static function filter_product_image( $html, $product, $woosize, $attr, $placeholder ): string {
        if ( empty( $product ) || ! is_object( $product ) ) {
            return $html;
        }

        return Fifu_Featured_Image_Filter::filter_post_thumbnail_html( $html, $product->get_id(), null, null, null );
    }

    /**
     * Responds to product duplication events to copy FIFU metadata.
     *
     * @param array $array Product duplication context.
     * @return void
     */
    public static function on_product_duplicate( $array ): void {
        if ( ! $array || ! $array->get_meta_data() ) {
            return;
        }

        $post_id = $array->get_id();
        foreach ( $array->get_meta_data() as $meta_data ) {
            $data = $meta_data->get_data();
            $key  = $data['key'] ?? '';
            if ( in_array( $key, array( 'fifu_image_url' ), true ) ) {
                delete_post_meta( $post_id, '_thumbnail_id' );
            }
        }
    }

}
