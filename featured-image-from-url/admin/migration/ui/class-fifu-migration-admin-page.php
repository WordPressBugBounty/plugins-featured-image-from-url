<?php

declare(strict_types=1);

/**
 * Provides the admin UI for viewing FIFU migration status.
 */
class Fifu_Migration_Admin_Page {
    /** @var Fifu_Migration_Runner */
    protected $runner;

    /** @var Fifu_Migration_Registry */
    protected $registry;

    /** @var Fifu_Migration_State */
    protected $state;

    /** @var Fifu_Migration_Logger */
    protected $logger;

    /**
     * @param Fifu_Migration_Runner   $runner
     * @param Fifu_Migration_Registry $registry
     * @param Fifu_Migration_State    $state
     * @param Fifu_Migration_Logger   $logger
     */
    public function __construct(
        ?Fifu_Migration_Runner $runner = null,
        ?Fifu_Migration_Registry $registry = null,
        ?Fifu_Migration_State $state = null,
        ?Fifu_Migration_Logger $logger = null
    ) {
        $this->state    = $state    ?: new Fifu_Migration_State();
        $this->logger   = $logger   ?: new Fifu_Migration_Logger();
        $this->registry = $registry ?: new Fifu_Migration_Registry();
        $this->runner   = $runner   ?: new Fifu_Migration_Runner($this->state, $this->logger, $this->registry);
    }

