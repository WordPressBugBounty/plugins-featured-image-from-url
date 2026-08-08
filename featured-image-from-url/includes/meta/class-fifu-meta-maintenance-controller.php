<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-fifu-generic-cron-service.php';

/**
 * Coordinates metadata maintenance, queues, and cron toggles.
 *
 * This controller will call the existing services/repositories and map the legacy
 * admin/db behaviors into a single entry point.
 */
final class Fifu_Meta_Maintenance_Controller
{
    /**
     * Clears all stored dimensions metadata for tracked attachments.
     */
    public static function clean_dimensions_all(): void
    {
        Fifu_Media_Maintenance_Service::reset_attachment_metadata_for_author();
    }

    /**
     * Enables the clean metadata job by toggling options and scheduling cron work.
     */
    public static function enable_clean(): void
    {
        Fifu_Metadata_Queue_Service::clear_meta_in();
        Fifu_Media_Maintenance_Service::run_full_cleanup();
    }

    /**
     * Clears the inbound metadata queue.
     */
    public static function clear_meta_in(): void
    {
        Fifu_Metadata_Queue_Service::clear_meta_in();
    }

    /**
     * Clears the outbound metadata queue.
     */
    public static function clear_meta_out(): void
    {
        Fifu_Metadata_Queue_Service::clear_meta_out();
    }

    /**
     * Enables the "fake" mode by preparing incoming metadata.
     *
     * This will clear meta-out queues and trigger the "metain" generic cron job.
     *
     * @return void
     */
    public static function enable_fake(): void
    {
        self::clear_meta_out();
        Fifu_Generic_Cron_Service::run('metain');
    }

    /**
     * Stops fake metadata generation without removing existing metadata.
     *
     * Turning Image Metadata off must not trigger MetaOut. Metadata removal belongs
     * to the Clean Metadata flow.
     *
     * @return void
     */
    public static function disable_fake(): void
    {
        // Intentionally no-op. REST/CLI callers own their toggle/stop options.
    }

    /**
     * Runs the outbound metadata cleanup job.
     *
     * This is used by Clean Metadata flows only. Do not call this when merely
     * turning Image Metadata off.
     *
     * @return void
     */
    public static function run_metaout(): void
    {
        Fifu_Generic_Cron_Service::run('metaout');
    }
}
