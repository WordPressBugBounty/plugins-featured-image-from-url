<?php

defined( 'ABSPATH' ) || exit;

/**
 * Static-only service class for handling CDN-aware image downsize logic.
 *
 * @package Fifu_Free
 */
class Fifu_Image_Cdn_Resize_Service {

    /**
     * Serve as the image_downsize filter entry point for CDN resizing.
     *
     * @param mixed $out
     * @param int|string $attachment_id
     * @param mixed $size
     * @return mixed
     */
    public static function filter_image_downsize( $out, $attachment_id, $size ) {
        global $FIFU_SESSION;

        $attachment_id = absint( $attachment_id );

        if ( ! $attachment_id || ! Fifu_Post_Meta_Utils::is_remote_image( $attachment_id ) ) {
            return $out;
        }

        if ( Fifu_Options_Utils::is_off( 'fifu_photon' ) ) {
            return $out;
        }

        $original_image_url = '';
        if ( function_exists( 'fifu_get_raw_remote_attached_file' ) ) {
            $original_image_url = fifu_get_raw_remote_attached_file( $attachment_id );
        }
        if ( $original_image_url === '' ) {
            $original_image_url = trim( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );
        }
        $original_image_url = trim( (string) $original_image_url );

        if ( $original_image_url === '' || preg_match( '~^(https?://|//)~i', $original_image_url ) !== 1 ) {
            return $out;
        }

        if ( strpos( $original_image_url, 'https://thumbnails.odycdn.com' ) !== 0 &&
            strpos( $original_image_url, 'https://res.cloudinary.com/glide/' ) !== 0 &&
            Fifu_Jetpack_Cdn_Service::is_blocked_source( $original_image_url ) ) {
            return $out;
        }

        if ( $original_image_url && Fifu_Generic_Utils::ends_with( $original_image_url, '.svg' ) ) {
            return $out;
        }

        if ( Fifu_Speedup_Url_Service::is_speedup_url( $original_image_url ) ) {
            return $out;
        }

        $image_url = $original_image_url;
        $size_details = Fifu_Image_Size_Usage_Tracker::get_image_size_details( $size );
        $width  = $size_details['width'] ?? 0;
        $height = $size_details['height'] ?? 0;
        $crop   = $size_details['crop'] ?? 0;

        if ( 'full' === $size ) {
            $metadata = wp_get_attachment_metadata( $attachment_id );
            if (
                is_array($metadata)
                && ! empty( $metadata['width'] )
                && ! empty( $metadata['height'] )
            ) {
                $original_width  = intval( $metadata['width'] ?? 0 );
                $original_height = intval( $metadata['height'] ?? 0 );
                $aspect_ratio    = $original_width ? $original_height / $original_width : 0;
                $max_dimension   = 1920;

                if ( $original_width > $original_height ) {
                    $new_width  = min( $original_width, $max_dimension );
                    $new_height = intval( $new_width * $aspect_ratio );
                } else {
                    $new_height = min( $original_height, $max_dimension );
                    $new_width  = $aspect_ratio ? intval( $new_height / $aspect_ratio ) : $new_height;
                }

                $new_url = self::resize_with_cdn( $image_url, $new_width, $new_height, $crop, $attachment_id, $size );
                self::update_session_mapping( $new_url, $original_image_url );
                return array( $new_url, $new_width, $new_height, true );
            }

            if ( is_front_page() || is_home() ) {
                $session_map = $FIFU_SESSION['cdn-new-old'] ?? null;
                if ( ! empty( $session_map ) ) {
                    return $out;
                }
            }

            return $out;
        }

        if ( ! $width && ! $height ) {
            return $out;
        }

        $new_url = self::resize_with_cdn( $image_url, $width, $height, $crop, $attachment_id, $size );
        self::update_session_mapping( $new_url, $original_image_url );
        return array( $new_url, $width, $height, true );
    }

    public static function resize_with_cdn( string $url, int $width, int $height, $crop, int $attachment_id, $size ): string {
        if ( ! $width && ! $height ) {
            $size_details = Fifu_Image_Size_Usage_Tracker::get_image_size_details( $size );
            $width  = $size_details['width'] ?? 0;
            $height = $size_details['height'] ?? 0;
            $crop   = $size_details['crop'] ?? 0;
        }

        if ( ! $width && ! $height ) {
            return Fifu_Jetpack_Cdn_Service::build_photon_url( $url, null, $attachment_id );
        }

        $w = $width;
        $h = $height;
        $c = is_null( $crop ) ? 0 : (int) $crop;

        return Fifu_Jetpack_Cdn_Service::build_photon_url( $url, "?w={$w}&h={$h}&c={$c}", $attachment_id );
    }

    /**
     * Store the CDN url mapping for later reference in the session.
     *
     * @param string $new_url
     * @param string $original_url
     * @return void
     */
    private static function update_session_mapping( string $new_url, string $original_url ): void {
        global $FIFU_SESSION;
        $FIFU_SESSION['cdn-new-old'] = $FIFU_SESSION['cdn-new-old'] ?? array();
        $FIFU_SESSION['cdn-new-old'][ $new_url ] = $original_url;
    }
}