    /**
     * Renders the admin page for FIFU migrations.
     *
     * This method is meant to be called from an existing WordPress admin menu callback.
     *
     * @return void
     */
    public function render_page(): void {
        $tasks = $this->registry->get_all_tasks();
        $auto_runner_available = class_exists('Fifu_Migration_Auto_Runner');

        $summary_defaults = array(
            'needs_backfill'   => false,
            'tasks_total'      => 0,
            'tasks_finished'   => 0,
            'current_task'     => null,
            'last_run'         => 0,
            'last_error'       => '',
            'paused_until'     => 0,
            'last_tick_attempt'=> 0,
        );

        $summary = $summary_defaults;

        if ($auto_runner_available) {
            $summary = array_merge($summary, Fifu_Migration_Auto_Runner::get_progress_summary());
        }

        $strings = array();
        if (class_exists('Fifu_Migration_Strings')) {
            $strings = Fifu_Migration_Strings::get_admin_notice_strings();
        }

        $notice_strings = $strings['notice'] ?? array();
        $status_strings = $strings['status'] ?? array();

        $page_title = isset($status_strings['page_title']) ? (string) $status_strings['page_title']() : (
            isset($status_strings['title']) ? (string) $status_strings['title']() : 'DB Migration'
        );
        $page_description = isset($status_strings['page_description']) ? (string) $status_strings['page_description']() : 'This tool migrates existing FIFU legacy metadata to the optimized DB storage. The migration runs in the background.';
        $page_safety_note = isset($status_strings['page_safety_note']) ? (string) $status_strings['page_safety_note']() : 'Legacy data is removed only after the corresponding DB data is confirmed.';
        $no_runner_text = isset($status_strings['no_runner']) ? (string) $status_strings['no_runner']() : 'Automatic migration runtime is not available on this site.';
        $summary_title = isset($status_strings['summary_title']) ? (string) $status_strings['summary_title']() : 'Migration summary';
        $overall_status_label = isset($status_strings['overall_status_label']) ? (string) $status_strings['overall_status_label']() : 'Overall status';
        $current_step_label = isset($status_strings['current_step_label']) ? (string) $status_strings['current_step_label']() : 'Current step';
        $current_task_label = isset($status_strings['current_task_label']) ? (string) $status_strings['current_task_label']() : 'Current task';
        $progress_label = isset($status_strings['progress_label']) ? (string) $status_strings['progress_label']() : 'Progress';
        $migrated_records_label = isset($status_strings['migrated_records_label']) ? (string) $status_strings['migrated_records_label']() : 'Migrated in this pass';
        $remaining_records_label = isset($status_strings['remaining_records_label']) ? (string) $status_strings['remaining_records_label']() : 'Remaining records';
        $total_errors_label = isset($status_strings['total_errors_label']) ? (string) $status_strings['total_errors_label']() : 'Errors';
        $summary_not_started_label = isset($status_strings['summary_not_started']) ? (string) $status_strings['summary_not_started']() : 'Not started';
        $summary_running_label = isset($status_strings['summary_running']) ? (string) $status_strings['summary_running']() : 'Running';
        $summary_paused_label = isset($status_strings['summary_paused']) ? (string) $status_strings['summary_paused']() : 'Paused';
        $summary_needs_attention_label = isset($status_strings['summary_needs_attention']) ? (string) $status_strings['summary_needs_attention']() : 'Needs attention';
        $summary_completed_label = isset($status_strings['summary_completed']) ? (string) $status_strings['summary_completed']() : 'Completed';
        $last_run_label = isset($status_strings['last_run_label']) ? (string) $status_strings['last_run_label']() : 'Last run';
        $last_error_label = isset($status_strings['last_error_label']) ? (string) $status_strings['last_error_label']() : 'Last error';
        $paused_label = isset($status_strings['paused_until_label']) ? (string) $status_strings['paused_until_label']() : 'Paused until';
        $step_label = isset($status_strings['step_label']) ? (string) $status_strings['step_label']() : 'Step';
        $status_label = isset($status_strings['status_label']) ? (string) $status_strings['status_label']() : 'Status';
        $cursor_label = isset($status_strings['cursor_label']) ? (string) $status_strings['cursor_label']() : 'Cursor';
        $migrated_label = isset($status_strings['migrated_label']) ? (string) $status_strings['migrated_label']() : 'Migrated';
        $historical_processed_records_label = isset($status_strings['historical_processed_records_label']) ? (string) $status_strings['historical_processed_records_label']() : 'Historical processed records';
        $remaining_label = isset($status_strings['remaining_label']) ? (string) $status_strings['remaining_label']() : 'Remaining';
        $errors_label = isset($status_strings['errors_label']) ? (string) $status_strings['errors_label']() : 'Errors';
        $last_update_label = isset($status_strings['last_update_label']) ? (string) $status_strings['last_update_label']() : 'Last update';
        $status_values_hint = isset($status_strings['status_values_hint']) ? (string) $status_strings['status_values_hint']() : 'Status values: pending, running, finished.';
        $no_tasks_text = isset($status_strings['no_tasks']) ? (string) $status_strings['no_tasks']() : 'No migration tasks registered.';
        $none_label = isset($status_strings['none']) ? (string) $status_strings['none']() : 'None';
        $never_label = isset($status_strings['never']) ? (string) $status_strings['never']() : 'Never';
        $action_start_label = isset($status_strings['action_start']) ? (string) $status_strings['action_start']() : 'Start migration';
        $action_pause_label = isset($status_strings['action_pause']) ? (string) $status_strings['action_pause']() : 'Pause migration';
        $action_resume_label = isset($status_strings['action_resume']) ? (string) $status_strings['action_resume']() : 'Resume migration';
        $action_retry_label = isset($status_strings['action_retry']) ? (string) $status_strings['action_retry']() : 'Retry failed items';
        $action_refresh_label = isset($status_strings['action_refresh']) ? (string) $status_strings['action_refresh']() : 'Refresh status';
        $action_scan_legacy_label = isset($status_strings['action_scan_legacy']) ? (string) $status_strings['action_scan_legacy']() : 'Scan for new legacy data';
        $action_starting_label = isset($status_strings['action_starting']) ? (string) $status_strings['action_starting']() : 'Starting migration...';
        $action_pausing_label = isset($status_strings['action_pausing']) ? (string) $status_strings['action_pausing']() : 'Pausing migration...';
        $action_resuming_label = isset($status_strings['action_resuming']) ? (string) $status_strings['action_resuming']() : 'Resuming migration...';
        $action_retrying_label = isset($status_strings['action_retrying']) ? (string) $status_strings['action_retrying']() : 'Retrying failed items...';
        $action_refreshing_label = isset($status_strings['action_refreshing']) ? (string) $status_strings['action_refreshing']() : 'Refreshing status...';
        $action_scanning_legacy_label = isset($status_strings['action_scanning_legacy']) ? (string) $status_strings['action_scanning_legacy']() : 'Scanning for new legacy data...';
        $action_copy_diagnostics_label = isset($status_strings['action_copy_diagnostics']) ? (string) $status_strings['action_copy_diagnostics']() : 'Copy diagnostic info';
        $action_copying_diagnostics_label = isset($status_strings['action_copying_diagnostics']) ? (string) $status_strings['action_copying_diagnostics']() : 'Copying diagnostic info...';
        $action_copy_diagnostics_success_label = isset($status_strings['action_copy_diagnostics_success']) ? (string) $status_strings['action_copy_diagnostics_success']() : 'Diagnostic info copied.';
        $action_copy_diagnostics_failed_label = isset($status_strings['action_copy_diagnostics_failed']) ? (string) $status_strings['action_copy_diagnostics_failed']() : 'Could not copy diagnostic info.';
        $diagnostic_report_title = isset($status_strings['diagnostic_report_title']) ? (string) $status_strings['diagnostic_report_title']() : 'FIFU migration diagnostic report';
        $control_failed_label = isset($status_strings['control_failed']) ? (string) $status_strings['control_failed']() : 'Migration control request failed.';
        $tasks_remaining_format = isset($notice_strings['tasks_remaining']) ? (string) $notice_strings['tasks_remaining']() : '%d tasks remaining';

        $mode = isset($summary['mode']) ? (string) $summary['mode'] : 'single';
        $network_mode = $mode === 'network';
        $needs_backfill = !empty($summary['needs_backfill']);
        $total = max(0, (int) ($summary['tasks_total'] ?? 0));
        $done = max(0, (int) ($summary['tasks_finished'] ?? 0));
        $percent = $total > 0 ? (int) floor(($done / $total) * 100) : ($needs_backfill ? 0 : 100);
        $raw_current_task = trim((string) ($summary['current_task'] ?? ''));
        $current_task = $raw_current_task === '' ? $none_label : $raw_current_task;
        $last_run_timestamp = (int) ($summary['last_run'] ?? 0);
        $last_run_text = $last_run_timestamp > 0 ? date_i18n('Y-m-d H:i:s', $last_run_timestamp) : $never_label;
        $last_error = trim((string) ($summary['last_error'] ?? ''));
        $last_error_text = $last_error === '' ? $none_label : $last_error;
        $paused_until = (int) ($summary['paused_until'] ?? 0);
        $paused_until_text = $paused_until > 0 ? date_i18n('Y-m-d H:i:s', $paused_until) : '';
        $tasks_remaining_text = sprintf($tasks_remaining_format, max(0, $total - $done));
        $token = $auto_runner_available ? Fifu_Migration_Auto_Runner::get_token() : '';
        $control_url = function_exists('rest_url') ? rest_url(FIFU_REST_NAMESPACE_V2 . '/migration/control') : '';
        $status_url = function_exists('rest_url') ? rest_url(FIFU_REST_NAMESPACE_V2 . '/migration/status') : '';
        $controls_available = $auto_runner_available && $token !== '' && $status_url !== '';
        $has_task_errors = false;
        $migrated_records_total = 0;
        $historical_processed_records_total = 0;
        $remaining_records_total = 0;
        $has_remaining_records_total = false;
        $total_task_errors = 0;
        foreach ($tasks as $task) {
            if (!is_object($task) || !method_exists($task, 'get_name')) {
                continue;
            }

            $task_state = $this->state->get_task_state($task->get_name());

            $processed_count = (int) ($task_state['processed_count'] ?? 0);
            $scan_start_processed_count = array_key_exists('scan_start_processed_count', $task_state) && is_numeric($task_state['scan_start_processed_count'])
                ? (int) $task_state['scan_start_processed_count']
                : 0;
            $pass_processed_count = max(0, $processed_count - $scan_start_processed_count);
            $error_count = (int) ($task_state['error_count'] ?? 0);

            $migrated_records_total += $pass_processed_count;
            $historical_processed_records_total += $processed_count;
            $total_task_errors += $error_count;

            if ($error_count > 0) {
                $has_task_errors = true;
            }

            if (array_key_exists('remaining_count', $task_state) && is_numeric($task_state['remaining_count'])) {
                $has_remaining_records_total = true;
                $remaining_records_total += (int) $task_state['remaining_count'];
            }
        }
        $has_retryable_failure = $last_error !== '' || $has_task_errors;
        $is_paused = $paused_until > time();
        $all_tasks_finished = $total > 0 && $done >= $total;
        $is_completed = (!$needs_backfill || $all_tasks_finished);
        if ($has_retryable_failure) {
            $overall_status = $summary_needs_attention_label;
        } elseif ($paused_until > time()) {
            $overall_status = $summary_paused_label;
        } elseif (!$needs_backfill || $all_tasks_finished) {
            $overall_status = $summary_completed_label;
        } elseif ($raw_current_task !== '') {
            $overall_status = $summary_running_label;
        } else {
            $overall_status = $summary_not_started_label;
        }
        $remaining_records_display = $has_remaining_records_total ? (string) $remaining_records_total : '&mdash;';
        $diagnostic_lines = array();
        $diagnostic_lines[] = $diagnostic_report_title;
        $diagnostic_lines[] = 'Mode: ' . $mode;
        $diagnostic_lines[] = 'Overall status: ' . $overall_status;
        $diagnostic_lines[] = 'Current step: ' . $current_task;
        $diagnostic_lines[] = 'Progress: ' . $done . ' / ' . $total . ' (' . $percent . '%)';
        $diagnostic_lines[] = 'Migrated in this pass: ' . $migrated_records_total;
        $diagnostic_lines[] = 'Historical processed records: ' . $historical_processed_records_total;
        $diagnostic_lines[] = 'Remaining records: ' . ($has_remaining_records_total ? (string) $remaining_records_total : '—');
        $diagnostic_lines[] = 'Errors: ' . $total_task_errors;
        $diagnostic_lines[] = 'Last run: ' . $last_run_text;
        $diagnostic_lines[] = 'Last error: ' . $last_error_text;
        if ($paused_until > 0) {
            $diagnostic_lines[] = 'Paused until: ' . $paused_until_text;
        }
        $diagnostic_lines[] = 'Task states:';
        foreach ($tasks as $task) {
            if (!is_object($task) || !method_exists($task, 'get_name')) {
                continue;
            }

            $task_name = (string) $task->get_name();
            $task_label = method_exists($task, 'get_label') ? (string) $task->get_label() : $task_name;
            $task_state = $this->state->get_task_state($task_name);
            $task_status = (string) ($task_state['status'] ?? 'pending');
            $task_cursor = (string) ($task_state['last_id'] ?? 0);
            $task_migrated = (string) ($task_state['processed_count'] ?? 0);
            $task_scan_start = array_key_exists('scan_start_processed_count', $task_state) && is_numeric($task_state['scan_start_processed_count'])
                ? (string) $task_state['scan_start_processed_count']
                : '0';
            $task_pass_migrated = max(0, (int) $task_migrated - (int) $task_scan_start);
            $task_remaining = array_key_exists('remaining_count', $task_state) && is_numeric($task_state['remaining_count']) ? (string) $task_state['remaining_count'] : '—';
            $task_errors = (string) ($task_state['error_count'] ?? 0);
            $task_updated = (string) ($task_state['updated_at'] ?? '');
            $diagnostic_lines[] = '- ' . $task_name . ' | label=' . $task_label . ' | status=' . $task_status . ' | cursor=' . $task_cursor . ' | migrated=' . $task_migrated . ' | migrated_in_pass=' . $task_pass_migrated . ' | historical_migrated=' . $task_migrated . ' | scan_start=' . $task_scan_start . ' | remaining=' . $task_remaining . ' | errors=' . $task_errors . ' | updated=' . $task_updated;
        }
        $diagnostic_report = implode("\n", $diagnostic_lines);
        $show_retry = $controls_available && $has_retryable_failure;
        $show_start = $controls_available && $needs_backfill && !$has_retryable_failure && !$is_paused && $raw_current_task === '';
        $show_pause = $controls_available && $needs_backfill && !$has_retryable_failure && !$is_paused && $raw_current_task !== '';
        $show_resume = $controls_available && $needs_backfill && !$has_retryable_failure && $is_paused;
        $show_scan = $controls_available && $is_completed && !$has_retryable_failure;
        $show_refresh = $controls_available;
        $show_copy_diagnostics = $controls_available;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html($page_title) . '</h1>';
        echo '<p>' . esc_html($page_description) . '</p>';
        echo '<p><em>' . esc_html($page_safety_note) . '</em></p>';
        ?>
        <style>
            #fifu-migration-page-status > .hndle {
                padding-inline-start: 12px;
            }

