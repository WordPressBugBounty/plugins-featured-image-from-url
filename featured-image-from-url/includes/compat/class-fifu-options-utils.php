<?php

defined( 'ABSPATH' ) || exit;

/**
 * Plugin option helpers for FIFU.
 *
 * @package Fifu_Free
 */
class Fifu_Options_Utils {

    private const PRIMARY_AUTHOR = 7777777777;
    private const LEGACY_AUTHOR = 77777;

    public static function is_on( $option ) {
        return get_option( $option ) == 'toggleon';
    }

    public static function is_off( $option ) {
        return get_option( $option ) == 'toggleoff';
    }

    public static function set_author() {
        $existing = self::option_author();
        if ( $existing !== null ) {
            return;
        }

        update_option( 'fifu_author', self::resolve_author_from_posts(), 'no' );
    }

    public static function get_author() {
        $option = self::option_author();
        if ( $option !== null ) {
            return $option;
        }

        return self::resolve_author_from_posts();
    }

    private static function option_author(): ?int {
        $option = get_option( 'fifu_author', null );
        if ( $option === null || $option === false ) {
            return null;
        }

        $value = trim( (string) $option );
        if ( $value === '' ) {
            return null;
        }

        return (int) $option;
    }

    private static function author_exists_in_posts( int $author ): bool {
        global $wpdb;

        if (
            ! isset( $wpdb ) ||
            ! is_object( $wpdb ) ||
            ! is_callable( array( $wpdb, 'get_var' ) ) ||
            empty( $wpdb->posts )
        ) {
            return false;
        }

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM {$wpdb->posts} WHERE post_author = %d LIMIT 1",
                $author
            )
        );
    }

    private static function resolve_author_from_posts(): int {
        if ( self::author_exists_in_posts( self::LEGACY_AUTHOR ) ) {
            return self::LEGACY_AUTHOR;
        }

        if ( self::author_exists_in_posts( self::PRIMARY_AUTHOR ) ) {
            return self::PRIMARY_AUTHOR;
        }

        return self::PRIMARY_AUTHOR;
    }

    public static function maybe_upgrade_author_after_metadata_cleanup(): void {
        $existing = self::option_author();

        if ( $existing !== self::LEGACY_AUTHOR ) {
            return;
        }

        if ( self::author_exists_in_posts( self::LEGACY_AUTHOR ) ) {
            return;
        }

        update_option( 'fifu_author', self::PRIMARY_AUTHOR, 'no' );
    }

    /**
     * Returns the configured default featured image URL.
     *
     * @return string|null
     */
    public static function get_default_image_url(): ?string {
        $attach_id = get_option( 'fifu_default_attach_id' );
        if ( ! $attach_id ) {
            return null;
        }

        return wp_get_attachment_url( $attach_id );
    }
}
