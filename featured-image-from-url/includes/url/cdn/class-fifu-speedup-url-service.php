<?php

defined( 'ABSPATH' ) || exit;

/**
 * Static-only service class for handling FIFU Speedup URLs and signed CDN URLs.
 *
 * Future migration of FIFU_SPEEDUP_SIZES and speedup helpers will happen here.
 *
 * @package Fifu_Free
 */
class Fifu_Speedup_Url_Service {

    /**
     * Builds a storage identifier based on a hex ID and dimensions.
     *
     * @param string $hexId
     * @param int    $width
     * @param int    $height
     * @return string
     */
    public static function build_storage_id(string $hexId, int $width, int $height): string {
        return $hexId . '-' . $width . '-' . $height;
    }

    /**
     * Allowed breakpoints for Speedup CDN resizing.
     *
     * @var int[]
     */
    private const ALLOWED_SIZES = [ 64, 128, 192, 256, 384, 512, 640, 768, 896, 1024, 1280, 1600, 1920 ];

    /**
     * Determine whether the URL belongs to the FIFU Speedup CDN.
     *
     * @param string|null $url
     * @return bool
     */
    public static function is_speedup_url( ?string $url ): bool {
        if ( empty( $url ) ) {
            return false;
        }

        return strpos( $url, 'cdn.fifu.app' ) !== false;
    }

    /**
     * Build a responsive srcset attribute using the allowed breakpoints.
     *
     * @param string $url
     * @return string
     */
    public static function get_srcset( string $url ): string {
        $sizes     = self::parse_sizes( $url );
        $width     = $sizes[0] ?? 0;
        $height    = $sizes[1] ?? 0;
        $is_video  = $sizes[2] ?? false;
        $clean_url = $sizes[3] ?? $url;

        $set_parts = array();
        foreach ( self::ALLOWED_SIZES as $size ) {
            $set_parts[] = self::resize_for_breakpoint( $size, $clean_url, $width, $height, $is_video ) . ' ' . $size . 'w';
            if ( $width <= $size ) {
                break;
            }
        }

        return implode( ', ', $set_parts );
    }

    /**
     * Resize the Speedup URL for a specific breakpoint, preserving aspect ratio.
     *
     * @param int $target_width
     * @param string $url
     * @param int $original_width
     * @param int $original_height
     * @param bool $is_video
     * @return string
     */
    public static function resize_for_breakpoint( int $target_width, string $url, int $original_width, int $original_height, bool $is_video ): string {
        $new_height = $original_width ? intval( $target_width * $original_height / $original_width ) : $original_height;
        return self::get_signed_url( $url, $target_width, $new_height, null, null, $is_video );
    }

    /**
     * Determine whether a Speedup URL points to a video thumbnail.
     *
     * @param string $url
     * @return bool
     */
    public static function has_video_thumb( string $url ): bool {
        return strpos( $url, 'video-thumb' ) !== false;
    }

