<?php

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the featured image URL for a given post.
 */
class Fifu_Post_Main_Image_Resolver {

    /**
     * Determines the canonical featured image URL for a post.
     *
     * @param int  $post_id The post ID to resolve.
     * @param bool $front   Whether the resolution is for a front-end request.
     * @return string|null
     */
    /**
     * Determines the canonical featured image URL for a post.
     *
     * @param int    $post_id   The post ID to resolve.
     * @param bool   $front     Whether the resolution is for a front-end request.
     * @param array  $meta_data Optional pre-fetched FIFU meta values keyed by meta_key.
     * @return string|null
     */
    public static function get_main_image_url( int $post_id, bool $front = false, array $meta_data = [] ): ?string {
        if ( class_exists( 'Fifu_Post_Featured_Media_State_Resolver', false ) ) {
            $state = Fifu_Post_Featured_Media_State_Resolver::resolve( $post_id );
            $stateType = is_array( $state ) ? (string) ( $state['type'] ?? '' ) : '';
            $stateUrl = is_array( $state ) ? (string) ( $state['url'] ?? '' ) : '';

            if ( $stateType === 'image' && $stateUrl !== '' ) {
                return self::normalize_url( $stateUrl );
            }
        }

        $url = self::resolve_meta_value( $post_id, 'fifu_image_url', $meta_data );

        if (
            ! $url
            && Fifu_Post_Meta_Utils::has_no_internal_image( $post_id )
            && Fifu_Options_Utils::is_on( 'fifu_enable_default_url' )
            && Fifu_Post_Type_Utils::is_valid_default_cpt( $post_id )
        ) {
            $url = Fifu_Options_Utils::get_default_image_url();
        }

        return self::normalize_url( $url );
    }

    /**
     * Retrieve a FIFU meta field value, preferring cached metadata if provided.
     *
     * @param int    $post_id
     * @param string $key
     * @param array  $meta_data
     * @return string|null
     */
    private static function resolve_meta_value( int $post_id, string $key, array $meta_data ): ?string {
        if ( array_key_exists( $key, $meta_data ) ) {
            $value = $meta_data[ $key ];
            return $value !== '' && $value !== null ? (string) $value : null;
        }

        if ( $key === 'fifu_image_url' ) {
            return Fifu_Post_Image_Url_Read_Service::get_image_url( $post_id );
        }

        $value = get_post_meta( $post_id, $key, true );
        return $value !== '' && $value !== null ? (string) $value : null;
    }

    /**
     * Normalize URLs to the canonical representation used by the resolver.
     *
     * @param string|null $url
     * @return string|null
     */
    private static function normalize_url( ?string $url ): ?string {
        if ( $url === null ) {
            return null;
        }

        $url = htmlspecialchars_decode( $url );
        $url = str_replace( "'", '%27', $url );
        $url = trim( $url );

        return $url !== '' ? $url : null;
    }

}
