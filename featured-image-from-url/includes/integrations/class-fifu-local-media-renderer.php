<?php

defined( 'ABSPATH' ) || exit;

/**
 * Handles the FIFU local media session registration and output buffering.
 */
class Fifu_Local_Media_Renderer {

    /**
     * Registers a local image so the renderer can enrich it later.
     *
     * @param string $url
     * @param int    $att_id
     * @return void
     */
    public static function register_image( string $url, int $att_id ): void {
        global $FIFU_SESSION;

        $url = self::get_main_url( $url );
        if ( ! $url ) {
            return;
        }

        if ( isset( $FIFU_SESSION[ $url ] ) ) {
            return;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }

        $post_thumbnail_id = get_post_thumbnail_id( $post_id );

        $is_category = false;
        if ( ! $post_thumbnail_id ) {
            $post_thumbnail_id = get_term_meta( $post_id, 'thumbnail_id', true );
            if ( $post_thumbnail_id ) {
                $is_category = true;
            }
        }

        $featured = $post_thumbnail_id == $att_id ? 1 : 0;

        if ( ! $featured ) {
            return;
        }

        $parameters = array();
        $parameters['att_id'] = $att_id;
        $parameters['post_id'] = $post_id;
        $parameters['featured'] = $featured;
        $parameters['category'] = $is_category;
        $parameters['local'] = true;

        $FIFU_SESSION[ $url ] = $parameters;

    }

    /**
     * Enriches a FIFU attachment URL with session parameters and metadata.
     *
     * This will eventually replace fifu_add_url_parameters and keep all session/$FIFU_SESSION
     * enrichment in one place before the legacy logic is migrated away.
     *
     * @param string     $url
     * @param int        $attachment_id
     * @param mixed|null $size
     * @return string
     */
    public static function enrich_attachment_url( string $url, int $attachment_id, $size = null ): string {
        global $FIFU_SESSION;

        if ( isset( $FIFU_SESSION[ $url ] ) ) {
            return $url;
        }

        $post = get_post( $attachment_id );
        $post_id = $post ? $post->post_parent : null;

        if ( ! $post_id ) {
            return $url;
        }

        if (
            function_exists( 'get_current_screen' )
            && isset( get_current_screen()->parent_file )
            && get_current_screen()->parent_file === 'edit.php?post_type=product'
            && get_current_screen()->id === 'edit-product_cat'
        ) {
            return Fifu_Cdn_Thumbnail_Resolver::get_optimized_thumbnail_url( $url, $attachment_id );
        }

        $post_thumbnail_id = get_post_thumbnail_id( $post_id );

        $is_category = false;
        if ( ! $post_thumbnail_id ) {
            $post_thumbnail_id = get_term_meta( $post_id, 'thumbnail_id', true );
            if ( $post_thumbnail_id ) {
                $is_category = true;
            }
        }

        $featured = $post_thumbnail_id == $attachment_id ? 1 : 0;

        if ( ! $featured ) {
            return $url;
        }

        $parameters = array();
        $parameters['att_id'] = $attachment_id;
        $parameters['post_id'] = $post_id;
        $parameters['featured'] = $featured;
        $parameters['category'] = $is_category;
        $parameters['local'] = false;

        if ( $size ) {
            $size_details = Fifu_Image_Size_Usage_Tracker::get_image_size_details( $size );
            if ( ( $size_details['width'] ?? false ) && ( $size_details['height'] ?? false ) ) {
                $parameters['theme-width'] = $size_details['width'];
                $parameters['theme-height'] = $size_details['height'];
                $parameters['theme-crop'] = $size_details['crop'] ?? false;
            }
        }

        $FIFU_SESSION[ $url ] = $parameters;

        if ( Fifu_Speedup_Url_Service::is_speedup_url( $url ) ) {
            $FIFU_SESSION['fifu-cloud'][ $url ] = Fifu_Speedup_Url_Service::get_srcset( $url );
            wp_enqueue_script( 'fifu-cloud', plugins_url( '/includes/html/js/cloud.js', FIFU_PLUGIN_FILE ), array( 'jquery' ), Fifu_Plugin_Info::get_enqueue_version() );
            $json = wp_json_encode( [ 'srcsets' => $FIFU_SESSION['fifu-cloud'] ] );
            wp_add_inline_script( 'fifu-cloud', "var fifuCloudVars = {$json};", 'before' );
        }

        return $url;
    }

    /**
     * Registers a single post thumbnail with the FIFU session.
     *
     * This is the future replacement for fifu_add_parameters_single_post.
     *
     * @param int $post_id
     * @return void
     */
    public static function register_post_thumbnail( int $post_id ): void {
        $attachment_id = get_post_thumbnail_id( $post_id );
        if ( ! $attachment_id ) {
            return;
        }

        $url = get_post_meta( $attachment_id, '_wp_attached_file', true );
        if ( $url ) {
            self::enrich_attachment_url( $url, $attachment_id, null );
        }
    }

    /**
     * Populates FIFU session data for FacetWP filtered posts.
     *
     * Mirrors the former facetwp_filtered_post_ids closure.
     *
     * @param mixed $post_ids
     * @param mixed $facetwp
     */
    public static function register_for_facetwp( $post_ids, $facetwp ) {
        if ( ! is_array( $post_ids ) ) {
            return $post_ids;
        }

        foreach ( $post_ids as $post_id ) {
            self::register_post_thumbnail( (int) $post_id );
        }

        return $post_ids;
    }