            #fifu-migration-page-controls {
                margin-bottom: 0;
            }

            .fifu-migration-table-fixed {
                table-layout: fixed;
                width: 100%;
            }

            .fifu-migration-table-fixed th,
            .fifu-migration-table-fixed td {
                overflow-wrap: anywhere;
                vertical-align: top;
            }

            .fifu-migration-summary-table th {
                width: 34%;
            }

            .fifu-migration-summary-table td {
                width: 66%;
            }

            .fifu-migration-num,
            .fifu-mig-num,
            .fifu-migration-mono {
                font-variant-numeric: tabular-nums;
            }

            .fifu-migration-nowrap {
                white-space: nowrap;
            }

            .fifu-migration-progress-cell progress {
                width: 160px;
                max-width: 100%;
            }
        </style>
        <div id="fifu-migration-page-status" class="postbox">
            <h2 class="hndle"><span><?php echo esc_html($summary_title); ?></span></h2>
            <div class="inside">
                <?php if (!$auto_runner_available) : ?>
                    <div class="notice notice-warning inline">
                        <p><?php echo esc_html($no_runner_text); ?></p>
                    </div>
                <?php endif; ?>
                <div id="fifu-migration-page-summary" class="fifu-migration-summary" style="margin-bottom:12px;">
                    <table class="widefat striped fifu-migration-table-fixed fifu-migration-summary-table" style="margin:0;">
                        <colgroup>
                            <col style="width:34%;">
                            <col style="width:66%;">
                        </colgroup>
                        <tbody>
                            <tr>
                                <th><?php echo esc_html($overall_status_label); ?></th>
                                <td data-summary-field="overall_status"><?php echo esc_html($overall_status); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html($current_step_label); ?></th>
                                <td data-summary-field="current_step"><?php echo esc_html($current_task); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html($progress_label); ?></th>
                                <td class="fifu-migration-num" data-summary-field="progress"><?php echo esc_html($done . ' / ' . $total . ' (' . $percent . '%)'); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html($migrated_records_label); ?></th>
                                <td class="fifu-migration-num" data-summary-field="migrated_records"><?php echo esc_html((string) $migrated_records_total); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html($historical_processed_records_label); ?></th>
                                <td class="fifu-migration-num" data-summary-field="historical_processed_records"><?php echo esc_html((string) $historical_processed_records_total); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html($remaining_records_label); ?></th>
                                <td class="fifu-migration-num" data-summary-field="remaining_records"><?php echo $has_remaining_records_total ? esc_html((string) $remaining_records_total) : '&mdash;'; ?></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html($total_errors_label); ?></th>
                                <td class="fifu-migration-num" data-summary-field="total_errors"><?php echo esc_html((string) $total_task_errors); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <table class="widefat striped fifu-migration-table-fixed" style="margin:0;">
                    <colgroup>
                        <col style="width:36%;">
                        <col style="width:25%;">
                        <col style="width:22%;">
                        <col style="width:17%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th><?php echo esc_html($current_task_label); ?></th>
                            <th><?php echo esc_html($progress_label); ?></th>
                            <th><?php echo esc_html($last_run_label); ?></th>
                            <th><?php echo esc_html($last_error_label); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($current_task_label); ?>:</strong><br>
                                <span id="fifu-migration-page-current-task"><?php echo esc_html($current_task); ?></span>
                                <span style="opacity:.8;"> — </span>
                                <span id="fifu-migration-page-tasks-remaining"><?php echo esc_html($tasks_remaining_text); ?></span>
                            </td>
                            <td class="fifu-migration-progress-cell">
                                <span id="fifu-migration-page-progress-text" class="fifu-migration-num"><?php echo esc_html($done); ?> / <?php echo esc_html($total); ?> (<?php echo esc_html($percent); ?>%)</span><br>
                                <?php if ($total > 0) : ?>
                                    <progress id="fifu-migration-page-progress-bar" value="<?php echo esc_attr($done); ?>" max="<?php echo esc_attr($total); ?>"></progress>
                                <?php else : ?>
                                    <progress id="fifu-migration-page-progress-bar" value="<?php echo esc_attr($percent); ?>" max="100"></progress>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span id="fifu-migration-page-last-run" class="fifu-migration-mono"><?php echo esc_html($last_run_text); ?></span>
                            </td>
                            <td>
                                <span id="fifu-migration-page-last-error"><?php echo esc_html($last_error_text); ?></span>
                            </td>
                        </tr>
                        <tr id="fifu-migration-page-paused" style="display:none;">
                            <td colspan="4">
                                <strong><?php echo esc_html($paused_label); ?>:</strong>
                                <span id="fifu-migration-page-paused-until"><?php echo $paused_until > 0 ? esc_html(date_i18n('Y-m-d H:i:s', $paused_until)) : ''; ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php if ($show_start || $show_pause || $show_resume || $show_retry || $show_scan || $show_refresh || $show_copy_diagnostics) : ?>
                    <p
                        id="fifu-migration-page-controls"
                        data-control-url="<?php echo esc_attr($control_url); ?>"
                        data-status-url="<?php echo esc_attr($status_url); ?>"
                        data-token="<?php echo esc_attr($token); ?>"
                        data-label-starting="<?php echo esc_attr($action_starting_label); ?>"
                        data-label-pausing="<?php echo esc_attr($action_pausing_label); ?>"
                        data-label-resuming="<?php echo esc_attr($action_resuming_label); ?>"
                        data-label-retrying="<?php echo esc_attr($action_retrying_label); ?>"
                        data-label-scanning="<?php echo esc_attr($action_scanning_legacy_label); ?>"
                        data-label-refreshing="<?php echo esc_attr($action_refreshing_label); ?>"
                        data-label-copying="<?php echo esc_attr($action_copying_diagnostics_label); ?>"
                        data-label-copy-success="<?php echo esc_attr($action_copy_diagnostics_success_label); ?>"
                        data-label-copy-failed="<?php echo esc_attr($action_copy_diagnostics_failed_label); ?>"
                        data-label-failed="<?php echo esc_attr($control_failed_label); ?>"
                    >
                        <?php if ($show_start) : ?>
                            <button type="button" class="button button-primary" id="fifu-migration-page-start" data-action="start">
                                <?php echo esc_html($action_start_label); ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($show_pause) : ?>
                            <button type="button" class="button button-secondary" id="fifu-migration-page-pause" data-action="pause">
                                <?php echo esc_html($action_pause_label); ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($show_resume) : ?>
                            <button type="button" class="button button-primary" id="fifu-migration-page-resume" data-action="resume">
                                <?php echo esc_html($action_resume_label); ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($show_retry) : ?>
                            <button type="button" class="button button-primary" id="fifu-migration-page-retry" data-action="retry">
                                <?php echo esc_html($action_retry_label); ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($show_scan) : ?>
                            <button type="button" class="button button-primary" id="fifu-migration-page-scan" data-action="scan">
                                <?php echo esc_html($action_scan_legacy_label); ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($show_refresh) : ?>
                            <button type="button" class="button button-secondary" id="fifu-migration-page-refresh" data-action="refresh">
                                <?php echo esc_html($action_refresh_label); ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($show_copy_diagnostics) : ?>
                            <button type="button" class="button button-secondary" id="fifu-migration-page-copy-diagnostics" data-action="copy-diagnostics">
                                <?php echo esc_html($action_copy_diagnostics_label); ?>
                            </button>
                        <?php endif; ?>

                        <span id="fifu-migration-page-control-status" style="margin-left:8px;"></span>
                    </p>
                    <textarea id="fifu-migration-page-diagnostics" readonly style="position:absolute;left:-9999px;width:1px;height:1px;"><?php echo esc_textarea($diagnostic_report); ?></textarea>
                    <script>
                        (function () {
                            var controls = document.getElementById('fifu-migration-page-controls');
                            if (!controls) {
                                return;
                            }

                            var controlUrl = controls.getAttribute('data-control-url') || '';
                            var statusUrl = controls.getAttribute('data-status-url') || '';
                            var token = controls.getAttribute('data-token') || '';
                            var copyButton = document.getElementById('fifu-migration-page-copy-diagnostics');
                            var diagnostics = document.getElementById('fifu-migration-page-diagnostics');
                            var statusLabel = document.getElementById('fifu-migration-page-control-status');

                            function setMessage(message) {
                                if (statusLabel) {
                                    statusLabel.textContent = message || '';
                                }
                            }

                            function buildUrl(base, params) {
                                var url = base;
                                var separator = url.indexOf('?') === -1 ? '?' : '&';
                                var query = [];

                                Object.keys(params).forEach(function (key) {
                                    query.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[key]));
                                });

                                return url + separator + query.join('&');
                            }

                            function asInt(value, fallback) {
                                var parsed = parseInt(value, 10);

                                return isNaN(parsed) ? fallback : parsed;
                            }

                            function setText(selector, value) {
                                var node = document.querySelector(selector);

                                if (node) {
                                    node.textContent = value;
                                }
                            }

                            function setSummaryField(field, value) {
                                var node = document.querySelector('[data-summary-field="' + field + '"]');

                                if (node) {
                                    node.textContent = value;
                                }
                            }

                            function setTaskField(row, field, value) {
                                var node = row.querySelector('[data-field="' + field + '"]');

                                if (node) {
                                    node.textContent = String(value);
                                }
                            }

                            function formatProgress(summary) {
                                var total = asInt(summary.tasks_total, 0);
                                var done = asInt(summary.tasks_finished, 0);
                                var percent = total > 0 ? Math.floor((done / total) * 100) : (summary.needs_backfill ? 0 : 100);

                                return {
                                    total: total,
                                    done: done,
                                    percent: percent,
                                    text: done + ' / ' + total + ' (' + percent + '%)'
                                };
                            }

                            function summarizeTaskTotals(tasks) {
                                var migrated = 0;
                                var historical = 0;
                                var remaining = 0;
                                var hasRemaining = false;
                                var errors = 0;

                                if (!tasks || !tasks.length) {
                                    return null;
                                }

                                tasks.forEach(function (task) {
                                    var processed = asInt(task.processed_count, 0);
                                    var scanStart = asInt(task.scan_start_processed_count, 0);

                                    migrated += Math.max(0, processed - scanStart);
                                    historical += processed;
                                    errors += asInt(task.error_count, 0);

                                    if (task.remaining_count !== null && typeof task.remaining_count !== 'undefined') {
                                        hasRemaining = true;
                                        remaining += asInt(task.remaining_count, 0);
                                    }
                                });

                                return {
                                    migrated: migrated,
                                    historical: historical,
                                    remaining: hasRemaining ? String(remaining) : '—',
                                    errors: errors
                                };
                            }

                            function updateTaskRows(tasks) {
                                if (!tasks || !tasks.length) {
                                    return;
                                }

                                tasks.forEach(function (task) {
                                    var row = document.querySelector('tr[data-task-name="' + task.name + '"]');

                                    if (!row) {
                                        return;
                                    }

                                    var status = task.status || 'pending';
                                    var statusField = row.querySelector('[data-field="status"]');

                                    if (statusField) {
                                        statusField.innerHTML = '<span class="fifu-mig-status fifu-mig-status--' + status + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
                                    }

                                    setTaskField(row, 'last_id', task.last_id);
                                    setTaskField(row, 'processed', task.processed_count);
                                    setTaskField(row, 'remaining', task.remaining_count === null || typeof task.remaining_count === 'undefined' ? '—' : task.remaining_count);
                                    setTaskField(row, 'errors', task.error_count);
                                    setTaskField(row, 'updated_at', task.updated_at || '');
                                });
                            }

                            function applyMigrationPayload(payload) {
                                if (!payload || !payload.summary) {
                                    return;
                                }

                                var summary = payload.summary;
                                var progress = formatProgress(summary);
                                var currentTask = summary.current_task || 'None';
                                var lastError = summary.last_error || 'None';

                                setSummaryField('current_step', currentTask);
                                setSummaryField('progress', progress.text);
                                setSummaryField('total_errors', '0');
                                setText('#fifu-migration-page-current-task', currentTask);
                                setText('#fifu-migration-page-progress-text', progress.text);
                                setText('#fifu-migration-page-last-error', lastError);

                                var progressBar = document.getElementById('fifu-migration-page-progress-bar');

                                if (progressBar) {
                                    progressBar.max = progress.total > 0 ? progress.total : 100;
                                    progressBar.value = progress.total > 0 ? progress.done : progress.percent;
                                }

                                if (summary.paused_until) {
                                    setText('#fifu-migration-page-paused-until', summary.paused_until);
                                }

                                if (payload.tasks) {
                                    updateTaskRows(payload.tasks);

                                    var totals = summarizeTaskTotals(payload.tasks);

                                    if (totals) {
                                        setSummaryField('migrated_records', String(totals.migrated));
                                        setSummaryField('historical_processed_records', String(totals.historical));
                                        setSummaryField('remaining_records', totals.remaining);
                                        setSummaryField('total_errors', String(totals.errors));
                                    }
                                }
                            }

                            function sendControl(action, button, loop) {
                                if (!controlUrl || !token || !action) {
                                    return Promise.reject(new Error('missing_control_data'));
                                }

                                var actionLabels = {
                                    start: controls.getAttribute('data-label-starting') || '',
                                    pause: controls.getAttribute('data-label-pausing') || '',
                                    resume: controls.getAttribute('data-label-resuming') || '',
                                    retry: controls.getAttribute('data-label-retrying') || '',
                                    scan: controls.getAttribute('data-label-scanning') || ''
                                };

                                var label = actionLabels[action] || '';
                                var failed = controls.getAttribute('data-label-failed') || '';

                                if (button) {
                                    button.disabled = true;
                                }

                                setMessage(label);

                                // loop: 1 keeps the browser control loop on /migration/control.
                                return fetch(buildUrl(controlUrl, { token: token, action: action, loop: loop ? 1 : 0, details: 1, remaining: 1 }), {
                                    method: 'POST',
                                    credentials: 'same-origin'
                                }).then(function (response) {
                                    if (!response.ok) {
                                        throw new Error(failed);
                                    }

                                    return response.json();
                                });
                            }

                            function shouldContinueControlLoop(summary) {
                                if (!summary) {
                                    return false;
                                }

                                if (summary.last_error) {
                                    return false;
                                }

                                if (summary.paused_until && parseInt(summary.paused_until, 10) > Math.floor(Date.now() / 1000)) {
                                    return false;
                                }

                                if (!summary.needs_backfill) {
                                    return false;
                                }

                                if (summary.tasks_total && summary.tasks_finished >= summary.tasks_total) {
                                    return false;
                                }

                                return true;
                            }

                            function runControlLoop(action, button) {
                                var failed = controls.getAttribute('data-label-failed') || '';
                                var iterations = 0;
                                var maxIterations = 10000;

                                function step(nextAction) {
                                    iterations += 1;

                                    if (iterations > maxIterations) {
                                        throw new Error(failed);
                                    }

                                    return sendControl(nextAction, button, true).then(function (payload) {
                                        applyMigrationPayload(payload);
                                        var summary = payload && payload.summary ? payload.summary : {};

                                        if (!shouldContinueControlLoop(summary)) {
                                            setMessage('');

                                            if (button) {
                                                button.disabled = false;
                                            }

                                            return;
                                        }

                                        return new Promise(function (resolve) {
                                            window.setTimeout(resolve, 100);
                                        }).then(function () {
                                            return step('resume');
                                        });
                                    });
                                }

                                step(action).catch(function (error) {
                                    setMessage(error.message || failed);

                                    if (button) {
                                        button.disabled = false;
                                    }
                                });
                            }

                            function refreshStatus(button) {
                                if (!statusUrl || !token) {
                                    return;
                                }

                                var refreshing = controls.getAttribute('data-label-refreshing') || '';
                                var failed = controls.getAttribute('data-label-failed') || '';

                                if (button) {
                                    button.disabled = true;
                                }

                                setMessage(refreshing);

                                fetch(buildUrl(statusUrl, { token: token, details: 1, remaining: 1 }), {
                                    method: 'GET',
                                    credentials: 'same-origin'
                                }).then(function (response) {
                                    if (!response.ok) {
                                        throw new Error(failed);
                                    }

                                    return response.json();
                                }).then(function (payload) {
                                    applyMigrationPayload(payload);
                                    setMessage('');

                                    if (button) {
                                        button.disabled = false;
                                    }
                                }).catch(function (error) {
                                    setMessage(error.message || failed);

                                    if (button) {
                                        button.disabled = false;
                                    }
                                });
                            }

                            function copyDiagnostics(button) {
                                if (!diagnostics) {
                                    return;
                                }

                                var copying = controls.getAttribute('data-label-copying') || '';
                                var success = controls.getAttribute('data-label-copy-success') || '';
                                var failed = controls.getAttribute('data-label-copy-failed') || '';
                                var text = diagnostics.value || diagnostics.textContent || '';

                                if (button) {
                                    button.disabled = true;
                                }

                                setMessage(copying);

                                function complete() {
                                    setMessage(success);

                                    if (button) {
                                        button.disabled = false;
                                    }
                                }

                                function fail() {
                                    setMessage(failed);

                                    if (button) {
                                        button.disabled = false;
                                    }
                                }

                                if (navigator.clipboard && navigator.clipboard.writeText) {
                                    navigator.clipboard.writeText(text).then(complete).catch(function () {
                                        try {
                                            diagnostics.focus();
                                            diagnostics.select();
                                            if (document.execCommand('copy')) {
                                                complete();
                                                return;
                                            }
                                        } catch (error) {
                                            // Fall through to failure.
                                        }

                                        fail();
                                    });

                                    return;
                                }

                                try {
                                    diagnostics.focus();
                                    diagnostics.select();

                                    if (document.execCommand('copy')) {
                                        complete();
                                        return;
                                    }
                                } catch (error) {
                                    // Fall through to failure.
                                }

                                fail();
                            }

                            controls.addEventListener('click', function (event) {
                                var button = event.target;
                                var target = button;

                                if (!button || button.tagName !== 'BUTTON') {
                                    return;
                                }

                                var action = button.getAttribute('data-action') || '';

                                if (action === 'copy-diagnostics') {
                                    copyDiagnostics(button);
                                    return;
                                }

                                if (action === 'refresh') {
                                    refreshStatus(button);
                                    return;
                                }

                                if (action === 'pause') {
                                    sendControl(action, button, false).then(function (payload) {
                                        applyMigrationPayload(payload);
                                        setMessage('');

                                        if (button) {
                                            button.disabled = false;
                                        }
                                    }).catch(function (error) {
                                        setMessage(error.message || (controls.getAttribute('data-label-failed') || ''));

                                        if (button) {
                                            button.disabled = false;
                                        }
                                    });
                                    return;
                                }

                                if (action === 'start' || action === 'resume' || action === 'retry' || action === 'scan') {
                                    runControlLoop(action, target);
                                    // Follow-up requests use runControlLoop('resume', button).
                                }
                            });
                        })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
        <?php

        if (empty($tasks)) {
            echo '<p>' . esc_html($no_tasks_text) . '</p>';
            echo '</div>';
            return;
        }

        echo '<p>' . esc_html($status_values_hint) . '</p>';
        echo '<table class="widefat fixed striped fifu-migration-table-fixed">';
        echo '<colgroup>';
        echo '<col style="width:22%;">';
        echo '<col style="width:12%;">';
        echo '<col style="width:13%;">';
        echo '<col style="width:13%;">';
        echo '<col style="width:12%;">';
        echo '<col style="width:8%;">';
        echo '<col style="width:20%;">';
        echo '</colgroup>';
        echo '<thead><tr>';
        echo '<th>' . esc_html($step_label) . '</th>';
        echo '<th>' . esc_html($status_label) . '</th>';
        echo '<th>' . esc_html($cursor_label) . '</th>';
        echo '<th>' . esc_html($migrated_label) . '</th>';
        echo '<th>' . esc_html($remaining_label) . '</th>';
        echo '<th>' . esc_html($errors_label) . '</th>';
        echo '<th>' . esc_html($last_update_label) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($tasks as $task) {
            $task_name  = $task->get_name();
            $task_label = $task->get_label();
            $state      = $this->state->get_task_state($task_name);

            $error_count = (int) ($state['error_count'] ?? 0);
            $status = $state['status'] ?? 'pending';

            if ('finished' === $status && $error_count > 0) {
                $status = 'running';
            }

            $normalized_status = in_array($status, array('running', 'pending', 'finished'), true) ? $status : 'pending';
            $status_label    = ucfirst($normalized_status);
            $last_id         = (int) ($state['last_id'] ?? 0);
            $processed_count = (int) ($state['processed_count'] ?? 0);
            $updated_at      = (string) ($state['updated_at'] ?? '');

            echo '<tr data-task-name="' . esc_attr($task_name) . '">';
            echo '<td class="fifu-migration-step-cell">' . esc_html($task_label) . '</td>';
            echo '<td data-field="status"><span class="fifu-mig-status fifu-mig-status--' . esc_attr($normalized_status) . '">' . esc_html($status_label) . '</span></td>';
            echo '<td data-field="last_id" class="fifu-mig-num">' . esc_html($last_id) . '</td>';
            echo '<td data-field="processed" class="fifu-mig-num">' . esc_html($processed_count) . '</td>';
            echo '<td data-field="remaining" class="fifu-mig-num">&mdash;</td>';
            echo '<td data-field="errors" class="fifu-mig-num">' . esc_html($error_count) . '</td>';
            echo '<td data-field="updated_at" class="fifu-migration-mono">' . esc_html($updated_at) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}
