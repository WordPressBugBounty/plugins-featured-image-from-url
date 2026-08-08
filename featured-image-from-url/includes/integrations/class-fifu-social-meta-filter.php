<?php

defined( 'ABSPATH' ) || exit;

/**
 * Filters og/twitter image metadata to protect FIFU tags.
 */
class Fifu_Social_Meta_Filter {

    /**
     * Removes non-FIFU og:image/twitter:image tags while preserving FIFU blocks.
     *
     * @param string $buffer
     * @return string
     */
    public static function filter_og_images( string $buffer ): string {
        $pattern_blocks = '/<!--\s*FIFU:meta:begin:[a-z]+\s*-->.*?<!--\s*FIFU:meta:end:[a-z]+\s*-->/is';

        $blocks = array();
        if ( preg_match_all( $pattern_blocks, $buffer, $matches, PREG_OFFSET_CAPTURE ) ) {
            foreach ( $matches[0] as $match ) {
                $blocks[] = $match[0];
            }
        }

        $has_fifu_ogimage = false;
        foreach ( $blocks as $block ) {
            if ( preg_match( '/<meta\s+[^>]*property=["\']og:image[^"\']*["\']/i', $block ) ) {
                $has_fifu_ogimage = true;
                break;
            }
        }

        if ( ! $has_fifu_ogimage ) {
            return $buffer;
        }

        $buffer_preserve = $buffer;
        foreach ( $blocks as $i => $block ) {
            $buffer_preserve = str_replace( $block, "___FIFU_BLOCK_" . ( $i + 1 ) . "___", $buffer_preserve );
        }

        $buffer_preserve = preg_replace( '/<meta\s+[^>]*property=["\']og:image[^"\']*["\'][^>]*>\s*/i', '', $buffer_preserve );
        $buffer_preserve = preg_replace( '/<meta\s+[^>]*name=["\']twitter:image[^"\']*["\'][^>]*>\s*/i', '', $buffer_preserve );

        foreach ( $blocks as $i => $block ) {
            $buffer_preserve = str_replace( "___FIFU_BLOCK_" . ( $i + 1 ) . "___", $block, $buffer_preserve );
        }

        return $buffer_preserve;
    }
}
