<?php

defined( 'ABSPATH' ) || exit;

/**
 * Integrates FIFU with Yoast SEO image tags.
 */
class Fifu_Yoast_Image_Integration {

    /**
     * Adjusts the Yoast OG image tag for FIFU remote media.
     *
     * @param mixed $image_url OG image value.
     * @return mixed
     */
    public static function filter_og_image( $image_url ) {
        if ( is_front_page() || is_home() ) {
            return $image_url;
        }

        $post_id = (int) get_queried_object_id();
        if ( ! $post_id ) {
            return $image_url;
        }

        if ( get_post_meta( $post_id, '_yoast_wpseo_opengraph-image', true ) || get_post_meta( $post_id, '_yoast_wpseo_twitter-image', true ) ) {
            return $image_url;
        }

        $main_image = Fifu_Post_Main_Image_Resolver::get_main_image_url( $post_id, true );
        return $main_image ? $main_image : $image_url;
    }

    /**
     * Adjusts Yoast OG images list when FIFU is present.
     *
     * @param mixed $object Yoast SEO object.
     * @return void
     */
    public static function filter_og_images( $object ): void {
        if ( is_front_page() || is_home() ) {
            return;
        }

        $post_id = (int) get_queried_object_id();
        if ( ! $post_id ) {
            return;
        }

        if ( get_post_meta( $post_id, '_yoast_wpseo_opengraph-image', true ) || get_post_meta( $post_id, '_yoast_wpseo_twitter-image', true ) ) {
            return;
        }

        $main_image = Fifu_Post_Main_Image_Resolver::get_main_image_url( $post_id, true );
        if ( $main_image ) {
            $object->add_image( $main_image );
        }
    }
}
