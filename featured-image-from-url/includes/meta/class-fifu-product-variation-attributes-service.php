<?php

defined( 'ABSPATH' ) || exit;

/**
 * Provides pretty variation attribute labels for products.
 */
class Fifu_Product_Variation_Attributes_Service {

    /**
     * Builds a map of pretty variation attributes for a parent product.
     *
     * @param int $parent_product_id Parent product ID.
     * @return array
     */
    public static function get_pretty_variation_attributes_map( int $parent_product_id ): array {
        $variation_map = [];

        if ( ! class_exists( 'WooCommerce' ) ) {
            return $variation_map;
        }

        $parent_product = wc_get_product( $parent_product_id );
        if ( ! $parent_product || ! $parent_product->is_type( 'variable' ) ) {
            return $variation_map;
        }

        $variations = $parent_product->get_children();
        if ( empty( $variations ) ) {
            return $variation_map;
        }

        $pretty_names = self::get_pretty_attribute_names( $parent_product_id );
        $variation_repo = new Fifu_Product_Variation_Meta_Repository();
        $attributes = $variation_repo->get_variation_attributes_by_ids( $variations );

        $pretty_names = self::filter_pretty_names( $pretty_names, $attributes );

        foreach ( $attributes as $variation_id => $attribute_values ) {
            $mapped = [];
            foreach ( $attribute_values as $key => $value ) {
                $stripped_key = preg_replace( '/^attribute_/', '', $key );
                if ( isset( $pretty_names[ $stripped_key ] ) ) {
                    $mapped[ $pretty_names[ $stripped_key ] ] = $value;
                } else {
                    $mapped[ $stripped_key ] = $value;
                }
            }
            $variation_map[ $variation_id ] = $mapped;
        }

        return $variation_map;
    }

    /**
     * Returns pretty attribute names for a single product.
     *
     * @param int $product_id Variation or parent product ID.
     * @return array
     */
    public static function get_pretty_attribute_names( int $product_id ): array {
        $attributes = get_post_meta( $product_id, '_product_attributes', true );
        $pretty_names = [];

        if ( ! is_array( $attributes ) ) {
            return $pretty_names;
        }

        foreach ( $attributes as $attribute ) {
            if ( empty( $attribute['is_variation'] ) ) {
                continue;
            }

            $name = $attribute['name'] ?? '';
            if ( empty( $name ) ) {
                continue;
            }

            $pretty_names[ $name ] = wc_attribute_label( $name );
        }

        return $pretty_names;
    }

    /**
     * Filters the pretty names against a set of attributes.
     *
     * @param array $pretty_names Pretty names map.
     * @param array $attributes   Raw product attributes.
     * @return array
     */
    private static function filter_pretty_names( array $pretty_names, array $attributes ): array {
        if ( empty( $attributes ) ) {
            return [];
        }

        $first_attribute = reset( $attributes );
        if ( ! is_array( $first_attribute ) ) {
            return [];
        }

        $first_attribute_lower_keys = array_change_key_case( $first_attribute, CASE_LOWER );

        return array_filter(
            $pretty_names,
            static function ( $key ) use ( $first_attribute_lower_keys ) {
                return array_key_exists( 'attribute_' . strtolower( $key ), $first_attribute_lower_keys );
            },
            ARRAY_FILTER_USE_KEY
        );
    }
}