    /**
     * Build a signed URL for the Speedup/Cloud CDN proxy layer.
     *
     * @param string $url
     * @param int $width
     * @param int $height
     * @param string|null $bucket_id
     * @param string|null $storage_id
     * @param bool $is_video
     * @return string
     */
    public static function get_signed_url( string $url, int $width, int $height, ?string $bucket_id, ?string $storage_id, bool $is_video ): string {
        if ( ! self::is_speedup_url( $url ) ) {
            return $url;
        }

        list( $width, $height ) = self::normalize_size( $width, $height );

        $resize = 'fill';

        if ( wp_is_mobile() ) {
            $square_mobile = get_option( 'fifu_square_mobile' );
            if ( $square_mobile ) {
                $height = $width;
                $resize = $square_mobile === 'crop' ? 'fill' : 'fit';
            }
        } else {
            $square_desktop = get_option( 'fifu_square_desktop' );
            if ( $square_desktop ) {
                $height = $width;
                $resize = $square_desktop === 'crop' ? 'fill' : 'fit';
            }
        }

        if ( $url ) {
            $url = explode( '?', $url )[0] ?? $url;
        }

        $proxy_auth = get_option( 'fifu_proxy_auth' );
        if ( ! $proxy_auth ) {
            return $url;
        }

        $bucket_id  = $bucket_id ?? '';
        $storage_id = $storage_id ?? '';

        if ( $url ) {
            $aux = explode( '/', $url );
            $bucket_id  = $aux[3] ?? $bucket_id;
            $storage_id = $aux[4] ?? $storage_id;
        }

        $aux = explode( '-', $storage_id );
        if ( count( $aux ) < 7 ) {
            return $url;
        }

        $original_width  = (int) ( $aux[1] ?? 0 );
        $original_height = (int) ( $aux[2] ?? 0 );
        $center_x       = (int) ( $aux[3] ?? 0 );
        $center_y       = (int) ( $aux[4] ?? 0 );
        $top_head       = (int) ( $aux[5] ?? 0 );
        $bottom         = (int) ( $aux[6] ?? 0 );

        $watermark = $is_video ? '/wm:0.85:ce:0:0:0.25' : '';

        $x_fp = $original_width ? number_format( $center_x / $original_width, 2 ) : '0.00';
        $y_fp = $original_height ? number_format( $center_y / $original_height, 2 ) : '0.00';

        if ( $top_head > 0 && Fifu_Generic_Utils::is_landscape( $width, $height ) ) {
            $ratio = $width / max( 1, $height );
            $w     = Fifu_Generic_Utils::is_portrait( $original_width, $original_height ) ? $original_width : $original_height;
            $h     = $w / $ratio;

            if ( $bottom > 0 && ( $bottom - $top_head ) <= $h ) {
                $center_y = ( $top_head + $bottom ) / 2;
                $y_fp     = number_format( $center_y / $original_height, 2 );
            } else {
                if ( $center_y - ( $h / 2 ) < $top_head ) {
                    $diff = $top_head - ( $center_y - ( $h / 2 ) );
                    if ( $center_y + ( $h / 2 ) < $original_height ) {
                        $padding_bottom = $original_height - ( $center_y + ( $h / 2 ) );
                        $center_y       += min( $diff, $padding_bottom );
                        $y_fp           = number_format( $center_y / $original_height, 2 );
                    }
                }
            }
        }

        $key  = pack( 'H*', $proxy_auth[0] ?? '' );
        $salt = pack( 'H*', $proxy_auth[1] ?? '' );

        $path = "/rs:{$resize}:{$width}:{$height}:1:1/g:fp:{$x_fp}:{$y_fp}{$watermark}/plain/{$bucket_id}/{$storage_id}@webp";
        $signature = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $salt . $path, $key, true ) ), '+/', '-_' ), '=' );

        return "https://cloud.fifu.app/{$signature}{$path}";
    }

    /**
     * Extract normalized dimensions and video flag from the provided Speedup URL.
     *
     * @param string $url
     * @return array<string,mixed>
     */
    public static function parse_sizes( string $url ): array {
        $aux        = explode( '?', $url );
        $clean_url  = $aux[0] ?? $url;
        $parameters = $aux[1] ?? '';
        parse_str( $parameters, $parameters );

        $parts  = explode( '-', $clean_url );
        $width  = (int) ( $parts[1] ?? 0 );
        $height = (int) ( $parts[2] ?? 0 );

        if ( isset( $parameters['resize'] ) ) {
            $resize_parts = explode( ',', $parameters['resize'] );
            $width        = (int) ( $resize_parts[0] ?? 0 );
            $height       = (int) ( $resize_parts[1] ?? 0 );
        }

        $is_video = isset( $parameters['video-thumb'] );

        return array( $width, $height, $is_video, $clean_url );
    }

    /**
     * Adjust width/height to the closest allowed breakpoint while maintaining aspect ratio.
     *
     * @param int $width
     * @param int $height
     * @return array<string,int>
     */
    private static function normalize_size( int $width, int $height ): array {
        $original_width = $width;

        if ( ! in_array( $width, self::ALLOWED_SIZES, true ) ) {
            foreach ( self::ALLOWED_SIZES as $size ) {
                if ( $size >= $width ) {
                    $width = $size;
                    break;
                }
            }

            if ( $width > 1920 ) {
                $width = 1920;
            }
        }

        if ( $height ) {
            $aspect_ratio = $height / max( 1, $original_width );
            $new_height   = (int) round( $width * $aspect_ratio );
        } else {
            $new_height = $height;
        }

        return array( $width, $new_height );
    }

    /**
     * Recreate the legacy fifu_speedup_get_url behavior using new helpers.
     *
     * @param array $image
     * @param mixed $size
     * @param int $att_id
     * @return array
     */
    public static function get_speedup_image( array $image, $size, int $att_id ): array {
        $image_url = $image[0] ?? null;
        $has_video = self::has_video_thumb( $image_url ?? '' );

        $aux = explode( '/', $image_url );
        if ( isset( $aux[4] ) ) {
            $aux = explode( '-', $aux[4] );
            $original_width  = (int) ( $aux[1] ?? 0 );
            $original_height = (int) ( $aux[2] ?? 0 );
        } else {
            $original_width  = 0;
            $original_height = 0;
        }

        if ( ( $image[1] ?? 0 ) <= 1 ) {
            $image[1] = $original_width;
            $image[2] = $original_height;
        }

        $result = Fifu_Attachment_Image_Src_Filter::apply_registered_size( $image, $size );
        $image  = $result['image'] ?? $image;
        $crop   = $result['crop'] ?? false;

        if ( ! isset( $image[1] ) || ! is_numeric( $image[1] ) || $image[1] >= 9999 ) {
            $image[1] = 0;
        }

        if ( ! isset( $image[2] ) || ! is_numeric( $image[2] ) || $image[2] >= 9999 ) {
            $image[2] = 0;
        }

        if ( ! $image[2] ) {
            $image[2] = $original_width ? (int) ( $image[1] * $original_height / $original_width ) : 0;
        }

        if ( $crop || $image[2] ) {
            if ( $has_video && ( $image[1] == 320 ) && ( $image[2] == 180 ) ) {
                $image[0] = $image[0] . '&resize=1280,720';
                $image[1] = 1280;
                $image[2] = 720;
            } else {
                $image[0] .= $has_video ? '&' : '?';
                $image[0] .= "resize={$image[1]},{$image[2]}";
            }
        } else {
            $image[0] .= "?theme-size={$image[1]},{$image[2]}";
        }

        $image[0] = Fifu_Local_Media_Renderer::enrich_attachment_url( $image[0], $att_id, $size );
        return $image;
    }
}
