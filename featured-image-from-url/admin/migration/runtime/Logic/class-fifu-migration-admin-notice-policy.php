<?php

declare(strict_types=1);

if (!class_exists('Fifu_Migration_Admin_Notice_Policy')) {
    final class Fifu_Migration_Admin_Notice_Policy
    {
        public const DATE_FORMAT = 'Y-m-d H:i:s';

        public static function build_view_model(array $summary, array $labels, int $now, callable $format_date): array
        {
            $should_render = !empty($summary['needs_backfill']);

            $title = $labels['title'] ?? '';
            $current_task_label = $labels['current_task_label'] ?? '';
            $tasks_remaining_format = $labels['tasks_remaining_format'] ?? '';
            $last_run_label = $labels['last_run_label'] ?? '';
            $last_error_label = $labels['last_error_label'] ?? '';
            $paused_until_label = $labels['paused_until_label'] ?? '';
            $none_label = $labels['none_label'] ?? 'None';
            $never_label = $labels['never_label'] ?? 'Never';
            $run_now_label = $labels['run_now_label'] ?? '';
            $status_sending = $labels['status_sending'] ?? '';
            $status_sent = $labels['status_sent'] ?? '';

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
            $last_run_text = $last_run_value > 0 ? $format_date(self::DATE_FORMAT, $last_run_value) : $never_label;

            $last_error_text = trim((string) ($summary['last_error'] ?? ''));
            if ($last_error_text === '') {
                $last_error_text = $none_label;
            }

            $paused_until = (int) ($summary['paused_until'] ?? 0);
            $show_paused_until = $paused_until > $now;
            $paused_until_text = $show_paused_until ? $format_date(self::DATE_FORMAT, $paused_until) : '';

            return [
                'should_render' => $should_render,
                'title' => $title,
                'current_task_label' => $current_task_label,
                'current_task' => $current_task,
                'tasks_left' => $tasks_left,
                'tasks_remaining_text' => $tasks_remaining_text,
                'last_run_label' => $last_run_label,
                'last_run_text' => $last_run_text,
                'last_error_label' => $last_error_label,
                'last_error_text' => $last_error_text,
                'show_paused_until' => $show_paused_until,
                'paused_until' => $paused_until,
                'paused_until_label' => $paused_until_label,
                'paused_until_text' => $paused_until_text,
                'run_now_label' => $run_now_label,
                'status_sending' => $status_sending,
                'status_sent' => $status_sent,
            ];
        }
    }
}
