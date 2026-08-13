<?php

defined( 'ABSPATH' ) || exit;

/**
 * Controls how post content interacts with the featured image.
 */
class Fifu_Content_Image_Controller {

    /**
     * Prepends the featured image to the rendered post content.
     *
     * @param mixed $content Original post content.
     */
    public static function append_featured_image( $content ) {
        if ( ! is_string( $content ) ) {
            return $content;
        }

        if ( Fifu_Options_Utils::is_off( 'fifu_pcontent_add' ) ) {
            return $content;
        }

        $post_types_string = (string) get_option( 'fifu_pcontent_types' );
        $post_types_array  = array_filter(
            array_map( 'trim', explode( ',', $post_types_string ) )
        );

        if ( $post_types_string && ! is_singular( $post_types_array ) ) {
            return $content;
        }

        if ( has_post_thumbnail() ) {
            return '<div style="text-align:center">' . get_the_post_thumbnail() . '</div>' . $content;
        }

        return $content;
    }

    /**
     * Removes the first content image that exactly matches the featured image URL.
     *
     * @param mixed $content Original post content.
     */
    public static function remove_duplicate_featured_from_content( $content ) {
        if ( ! is_string( $content ) ) {
            return $content;
        }

        if ( Fifu_Options_Utils::is_off( 'fifu_pcontent_remove' ) ) {
            return $content;
        }

        $post_types_string = (string) get_option( 'fifu_pcontent_types' );
        $post_types_array  = array_filter(
            array_map( 'trim', explode( ',', $post_types_string ) )
        );

        if ( $post_types_string && ! is_singular( $post_types_array ) ) {
            return $content;
        }

        global $post;
        if ( ! isset( $post->ID ) ) {
            return $content;
        }

        $attachment_id  = (int) get_post_thumbnail_id( $post->ID );
        $attachment_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';

        if ( ! $attachment_url ) {
            return $content;
        }

        $pattern = '/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>/i';

        if ( ! preg_match_all( $pattern, $content, $matches ) ) {
            return $content;
        }

        foreach ( $matches[0] as $index => $image_html ) {
            $image_url = $matches[2][ $index ] ?? '';

            if ( self::urls_match( $image_url, $attachment_url ) ) {
                $position = strpos( $content, $image_html );

                if ( false !== $position ) {
                    return substr_replace(
                        $content,
                        '',
                        $position,
                        strlen( $image_html )
                    );
                }
            }
        }

        return $content;
    }

    /**
     * Compares URLs after trimming and HTML entity decoding.
     *
     * @param string|null $left Left URL.
     * @param string|null $right Right URL.
     * @return bool
     */
    private static function urls_match( ?string $left, ?string $right ): bool {
        $left  = trim( html_entity_decode( (string) $left, ENT_QUOTES | ENT_HTML5 ) );
        $right = trim( html_entity_decode( (string) $right, ENT_QUOTES | ENT_HTML5 ) );

        return '' !== $left && '' !== $right && $left === $right;
    }
}
