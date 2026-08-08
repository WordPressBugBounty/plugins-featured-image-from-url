<?php

defined( 'ABSPATH' ) || exit;

/**
 * Static-only service class for tracking image size usage and resolving size details.
 *
 * Future migration of fifu_detect_image_size_usage and related helpers will happen here.
 *
 * @package Fifu_Free
 */
class Fifu_Image_Size_Usage_Tracker {

    /**
     * Callback for the image_downsize filter to record page context and size usage.
     *
     * @param mixed $image
     * @param int|string $attachment_id
     * @param mixed $size
     * @return mixed
     */
    public static function track_usage( $image, $attachment_id, $size ) {
        $attachment_id = absint( $attachment_id );

        if ( ! $attachment_id ) {
            return $image;
        }
        $page_type = 'unknown';

        if ( is_front_page() ) {
            $page_type = 'front page';
        } elseif ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
            if ( function_exists( 'is_shop' ) && is_shop() ) {
                $page_type = 'shop';
            } elseif ( function_exists( 'is_product' ) && is_product() ) {
                $page_type = 'product';
            } elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
                $page_type = 'product category';
            } elseif ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
                $page_type = 'product tag';
            } elseif ( function_exists( 'is_cart' ) && is_cart() ) {
                $page_type = 'cart';
            } elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
                $page_type = 'checkout';
            } elseif ( function_exists( 'is_account_page' ) && is_account_page() ) {
                $page_type = 'account';
            } elseif ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
                $page_type = 'order received';
            }
        }

        if ( 'unknown' === $page_type ) {
            if ( is_home() ) {
                $page_type = 'blog home';
            } elseif ( is_category() ) {
                $page_type = 'category';
            } elseif ( is_tag() ) {
                $page_type = 'tag';
            } elseif ( is_tax() ) {
                $page_type = 'taxonomy';
            } elseif ( is_single() ) {
                $page_type = 'single post';
            } elseif ( is_page() ) {
                $page_type = 'page';
            } elseif ( is_archive() ) {
                $page_type = 'archive';
            } elseif ( is_author() ) {
                $page_type = 'author';
            } elseif ( is_search() ) {
                $page_type = 'search';
            } elseif ( is_404() ) {
                $page_type = '404';
            } elseif ( is_attachment() ) {
                $page_type = 'attachment';
            }
        }

        $option_key = self::get_detected_size_option_key( $size );

        $default_data = [
            'w'     => 0,
            'h'     => 0,
            'c'     => false,
            'pages' => [],
        ];

        if ( is_string( $size ) ) {
            $registered_sizes = wp_get_registered_image_subsizes();
            if ( array_key_exists( $size, $registered_sizes ) ) {
                $default_data['w'] = $registered_sizes[ $size ]['width'] ?? 0;
                $default_data['h'] = $registered_sizes[ $size ]['height'] ?? 0;
                $default_data['c'] = $registered_sizes[ $size ]['crop'] ?? false;
            }
        } elseif ( is_array( $size ) && count( $size ) >= 2 ) {
            $default_data['w'] = (int) ( $size[0] ?? 0 );
            $default_data['h'] = (int) ( $size[1] ?? 0 );
            $default_data['c'] = (bool) ( $size[2] ?? false );
        } else {
            return $image;
        }

        $current = get_option( $option_key, $default_data );
        if ( ! is_array( $current ) ) {
            $current = $default_data;
        }

        if ( ! isset( $current['pages'] ) || ! is_array( $current['pages'] ) ) {
            $current['pages'] = [];
        }

        $next = $current;
        $next['w'] = $current['w'] ?? $default_data['w'];
        $next['h'] = $current['h'] ?? $default_data['h'];
        $next['c'] = $current['c'] ?? $default_data['c'];

        if ( ! in_array( $page_type, $next['pages'], true ) ) {
            $next['pages'][] = $page_type;
        }

        if ( $next !== $current ) {
            update_option( $option_key, $next );
        }

        return $image;
    }

    /**
     * Resolve the option key for a detected image size.
     *
     * @param mixed $size
     * @return string
     */
    public static function get_detected_size_option_key( $size ): string {
        if ( is_string( $size ) ) {
            return empty( $size ) ? 'fifu_detected_size_empty' : "fifu_detected_size_{$size}";
        }

        if ( is_array( $size ) && count( $size ) >= 2 ) {
            $w = (int) ( $size[0] ?? 0 );
            $h = (int) ( $size[1] ?? 0 );
            $c = (bool) ( $size[2] ?? false );
            return "fifu_detected_size_{$w}x{$h}x" . ( $c ? '1' : '0' );
        }

        return 'fifu_detected_size_unknown';
    }

    /**
     * Resolve the option key for a defined image size.
     *
     * @param mixed $size
     * @return string
     */
    public static function get_defined_size_option_key( $size ): string {
        if ( is_string( $size ) && strpos( $size, 'fifu_detected_size_' ) === 0 ) {
            return str_replace( 'fifu_detected_size_', 'fifu_defined_size_', $size );
        }

        $detected_key = self::get_detected_size_option_key( $size );
        return str_replace( 'fifu_detected_size_', 'fifu_defined_size_', $detected_key );
    }

    /**
     * Map an option key back to the human-readable size label.
     *
     * @param string $option_key
     * @return string
     */
    public static function get_size_name_from_option_key( string $option_key ): string {
        if ( strpos( $option_key, 'fifu_detected_size_' ) === 0 ) {
            return substr( $option_key, strlen( 'fifu_detected_size_' ) );
        }

        if ( strpos( $option_key, 'fifu_defined_size_' ) === 0 ) {
            return substr( $option_key, strlen( 'fifu_defined_size_' ) );
        }

        return $option_key;
    }

    /**
     * Retrieve normalized width, height and crop flags for the provided size specifier.
     *
     * @param mixed $size
     * @return array<string,int>
     */
    public static function get_image_size_details( $size ): array {
        $defined = self::get_defined_size_option_key( $size );
        if ( $defined ) {
            $defined_data = get_option( $defined );
            if ( $defined_data ) {
                return [
                    'width'  => intval( $defined_data['w'] ?? 0 ),
                    'height' => intval( $defined_data['h'] ?? 0 ),
                    'crop'   => intval( $defined_data['c'] ?? 0 ),
                ];
            }
        }

        $default_width  = 0;
        $default_height = 0;
        $default_crop   = 0;

        $image_sizes       = get_intermediate_image_sizes();
        $registered_sizes  = wp_get_registered_image_subsizes();

        $width  = $default_width;
        $height = $default_height;
        $crop   = $default_crop;

        if ( is_array( $size ) ) {
            $width  = isset( $size[0] ) && is_numeric( $size[0] ) ? intval( $size[0] ) : $default_width;
            $height = isset( $size[1] ) && is_numeric( $size[1] ) ? intval( $size[1] ) : $default_height;
            $crop   = isset( $size[2] ) ? ( boolval( $size[2] ) ? 1 : 0 ) : $default_crop;
        } elseif ( is_string( $size ) && in_array( $size, $image_sizes, true ) ) {
            if ( isset( $registered_sizes[ $size ] ) ) {
                $width  = intval( $registered_sizes[ $size ]['width'] ?? 0 );
                $height = intval( $registered_sizes[ $size ]['height'] ?? 0 );
                $crop   = intval( boolval( $registered_sizes[ $size ]['crop'] ?? false ) );
            } else {
                $width  = intval( get_option( "{$size}_size_w", $default_width ) );
                $height = intval( get_option( "{$size}_size_h", $default_height ) );
                $crop   = intval( get_option( "{$size}_size_crop", $default_crop ) );
            }
        }

        return [
            'width'  => $width,
            'height' => $height,
            'crop'   => $crop,
        ];
    }
}
