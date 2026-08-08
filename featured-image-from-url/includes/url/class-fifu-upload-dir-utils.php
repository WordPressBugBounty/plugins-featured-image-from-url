<?php

defined( 'ABSPATH' ) || exit;

/**
 * Upload directory utility helpers for FIFU.
 *
 * @package Fifu_Free
 */
class Fifu_Upload_Dir_Utils {

    public static function get_upload_dir() {
        $upload_dir   = wp_upload_dir();
        $base_dir     = $upload_dir['basedir'] ?? '';
        $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $relative_path = str_replace( $document_root, '', $base_dir );
        return '/' . trim( $relative_path, '/' ) . '/';
    }
}
