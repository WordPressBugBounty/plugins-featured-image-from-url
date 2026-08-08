<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Metadata_Queue_Service {

    /**
     * Clears all entries from the FIFU meta_in table.
     *
     * @return void
     */
    public static function clear_meta_in(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'fifu_meta_in';
        $wpdb->query("DELETE FROM {$table} WHERE 1=1");
    }

    /**
     * Clears all entries from the FIFU meta_out table.
     *
     * @return void
     */
    public static function clear_meta_out(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'fifu_meta_out';
        $wpdb->query("DELETE FROM {$table} WHERE 1=1");
    }

    /**
     * @return Fifu_Metadata_Queue_Repository
     */
    private static function get_metadata_queue_repository(): Fifu_Metadata_Queue_Repository {
        return new Fifu_Metadata_Queue_Repository();
    }

}
