<?php

defined( 'ABSPATH' ) || exit;

/**
 * Placeholder filters for future FIFU attachment compatibility passes.
 */
final class Fifu_Attachment_Compat_Filters {

    /**
     * Keeps WooCommerce thumbnail filter behavior until overrides are needed.
     *
     * @param mixed      $html
     * @param mixed|null $post_id
     */
    public static function passthrough_woocommerce_thumbnail_html( $html, $post_id = null ) {
        // Pass-through so legacy filter behavior remains unchanged for now.
        return $html;
    }

    /**
     * Sanitizes attachment metadata for FIFU-owned attachments.
     *
     * @param mixed $data
     * @param mixed $att_id
     * @return mixed
     */
    public static function passthrough_attachment_metadata( $data, $att_id ) {
        $att_id = is_numeric( $att_id ) ? (int) $att_id : 0;

        if ( $att_id <= 0 ) {
            return $data;
        }

        if ( ! self::is_fifu_attachment( $att_id ) ) {
            return $data;
        }

        if ( ! is_array( $data ) ) {
            return false;
        }

        return self::sanitize_attachment_metadata( $data );
    }

    /**
     * Checks whether an attachment is FIFU-owned.
     *
     * @param int $attachment_id
     * @return bool
     */
    private static function is_fifu_attachment( int $attachment_id ): bool {
        if ( $attachment_id <= 0 ) {
            return false;
        }

        if ( function_exists( 'fifu_is_fifu_attachment' ) ) {
            return (bool) fifu_is_fifu_attachment( $attachment_id );
        }

        if ( ! function_exists( 'get_post' ) ) {
            return false;
        }

        $att_post = get_post( $attachment_id );
        if ( ! $att_post || ! isset( $att_post->post_author ) ) {
            return false;
        }

        $authors = [];

        if ( function_exists( 'fifu_get_fifu_author_candidates' ) ) {
            $authors = array_merge( $authors, (array) fifu_get_fifu_author_candidates() );
        }

        if ( function_exists( 'fifu_get_author' ) ) {
            $authors[] = (int) fifu_get_author();
        }

        if ( defined( 'FIFU_AUTHOR' ) ) {
            $authors[] = (int) FIFU_AUTHOR;
        }

        $authors[] = 7777777777;
        $authors[] = 77777;

        $authors = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn( $author ): int => (int) $author,
                        $authors
                    ),
                    static fn( int $author ): bool => $author > 0
                )
            )
        );

        return in_array( (int) $att_post->post_author, $authors, true );
    }

    /**
     * Normalizes a dimension value to a positive integer when safe.
     *
     * @param mixed $value
     * @return int|null
     */
    private static function normalize_dimension( $value ): ?int {
        if ( is_int( $value ) || is_float( $value ) || is_string( $value ) ) {
            $trimmed = trim( (string) $value );
            if ( $trimmed !== '' && is_numeric( $trimmed ) ) {
                return (int) round( (float) $trimmed );
            }
        }

        return null;
    }

    /**
     * Sanitizes attachment metadata so WordPress core never sees invalid dimensions.
     *
     * @param array $metadata
     * @return array
     */
    private static function sanitize_attachment_metadata( array $metadata ): array {
        foreach ( [ 'width', 'height' ] as $dimension_key ) {
            if ( ! array_key_exists( $dimension_key, $metadata ) ) {
                continue;
            }

            $normalized = self::normalize_dimension( $metadata[ $dimension_key ] );
            if ( $normalized === null ) {
                unset( $metadata[ $dimension_key ] );
                continue;
            }

            $metadata[ $dimension_key ] = $normalized;
        }

        if ( ! array_key_exists( 'sizes', $metadata ) ) {
            return $metadata;
        }

        if ( ! is_array( $metadata['sizes'] ) ) {
            unset( $metadata['sizes'] );
            return $metadata;
        }

        foreach ( $metadata['sizes'] as $size_name => $size_data ) {
            if ( ! is_array( $size_data ) ) {
                unset( $metadata['sizes'][ $size_name ] );
                continue;
            }

            $width = array_key_exists( 'width', $size_data ) ? self::normalize_dimension( $size_data['width'] ) : null;
            $height = array_key_exists( 'height', $size_data ) ? self::normalize_dimension( $size_data['height'] ) : null;

            if ( ( array_key_exists( 'width', $size_data ) && $width === null ) || ( array_key_exists( 'height', $size_data ) && $height === null ) ) {
                unset( $metadata['sizes'][ $size_name ] );
                continue;
            }

            if ( $width !== null ) {
                $metadata['sizes'][ $size_name ]['width'] = $width;
            }

            if ( $height !== null ) {
                $metadata['sizes'][ $size_name ]['height'] = $height;
            }
        }

        return $metadata;
    }

    /**
     * Keeps wp_get_attachment_image output untouched.
     *
     * @param mixed      $html
     * @param mixed      $attachment_id
     * @param mixed      $size
     * @param mixed      $icon
     * @param array|bool $attr
     */
    public static function passthrough_attachment_image( $html, $attachment_id, $size, $icon, $attr ) {
        // Pass-through stub to preserve compatibility.
        return $html;
    }

    /**
     * Pass-through for calculate_image_sizes.
     *
     * @param mixed  $sizes
     * @param mixed  $size_array
     * @param mixed  $src
     * @return mixed
     */
    public static function passthrough_calculate_image_sizes( $sizes, $size_array, $src ) {
        // Pass-through stub to retain existing hooks.
        return $sizes;
    }

    /**
     * Pass-through for calculate_image_srcset.
     *
     * @param mixed $sources
     * @param mixed $size_array
     * @param mixed $image_src
     * @param mixed $image_meta
     * @param mixed $attachment_id
     */
    public static function passthrough_calculate_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
        // Pass-through stub to preserve srcset hooks.
        return $sources;
    }

    /**
     * Pass-through for calculate_image_srcset_meta.
     *
     * @param mixed $image_meta
     * @param mixed $size_array
     * @param mixed $image_src
     * @param mixed $attachment_id
     * @return array
     */
    public static function passthrough_calculate_image_srcset_meta( $image_meta, $size_array, $image_src, $attachment_id ): array {
        // Pass-through stub to preserve srcset metadata hooks.
        if ( ! is_array( $image_meta ) ) {
            return [];
        }
        return $image_meta;
    }

    /**
     * Pass-through for max_srcset_image_width.
     *
     * @param mixed $max_width
     * @param mixed $size_array
     */
    public static function passthrough_max_srcset_image_width( $max_width, $size_array ) {
        // Pass-through stub for width limits.
        return $max_width;
    }

    /**
     * Pass-through for admin post thumbnail HTML.
     *
     * @param mixed $content
     * @param mixed $post_id
     * @param mixed $thumbnail_id
     */
    public static function passthrough_admin_post_thumbnail_html( $content, $post_id, $thumbnail_id ) {
        // Pass-through stub to keep legacy admin filter behavior intact.
        return $content;
    }
}
