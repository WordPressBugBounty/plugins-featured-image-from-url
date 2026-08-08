<?php

defined( 'ABSPATH' ) || exit;

/**
 * Controls the visibility policy for featured media.
 */
class Fifu_Image_Display_Policy {

    /**
     * Indicates if the featured media should be hidden for a given post.
     *
     * @param int|null $post_id Optional post ID context.
     * @return bool
     */
    public static function should_hide_featured_media( ?int $post_id = null ): bool {
        if ( Fifu_Options_Utils::is_off( 'fifu_hide' ) ) {
            return false;
        }

        global $post;
        $current_post_id = $post_id ?? ( $post->ID ?? null );
        if (
            $current_post_id
            && class_exists( 'Fifu_Woocommerce_Context' )
            && Fifu_Woocommerce_Context::is_product_context_for_post( (int) $current_post_id )
        ) {
            return false;
        }
        if ( isset( $post->ID ) && $post->ID !== get_queried_object_id() ) {
            return false;
        }

        $post_types_string = get_option( 'fifu_hide_type' );
        $post_types_array = $post_types_string ? explode( ',', $post_types_string ) : [];
        if ( $post_types_string && ! is_singular( $post_types_array ) ) {
            return false;
        }

        $formats = get_option( 'fifu_hide_format' );
        if ( $current_post_id && $formats ) {
            $post_format = get_post_format( $current_post_id );
            if ( false === $post_format ) {
                $post_format = 'standard';
            }
            if ( ! in_array( $post_format, explode( ',', $formats ), true ) ) {
                return false;
            }
        }

        $post_type = $current_post_id ? get_post_type( $current_post_id ) : get_post_type( get_the_ID() );

        return ! is_front_page() && $post_type ? is_singular( $post_type ) : false;
    }
}
