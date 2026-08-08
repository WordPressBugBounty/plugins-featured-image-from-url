<?php

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce-specific context helpers for FIFU.
 *
 * @package Fifu_Free
 */
class Fifu_Woocommerce_Context {

    public static function is_home_or_shop() {
        return is_home() || self::is_shop();
    }

    /**
     * Returns true only for a real WooCommerce single-product context.
     *
     * Some integration tests and theme/plugin stubs can leave is_product()
     * truthy after the current queried object has changed. FIFU must not let
     * that stale state suppress normal post/page featured media behavior.
     */
    public static function is_product(): bool {
        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'is_product' ) || ! is_product() ) {
            return false;
        }

        $queried_object_id = (int) get_queried_object_id();
        if ( $queried_object_id > 0 ) {
            return get_post_type( $queried_object_id ) === 'product';
        }

        global $post;
        if ( isset( $post->ID ) ) {
            return get_post_type( (int) $post->ID ) === 'product';
        }

        return false;
    }

    /**
     * Returns true only when the current WooCommerce product context belongs
     * to the post currently being rendered.
     */
    public static function is_product_context_for_post( int $post_id ): bool {
        return self::is_product() && get_post_type( $post_id ) === 'product';
    }

    public static function is_shop() {
        return class_exists( 'WooCommerce' ) && ( is_shop() || is_product_category() );
    }

    public static function is_woo_variation_swatches_taxonomy( $term_id ) {
        if ( class_exists( 'Fifu_Plugin_Detector' ) && Fifu_Plugin_Detector::is_woo_variation_swatches_active() ) {
            $term = get_term( $term_id );
            if ( $term !== null && ! is_wp_error( $term ) ) {
                return strpos( $term->taxonomy, 'pa_' ) === 0;
            }
        }
        return false;
    }

    /**
     * Detects if the current admin listing is the products table.
     *
     * @return bool
     */
    public static function is_products_admin_list(): bool {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos( $uri, 'wp-admin/edit.php' ) !== false && strpos( $uri, 'post_type=product' ) !== false;
    }

    /**
     * Detects if the current admin listing is the product categories table.
     *
     * @return bool
     */
    public static function is_product_categories_admin_list(): bool {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos( $uri, 'wp-admin/edit-tags.php?taxonomy=product_cat&post_type=product' ) !== false;
    }

    /**
     * Checks whether a given post ID references a variable product.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    public static function is_variable_product( int $post_id ): bool {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return false;
        }

        $product = wc_get_product( $post_id );
        return $product ? $product->get_type() === 'variable' : false;
    }
}
