<?php

if (!defined('ABSPATH')) {
    exit;
}

class Fifu_Attachment_Dimensions_Service {

    public static function update_dimensions( int $attachment_id, int $width, int $height ): void {
        if ( ! $attachment_id || ! $width || ! $height ) {
            return;
        }

        $metadata = [
            'width' => $width,
            'height' => $height,
        ];

        wp_update_attachment_metadata( $attachment_id, $metadata );
    }
}
