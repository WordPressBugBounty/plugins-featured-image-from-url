<?php

defined( 'ABSPATH' ) || exit;

/**
 * Provides AJAX attachment filters for FIFU-managed media.
 */
class Fifu_Attachment_Ajax_Filters {

    /**
     * Blocks remote FIFU attachments when the media modal requests them.
     */
    public static function intercept_get_attachment(): void {
        $att_id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;

        if ( $att_id > 0 && Fifu_Post_Meta_Utils::is_remote_image( $att_id ) ) {
            $response = array(
                'success' => false,
                'data' => array(),
            );
            wp_send_json( $response );
        }
    }
}
