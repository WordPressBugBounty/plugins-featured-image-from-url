<?php

defined( 'ABSPATH' ) || exit;

/**
 * Image URL helper utilities for FIFU.
 *
 * @package Fifu_Free
 */
class Fifu_Image_Url_Utils {

    public static function is_remote_image_url( $url ) {
        if ( empty( $url ) ) {
            return false;
        }

        $has_protocol = strpos( $url, 'http' ) === 0 || strpos( $url, '//' ) === 0;
        if ( ! $has_protocol ) {
            return false;
        }

        if ( strpos( $url, 'fifu' ) !== false ) {
            return true;
        }

        $site_host  = parse_url( get_site_url(), PHP_URL_HOST );
        $image_host = parse_url( $url, PHP_URL_HOST );

        if ( $image_host && $site_host && $image_host !== $site_host ) {
            return true;
        }

        return false;
    }

    public static function remove_query_strings( $url ) {
        return preg_replace( '/\?.*/', '', $url );
    }

    public static function get_placeholder( $width, $height ) {
        $text = '...';
        return "https://images.placeholders.dev/?width={$width}&height={$height}&text={$text}";
    }

    public static function is_base64( $url ) {
        return strpos( $url, 'data:' ) === 0;
    }

    public static function to_base64( $url ) {
        return 'data:image/jpg;base64,' . base64_encode( file_get_contents( $url ) );
    }

    public static function is_valid_image_url( $url ) {
        if ( empty( $url ) ) {
            return false;
        }

        if ( function_exists( 'fifu_convert' ) ) {
            $url = fifu_convert( $url );
        }

        if ( function_exists( 'apply_filters' ) ) {
            $prevalidated = apply_filters( 'fifu_pre_is_valid_image_url', null, $url );

            if ( $prevalidated !== null ) {
                return (bool) $prevalidated;
            }
        }

        $curl = curl_init();
        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL        => str_replace( ' ', '%20', $url ),
                CURLOPT_HEADER     => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY     => true,
            ]
        );

        $header = explode( "\n", curl_exec( $curl ) ?? '' );
        curl_close( $curl );

        $status_line = $header[0] ?? '';

        return strpos( $status_line, ' 200' ) !== false || strpos( $status_line, ' 302' ) !== false;
    }

    /**
     * Returns the full attachment URL when available.
     *
     * WordPress can return false for missing or invalid attachments, so this
     * helper normalizes those cases to null for callers that expect "no URL".
     *
     * @param int|string|null $att_id
     * @return string|null
     */
    public static function get_full_image_url( $att_id ) {
        $att_id = (int) $att_id;
        if ( $att_id <= 0 ) {
            return null;
        }

        $remote_url = self::get_remote_attached_file_url( $att_id );
        if ( $remote_url !== null ) {
            return $remote_url;
        }

        $url = wp_get_attachment_url( $att_id );
        if ( ! is_string( $url ) || $url === '' ) {
            return null;
        }

        $normalized = self::normalize_wrapped_remote_upload_url( $url );
        if ( $normalized !== $url ) {
            return $normalized;
        }

        return $url;
    }

    private static function get_remote_attached_file_url( int $att_id ): ?string {
        $raw_url = trim( (string) get_post_meta( $att_id, '_wp_attached_file', true ) );
        if ( $raw_url === '' ) {
            return null;
        }

        $normalized = self::normalize_wrapped_remote_upload_url( $raw_url );
        if ( $normalized !== '' ) {
            return $normalized;
        }

        if ( preg_match( '~^(https?://|//)~i', $raw_url ) === 1 ) {
            return $raw_url;
        }

        return null;
    }

    private static function normalize_wrapped_remote_upload_url( string $url ): string {
        $url = trim( $url );
        if ( $url === '' ) {
            return '';
        }

        if ( preg_match( '~uploads/(https?://.+)$~i', $url, $matches ) === 1 ) {
            return $matches[1];
        }

        if ( preg_match( '~uploads/(//.+)$~i', $url, $matches ) === 1 ) {
            return $matches[1];
        }

        if ( preg_match( '~^(https?://|//)~i', $url ) === 1 ) {
            return $url;
        }

        return '';
    }

    public static function base64( $url ) {
        if ( $url === null ) {
            return '';
        }
        return rtrim( strtr( base64_encode( $url ), '+/', '-_' ), '=' );
    }

    public static function get_host( $url ) {
        $parsed = wp_parse_url( $url );
        return $parsed['host'] ?? null;
    }

    public static function get_home_url_no_scheme() {
        $parts = explode( '//', get_home_url() );
        return $parts[1] ?? '';
    }

    public static function get_domain() {
        $url = get_home_url();

        $aux = explode( '//', $url );
        $part = $aux[1] ?? null;
        if ( ! $part ) {
            return null;
        }

        $aux = explode( '/', $part );
        return $aux[0] ?? null;
    }

    /**
     * Check if the given URL belongs to a known external CDN provider.
     *
     * This will host the future migration of fifu_is_cdn_url logic.
     *
     * @param string $url
     * @return bool
     */
    public static function is_cdn_url( string $url ): bool {
        if ( strpos( $url, 'https://thumbnails.odycdn.com' ) === 0 ) {
            return true;
        }

        if ( strpos( $url, 'https://res.cloudinary.com/glide/' ) === 0 ) {
            return true;
        }

        for ( $i = 0; $i <= 3; $i++ ) {
            if ( strpos( $url, "https://i{$i}.wp.com" ) === 0 ) {
                return true;
            }
        }

        return false;
    }
}
