<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    return;
}

if (!class_exists('Fifu_Migration_Admin_Notice')) {
    class Fifu_Migration_Admin_Notice {
        public static function register_hooks(): void {
            if (!function_exists('add_action')) {
                return;
            }

            add_action('admin_notices', array(__CLASS__, 'render_notice'));
        }

        public static function render_notice(): void {
            if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
                return;
            }

            if (!class_exists('Fifu_Migration_Auto_Runner') || !class_exists('Fifu_Migration_Strings')) {
                return;
            }

            $summary = Fifu_Migration_Auto_Runner::get_progress_summary();

            if (empty($summary['needs_backfill'])) {
                return;
            }

            $strings = Fifu_Migration_Strings::get_admin_notice_strings();
            $notice_strings = $strings['notice'] ?? array();

            $title = isset($notice_strings['title']) ? (string) $notice_strings['title']() : '';
            $current_task_label = isset($notice_strings['current_task_label']) ? (string) $notice_strings['current_task_label']() : '';
            $tasks_remaining_format = isset($notice_strings['tasks_remaining']) ? (string) $notice_strings['tasks_remaining']() : '';
            $last_run_label = isset($notice_strings['last_run_label']) ? (string) $notice_strings['last_run_label']() : '';
            $last_error_label = isset($notice_strings['last_error_label']) ? (string) $notice_strings['last_error_label']() : '';
            $paused_until_label = isset($notice_strings['paused_until_label']) ? (string) $notice_strings['paused_until_label']() : '';
            $none_label = isset($notice_strings['none']) ? (string) $notice_strings['none']() : 'None';
            $never_label = isset($notice_strings['never']) ? (string) $notice_strings['never']() : 'Never';
            $run_now_label = isset($notice_strings['run_now']) ? (string) $notice_strings['run_now']() : 'Run now';
            $status_sending = isset($notice_strings['status_sending']) ? (string) $notice_strings['status_sending']() : '';
            $status_sent = isset($notice_strings['status_sent']) ? (string) $notice_strings['status_sent']() : '';

            $current_task = $summary['current_task'] ?? '';
            $current_task = '' === $current_task ? $none_label : $current_task;

            $tasks_total = (int) ($summary['tasks_total'] ?? 0);
            $tasks_finished = (int) ($summary['tasks_finished'] ?? 0);
            $tasks_left = max(0, $tasks_total - $tasks_finished);
            $tasks_remaining_text = '';

            if ($tasks_remaining_format !== '') {
                $tasks_remaining_text = sprintf($tasks_remaining_format, $tasks_left);
            }

            $last_run_value = (int) ($summary['last_run'] ?? 0);
            $last_run_text = $last_run_value > 0 ? date_i18n('Y-m-d H:i:s', $last_run_value) : $never_label;

            $last_error_text = trim((string) ($summary['last_error'] ?? ''));
            if ($last_error_text === '') {
                $last_error_text = $none_label;
            }

            $token = Fifu_Migration_Auto_Runner::get_token();
            if ($token === '' || !function_exists('rest_url')) {
                return;
            }

            $tick_url = rest_url(FIFU_REST_NAMESPACE_V2 . '/migration/tick');
            ?>
            <div class="notice notice-info">
                <p><strong><?php echo esc_html($title); ?></strong></p>
                <p>
                    <strong><?php echo esc_html($current_task_label); ?>:</strong>
                    <span><?php echo esc_html($current_task); ?></span>
                    <?php if ($tasks_remaining_text !== '') : ?>
                        &mdash; <?php echo esc_html($tasks_remaining_text); ?>
                    <?php endif; ?>
                </p>
                <p>
                    <strong><?php echo esc_html($last_run_label); ?>:</strong>
                    <span><?php echo esc_html($last_run_text); ?></span>
                    &bull;
                    <strong><?php echo esc_html($last_error_label); ?>:</strong>
                    <span><?php echo esc_html($last_error_text); ?></span>
                </p>
                <?php $paused_until = (int) ($summary['paused_until'] ?? 0); ?>
                <?php if ($paused_until > time()) : ?>
                    <p>
                        <strong><?php echo esc_html($paused_until_label); ?>:</strong>
                        <span><?php echo esc_html(date_i18n('Y-m-d H:i:s', $paused_until)); ?></span>
                    </p>
                <?php endif; ?>
                <p>
                    <button type="button" class="button button-primary" id="fifu-migration-run-now"
                        data-url="<?php echo esc_attr($tick_url); ?>"
                        data-token="<?php echo esc_attr($token); ?>"
                        data-status-sending="<?php echo esc_attr($status_sending); ?>"
                        data-status-sent="<?php echo esc_attr($status_sent); ?>">
                        <?php echo esc_html($run_now_label); ?>
                    </button>
                    <span id="fifu-migration-run-status" aria-live="polite" style="margin-left:10px;"></span>
                </p>
            </div>
            <script>
                (function () {
                    if (typeof window.fifuMigrationRunNow !== 'undefined') {
                        return;
                    }

                    window.fifuMigrationRunNow = true;

                    var button = document.getElementById('fifu-migration-run-now');
                    var status = document.getElementById('fifu-migration-run-status');

                    if (!button || !window.fetch) {
                        return;
                    }

                    var tickUrl = button.getAttribute('data-url');
                    var token = button.getAttribute('data-token');
                    var sendingLabel = button.getAttribute('data-status-sending');
                    var sentLabel = button.getAttribute('data-status-sent');
                    var attempts = 5;
                    var delay = 500;

                    function fire(remaining) {
                        if (remaining <= 0) {
                            if (status && sentLabel) {
                                status.textContent = sentLabel;
                            }
                            return;
                        }

                        if (status && sendingLabel) {
                            status.textContent = sendingLabel;
                        }

                        fetch(tickUrl + '?token=' + encodeURIComponent(token), {
                            method: 'POST',
                            credentials: 'same-origin'
                        }).finally(function () {
                            setTimeout(function () {
                                fire(remaining - 1);
                            }, delay);
                        });
                    }

                    button.addEventListener('click', function () {
                        fire(attempts);
                    });
                })();
            </script>
            <?php
        }
    }
}
