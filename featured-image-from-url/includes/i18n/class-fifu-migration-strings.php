<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

class Fifu_Migration_Strings {
    /**
     * Return the strings needed by the migration admin notice.
     *
     * @return array<string, mixed>
     */
    public static function get_admin_notice_strings(): array {
        $fifu = array();

        $fifu['notice']['title'] = function () {
            return __("FIFU: Database migration is running.", FIFU_SLUG);
        };

        $fifu['notice']['current_task_label'] = function () {
            return __("Current task", FIFU_SLUG);
        };

        $fifu['notice']['tasks_remaining'] = function () {
            return __("%d tasks remaining", FIFU_SLUG);
        };

        $fifu['notice']['last_run_label'] = function () {
            return __("Last run", FIFU_SLUG);
        };

        $fifu['notice']['last_error_label'] = function () {
            return __("Last error", FIFU_SLUG);
        };

        $fifu['notice']['paused_until_label'] = function () {
            return __("Paused until", FIFU_SLUG);
        };

        $fifu['notice']['none'] = function () {
            return __("None", FIFU_SLUG);
        };

        $fifu['notice']['never'] = function () {
            return __("Never", FIFU_SLUG);
        };

        $fifu['notice']['run_now'] = function () {
            return __("Run now", FIFU_SLUG);
        };

        $fifu['notice']['status_sending'] = function () {
            return __("Sending tick requests...", FIFU_SLUG);
        };

        $fifu['notice']['status_sent'] = function () {
            return __("Tick requests sent.", FIFU_SLUG);
        };

        $fifu['status'] = array(
            'title' => function () {
                return __("Migration Status", FIFU_SLUG);
            },
            'page_title' => function () {
                return __("DB Migration", FIFU_SLUG);
            },
            'page_description' => function () {
                return __("This tool migrates existing FIFU legacy metadata to the optimized DB storage. The migration runs in the background.", FIFU_SLUG);
            },
            'page_safety_note' => function () {
                return __("Legacy data is removed only after the corresponding DB data is confirmed.", FIFU_SLUG);
            },
            'summary_title' => function () {
                return __("Migration summary", FIFU_SLUG);
            },
            'overall_status_label' => function () {
                return __("Overall status", FIFU_SLUG);
            },
            'current_step_label' => function () {
                return __("Current step", FIFU_SLUG);
            },
            'progress_label' => function () {
                return __("Progress", FIFU_SLUG);
            },
            'migrated_records_label' => function () {
                return __("Migrated in this pass", FIFU_SLUG);
            },
            'historical_processed_records_label' => function () {
                return __("Historical processed records", FIFU_SLUG);
            },
            'remaining_records_label' => function () {
                return __("Remaining records", FIFU_SLUG);
            },
            'total_errors_label' => function () {
                return __("Errors", FIFU_SLUG);
            },
            'summary_not_started' => function () {
                return __("Not started", FIFU_SLUG);
            },
            'summary_running' => function () {
                return __("Running", FIFU_SLUG);
            },
            'summary_paused' => function () {
                return __("Paused", FIFU_SLUG);
            },
            'summary_needs_attention' => function () {
                return __("Needs attention", FIFU_SLUG);
            },
            'summary_completed' => function () {
                return __("Completed", FIFU_SLUG);
            },
            'no_runner' => function () {
                return __("Automatic migration runtime is not available on this site.", FIFU_SLUG);
            },
            'completed' => function () {
                return __("Migration completed", FIFU_SLUG);
            },
            'sending' => function () {
                return __("Sending tick requests...", FIFU_SLUG);
            },
            'sent' => function () {
                return __("Tick requests sent.", FIFU_SLUG);
            },
            'current_task_label' => function () {
                return __("Current task", FIFU_SLUG);
            },
            'progress_label' => function () {
                return __("Progress", FIFU_SLUG);
            },
            'last_run_label' => function () {
                return __("Last run", FIFU_SLUG);
            },
            'last_error_label' => function () {
                return __("Last error", FIFU_SLUG);
            },
            'paused_until_label' => function () {
                return __("Paused until", FIFU_SLUG);
            },
            'step_label' => function () {
                return __("Step", FIFU_SLUG);
            },
            'status_label' => function () {
                return __("Status", FIFU_SLUG);
            },
            'cursor_label' => function () {
                return __("Cursor", FIFU_SLUG);
            },
            'migrated_label' => function () {
                return __("Migrated", FIFU_SLUG);
            },
            'remaining_label' => function () {
                return __("Remaining", FIFU_SLUG);
            },
            'errors_label' => function () {
                return __("Errors", FIFU_SLUG);
            },
            'last_update_label' => function () {
                return __("Last update", FIFU_SLUG);
            },
            'status_values_hint' => function () {
                return __("Status values: pending, running, finished.", FIFU_SLUG);
            },
            'no_tasks' => function () {
                return __("No migration tasks registered.", FIFU_SLUG);
            },
            'none' => function () {
                return __("None", FIFU_SLUG);
            },
            'never' => function () {
                return __("Never", FIFU_SLUG);
            },
            'action_start' => function () {
                return __("Start migration", FIFU_SLUG);
            },
            'action_pause' => function () {
                return __("Pause migration", FIFU_SLUG);
            },
            'action_resume' => function () {
                return __("Resume migration", FIFU_SLUG);
            },
            'action_retry' => function () {
                return __("Retry failed items", FIFU_SLUG);
            },
            'action_refresh' => function () {
                return __("Refresh status", FIFU_SLUG);
            },
            'action_scan_legacy' => function () {
                return __("Scan for new legacy data", FIFU_SLUG);
            },
            'action_starting' => function () {
                return __("Starting migration...", FIFU_SLUG);
            },
            'action_pausing' => function () {
                return __("Pausing migration...", FIFU_SLUG);
            },
            'action_resuming' => function () {
                return __("Resuming migration...", FIFU_SLUG);
            },
            'action_retrying' => function () {
                return __("Retrying failed items...", FIFU_SLUG);
            },
            'action_refreshing' => function () {
                return __("Refreshing status...", FIFU_SLUG);
            },
            'action_scanning_legacy' => function () {
                return __("Scanning for new legacy data...", FIFU_SLUG);
            },
            'action_copy_diagnostics' => function () {
                return __("Copy diagnostic info", FIFU_SLUG);
            },
            'action_copying_diagnostics' => function () {
                return __("Copying diagnostic info...", FIFU_SLUG);
            },
            'action_copy_diagnostics_success' => function () {
                return __("Diagnostic info copied.", FIFU_SLUG);
            },
            'action_copy_diagnostics_failed' => function () {
                return __("Could not copy diagnostic info.", FIFU_SLUG);
            },
            'diagnostic_report_title' => function () {
                return __("FIFU migration diagnostic report", FIFU_SLUG);
            },
            'control_failed' => function () {
                return __("Migration control request failed.", FIFU_SLUG);
            },
        );

        return $fifu;
    }
}
