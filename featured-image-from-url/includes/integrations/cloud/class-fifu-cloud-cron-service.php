<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Cloud_Cron_Service
{
    private const UPLOAD_SCHEDULE =
        'fifu_schedule_cloud_upload_auto';

    private const DELETE_SCHEDULE =
        'fifu_schedule_cloud_delete_auto';

    private const UPLOAD_HOOK =
        'fifu_create_cloud_upload_auto_event';

    private const DELETE_HOOK =
        'fifu_create_cloud_delete_auto_event';

    private const UPLOAD_OPTION =
        'fifu_cloud_upload_auto';

    private const DELETE_OPTION =
        'fifu_cloud_delete_auto';

    /**
     * Registers the local WordPress cron schedules and callbacks.
     */
    public static function register_hooks(): void
    {
        add_filter(
            'cron_schedules',
            [self::class, 'add_schedules']
        );

        add_action(
            'init',
            [self::class, 'sync_schedules']
        );

        add_action(
            self::UPLOAD_HOOK,
            [self::class, 'run_upload_job']
        );

        add_action(
            self::DELETE_HOOK,
            [self::class, 'run_delete_job']
        );
    }

    /**
     * Adds the two Free FIFU Cloud recurrence intervals.
     *
     * @param mixed $schedules
     */
    public static function add_schedules($schedules) {
        if (!is_array($schedules)) {
            return $schedules;
        }

        if (!isset($schedules[self::UPLOAD_SCHEDULE])) {
            $schedules[self::UPLOAD_SCHEDULE] = [
                'interval' => 5 * 60,
                'display' => 'fifu-cloud-upload-auto',
            ];
        }

        if (!isset($schedules[self::DELETE_SCHEDULE])) {
            $schedules[self::DELETE_SCHEDULE] = [
                'interval' => 24 * 60 * 60,
                'display' => 'fifu-cloud-delete-auto',
            ];
        }

        return $schedules;
    }

    /**
     * Repairs local schedules to match the two persisted toggles.
     */
    public static function sync_schedules(): void
    {
        self::sync_schedule(
            self::UPLOAD_OPTION,
            self::UPLOAD_SCHEDULE,
            self::UPLOAD_HOOK
        );

        self::sync_schedule(
            self::DELETE_OPTION,
            self::DELETE_SCHEDULE,
            self::DELETE_HOOK
        );
    }

    /**
     * Runs the automatic Cloud upload callback.
     */
    public static function run_upload_job(): void
    {
        if (!Fifu_Options_Utils::is_on(self::UPLOAD_OPTION)) {
            wp_clear_scheduled_hook(self::UPLOAD_HOOK);
            return;
        }

        Fifu_Cloud_Media_Service::run_upload_auto_job();
    }

    /**
     * Runs the automatic Cloud deletion callback.
     */
    public static function run_delete_job(): void
    {
        if (!Fifu_Options_Utils::is_on(self::DELETE_OPTION)) {
            wp_clear_scheduled_hook(self::DELETE_HOOK);
            return;
        }

        Fifu_Cloud_Media_Service::run_delete_auto_job();
    }

    /**
     * Removes the two local Cloud schedules.
     */
    public static function clear_schedules(): void
    {
        wp_clear_scheduled_hook(self::UPLOAD_HOOK);
        wp_clear_scheduled_hook(self::DELETE_HOOK);
    }

    private static function sync_schedule(
        string $option,
        string $schedule,
        string $hook
    ): void {
        $nextScheduled = wp_next_scheduled($hook);

        if (Fifu_Options_Utils::is_on($option)) {
            if ($nextScheduled === false) {
                wp_schedule_event(
                    time(),
                    $schedule,
                    $hook
                );
            }

            return;
        }

        if ($nextScheduled !== false) {
            wp_clear_scheduled_hook($hook);
        }
    }
}
