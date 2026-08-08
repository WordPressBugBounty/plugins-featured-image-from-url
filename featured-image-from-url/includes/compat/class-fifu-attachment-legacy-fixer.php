<?php

defined( 'ABSPATH' ) || exit;

/**
 * Fixes legacy FIFU attachment URLs for compatibility.
 */
final class Fifu_Attachment_Legacy_Fixer {

    /**
     * Restores legacy attached file URLs when needed.
     *
     * @param string $url
     * @param int    $att_id
     * @return string
     */
    public static function maybe_fix_legacy_url( string $url, int $att_id ): string {
        if ( strpos( $url, ';' ) === false ) {
            return $url;
        }

        $att_url = get_post_meta( $att_id, '_wp_attached_file' );
        $att_url = is_array( $att_url ) ? ( $att_url[0] ?? '' ) : $att_url;
        if ( Fifu_Generic_Utils::starts_with( $att_url, ';http' ) || Fifu_Generic_Utils::starts_with( $att_url, ';/') ) {
            update_post_meta( $att_id, '_wp_attached_file', $url );
        }

        return $url;
    }
}