    /**
     * Registers thumbnails when posts_results is filtered to keep FIFU session data in sync.
     *
     * WordPress and third-party plugins can short-circuit queries before posts are populated.
     * Keep the original value unchanged when posts_results receives null or another non-array value.
     *
     * @param array|null $posts
     * @param mixed $query
     * @return array|null
     */
    public static function register_posts_results( $posts, $query ) {
        if ( ! is_array( $posts ) || empty( $posts ) ) {
            return $posts;
        }

        if ( ! $query instanceof \WP_Query ) {
            return $posts;
        }

        if ( ! is_admin() && $query->is_main_query() && is_paged() ) {
            foreach ( $posts as $post ) {
                if ( isset( $post->ID ) ) {
                    self::register_post_thumbnail( (int) $post->ID );
                }
            }
        }

        return $posts;
    }

    /**
     * Starts output buffering for local media processing.
     */
    public static function start_buffer(): void {
        ob_start( array( self::class, 'filter_buffer' ) );
    }

    /**
     * Filters the buffer in order to enrich local FIFU images and backgrounds.
     *
     * @param string $buffer
     * @return string
     */
    public static function filter_buffer( string $buffer ): string {
        global $FIFU_SESSION;

        if ( empty( $buffer ) ) {
            return $buffer;
        }

        if ( isset( $_REQUEST['ct_builder'] ) || isset( $_REQUEST['bricks'] ) || isset( $_REQUEST['fb-edit'] ) ) {
            return $buffer;
        }

        $srcType = 'src';
        $imgList = array();
        preg_match_all( '/<img[^>]*>/', $buffer, $imgList );

        foreach ( ( $imgList[0] ?? [] ) as $imgItem ) {
            preg_match( '/(' . $srcType . ')([^\\\'"]*[\\\'"]){2}/', $imgItem, $src );
            if ( ! $src ) {
                continue;
            }
            $del = substr( $src[0] ?? '', -1 );
            $url = Fifu_Html_Attribute_Utils::normalize( explode( $del, $src[0] ?? '' )[1] ?? '' );

            $url = self::get_main_url( $url );
            if ( ! $url ) {
                continue;
            }

            if ( isset( $FIFU_SESSION[ $url ] ) ) {
                $data = $FIFU_SESSION[ $url ];
            } else {
                continue;
            }

            if ( strpos( $imgItem, 'fifulocal-replaced' ) !== false ) {
                continue;
            }

            if ( ! ( $data['local'] ?? false ) ) {
                continue;
            }

            $post_id = $data['post_id'] ?? null;
            $att_id = $data['att_id'] ?? null;
            $featured = $data['featured'] ?? null;
            $is_category = $data['category'] ?? false;

            if ( $featured ) {
                $newImgItem = str_replace( '<img ', '<img fifulocal-featured="' . $featured . '" ', $imgItem );

                if ( $is_category ) {
                    $newImgItem = str_replace( '<img ', '<img fifu-category="1" ', $newImgItem );
                }

                if ( get_post_type( $post_id ) == 'product' ) {
                    $newImgItem = str_replace( '<img ', '<img product-id="' . $post_id . '" ', $newImgItem );
                } else {
                    $newImgItem = str_replace( '<img ', '<img post-id="' . $post_id . '" ', $newImgItem );
                }

                $buffer = str_replace( $imgItem, Fifu_Featured_Image_Filter::filter_post_thumbnail_html( $newImgItem, $post_id, null, null, null ), $buffer );
            }
        }

        $imgList = array();
        preg_match_all( '/<[^>]*background-image[^>]*>/', $buffer, $imgList );
        foreach ( ( $imgList[0] ?? [] ) as $imgItem ) {
            if ( strpos( $imgItem, 'style=' ) === false || strpos( $imgItem, 'url(' ) === false ) {
                continue;
            }

            $mainDelimiter = substr( explode( 'style=', str_replace( '\\', '', $imgItem ) )[1] ?? '', 0, 1 );
            $subDelimiter = substr( explode( 'url(', str_replace( '\\', '', $imgItem ) )[1] ?? '', 0, 1 );
            if ( in_array( $subDelimiter, array( '"', "'", ' ' ) ) ) {
                $url = preg_split( '/[\'" ]{1}\)/', preg_split( '/url\([\'" ]{1}/', $imgItem, -1 )[1] ?? '', -1 )[0] ?? '';
            } else {
                $url = preg_split( '/\)/', preg_split( '/url\(/', $imgItem, -1 )[1] ?? '', -1 )[0] ?? '';
                $subDelimiter = '';
            }

            $newImgItem = $imgItem;

            $url = Fifu_Html_Attribute_Utils::normalize( $url );
            $url = self::get_main_url( $url );
            if ( ! $url ) {
                continue;
            }

            if ( isset( $FIFU_SESSION[ $url ] ) ) {
                $data = $FIFU_SESSION[ $url ];

                if ( strpos( $imgItem, 'fifulocal-replaced' ) !== false ) {
                    continue;
                }

                if ( ! ( $data['local'] ?? false ) ) {
                    continue;
                }

                $post_id = $data['post_id'] ?? null;
                $newImgItem = str_replace( '>', ' ' . 'post-id="' . $post_id . '">', $newImgItem );
            }

            if ( $newImgItem != $imgItem ) {
                $buffer = str_replace( $imgItem, $newImgItem, $buffer );
            }
        }

        return $buffer;
    }

    /**
     * Normalizes a URL so duplicate size suffixes are removed.
     *
     * @param string $url
     * @return string|null
     */
    private static function get_main_url( string $url ): ?string {
        if ( ! $url ) {
            return null;
        }

        $aux = explode( '.', $url );
        if ( ! $aux || sizeof( $aux ) <= 1 ) {
            return null;
        }

        $extension = $aux[ sizeof( $aux ) - 1 ] ?? '';
        if ( ! $extension ) {
            return null;
        }

        return preg_replace( '/-[0-9]+x[0-9]+.[a-z]{1,4}$/', '.' . $extension, $url );
    }
}
