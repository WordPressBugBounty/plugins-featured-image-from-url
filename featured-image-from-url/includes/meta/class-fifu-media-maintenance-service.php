<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Media_Maintenance_Service {

    /**
     * Runs a full media cleanup for FIFU-managed local attachments.
     *
     * This should delete obsolete media, disable fake images if needed,
     * and update related plugin options.
     *
     * @return void
     */
    public static function run_full_cleanup(): void {
        $cleanup = new Fifu_Local_Media_Cleanup(null, (int) Fifu_Options_Utils::get_author());
        $cleanup->delete_garbage();

        Fifu_Meta_Maintenance_Controller::run_metaout();

        update_option('fifu_fake', 'toggleoff', 'no');
    }

    /**
     * Deletes _wp_attachment_metadata entries for attachments owned by the FIFU author,
     * forcing WordPress to regenerate attachment metadata on demand.
     *
     * @return void
     */
    public static function reset_attachment_metadata_for_author(): void {
        global $wpdb;

        $author_id = (int) Fifu_Options_Utils::get_author();
        $query = $wpdb->prepare(
            "
            DELETE FROM {$wpdb->postmeta} pm
            WHERE pm.meta_key = %s
            AND EXISTS (
                SELECT 1
                FROM {$wpdb->posts} p
                WHERE p.id = pm.post_id
                AND p.post_author = %d
            )
            ",
            '_wp_attachment_metadata',
            $author_id
        );

        $wpdb->query($query);
    }
}
