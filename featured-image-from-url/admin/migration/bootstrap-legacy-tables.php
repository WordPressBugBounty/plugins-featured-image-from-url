<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/core/class-fifu-legacy-tables-manager.php';

function fifu_legacy_tables_manager(): Fifu_Legacy_Tables_Manager {
    static $instances = [];
    $blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;

    if ( ! isset( $instances[ $blog_id ] ) ) {
        global $wpdb;
        $instances[ $blog_id ] = new Fifu_Legacy_Tables_Manager( $wpdb );
    }

    return $instances[ $blog_id ];
}

function fifu_db_create_table_invalid_media_su(): void {
    fifu_legacy_tables_manager()->create_invalid_media_su_table();
}

function fifu_db_maybe_create_table_meta_in(): void {
    fifu_legacy_tables_manager()->create_meta_in_table();
}

function fifu_db_maybe_create_table_meta_out(): void {
    fifu_legacy_tables_manager()->create_meta_out_table();
}
