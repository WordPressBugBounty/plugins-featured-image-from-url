<?php

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/fifu-meta-functions.php';

/**
 * Post and term meta helpers for FIFU.
 *
 * @package Fifu_Free
 */
class Fifu_Post_Meta_Utils {

    public static function has_local_featured_image( $post_id ) {
        $att_id = get_post_thumbnail_id( $post_id );
        if ( ! $att_id ) {
            return false;
        }

        $att_post = get_post( $att_id );
        if ( ! $att_post ) {
            return false;
        }

        if ( function_exists( 'fifu_is_fifu_attachment_author' ) && fifu_is_fifu_attachment_author( $att_post->post_author ) ) {
            return false;
        }

        return ! self::is_remote_image_from_post( $att_id, $att_post );
    }

    public static function is_remote_image( $att_id ) {
        $att_id = (int) $att_id;
        if ( $att_id <= 0 ) {
            return false;
        }

        $att_post = get_post( $att_id );
        if ( ! $att_post ) {
            return false;
        }

        return self::is_remote_image_from_post( $att_id, $att_post );
    }

    private static function is_remote_image_from_post( int $att_id, $att_post ): bool {
        if ( function_exists( 'fifu_is_fifu_attachment' ) && fifu_is_fifu_attachment( $att_id ) ) {
            return true;
        }

        $attached_file = trim( (string) get_post_meta( $att_id, '_wp_attached_file', true ) );
        if ( $attached_file === '' ) {
            return false;
        }

        return preg_match( '~^(https?://|//)~i', $attached_file ) === 1;
    }

    public static function get_tags( $post_id ) {
        $tags = get_the_tags( $post_id );
        if ( ! $tags ) {
            return null;
        }

        $names = null;
        foreach ( $tags as $tag ) {
            $names .= $tag->name . ' ';
        }
        return $names ? rtrim( $names ) : null;
    }

    public static function get_term_thumbnail_id( $term_id ) {
        $value = get_term_meta( $term_id, 'thumbnail_id', true );
        if ( $value === '' || $value === null ) {
            return null;
        }

        return (int) $value;
    }

    public static function get_parent_slug( $att_id ) {
        $att = get_post( $att_id );
        if ( $att && $att->post_parent ) {
            $parent_post = get_post( $att->post_parent );
            if ( $parent_post ) {
                return $parent_post->post_name;
            }
        }
        return '';
    }

    /**
     * Checks if a post has no local internal featured image.
     *
     * @param int $post_id Post ID to inspect.
     * @return bool
     */
    public static function has_no_internal_image( int $post_id ): bool {
        $thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );
        $default_attachment = get_option( 'fifu_default_attach_id', '' );

        if (
            $thumbnail_id === null
            || $thumbnail_id === false
            || $thumbnail_id === ''
            || $thumbnail_id === 0
            || $thumbnail_id === '0'
            || ! is_numeric( $thumbnail_id )
        ) {
            return true;
        }

        $thumbnail_id = (int) $thumbnail_id;
        if ( $thumbnail_id <= 0 ) {
            return true;
        }

        if ( $default_attachment === null || $default_attachment === false || $default_attachment === '' || ! is_numeric( $default_attachment ) ) {
            return false;
        }

        $default_attachment = (int) $default_attachment;
        if ( $default_attachment <= 0 ) {
            return false;
        }

        return $thumbnail_id === $default_attachment;
    }

    private static function get_fifu_author_id(): int {
        if ( function_exists( 'fifu_resolve_author' ) ) {
            return (int) fifu_resolve_author();
        }

        return fifu_get_author();
    }
}
