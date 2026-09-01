<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Fifu_Manual_Metain_Busy_Exception', false)) {
    class Fifu_Manual_Metain_Busy_Exception extends RuntimeException {}
}

final class Fifu_Manual_Metain_Service
{
    private const STATE_OPTION = 'fifu_manual_metain_state';
    private const TOTAL_OPTION = 'fifu_manual_metain_total';
    private const STATES = ['idle', 'running', 'paused', 'complete'];

    public static function start(): array
    {
        self::acquire_processing_lock();
        try {
            if (get_option('fifu_manual_metaout_state', 'idle') === 'running') {
                throw new Fifu_Manual_Metain_Busy_Exception('Manual metadata cleanup is busy.');
            }
            $repository = new Fifu_Metadata_Queue_Repository();
            $stats = new Fifu_Migration_Stats();
            $state = self::state();
            $rows = $repository->get_meta_in();
            $storedTotal = max(0, (int) get_option(self::TOTAL_OPTION, 0));

            if (in_array($state, ['running', 'paused'], true) && $rows !== []) {
                $remaining = max(0, $stats->count_meta_in_operations());
                if ($remaining > $storedTotal) {
                    $storedTotal = $remaining;
                    self::persist(self::TOTAL_OPTION, $storedTotal);
                }
                self::persist(self::STATE_OPTION, 'running');
                self::sync_counter($remaining);
                return self::status();
            }

            if ($rows === []) {
                Fifu_Metadata_Import_Service::prepare_meta_in_queue('', false);
            }
            $remaining = max(0, $stats->count_meta_in_operations());
            self::persist(self::TOTAL_OPTION, $remaining);
            self::persist(self::STATE_OPTION, $remaining > 0 ? 'running' : 'complete');
            self::sync_counter($remaining);
            return self::status();
        } finally {
            self::release_processing_lock();
        }
    }

    public static function pause(): array
    {
        $remaining = max(0, (new Fifu_Migration_Stats())->count_meta_in_operations());
        self::persist(self::STATE_OPTION, $remaining > 0 ? 'paused' : 'complete');
        self::sync_counter($remaining);
        return self::status();
    }

    public static function process_next_batch(): array
    {
        if (filter_var(get_option('fifu_fake_stop', false), FILTER_VALIDATE_BOOLEAN)) {
            return self::pause();
        }
        if (self::state() !== 'running') {
            return self::status();
        }
        self::acquire_processing_lock();
        try {
            if (get_option('fifu_manual_metaout_state', 'idle') === 'running') {
                throw new Fifu_Manual_Metain_Busy_Exception('Manual metadata cleanup is busy.');
            }
            $repository = new Fifu_Metadata_Queue_Repository();
            $rows = $repository->get_meta_in();
            $id = self::row_id($rows[0] ?? null);
            if ($id === null) {
                self::persist(self::STATE_OPTION, 'complete');
                self::sync_counter(0);
                return self::status();
            }
            $type = $repository->get_meta_in_type($id);
            if ($type === 'post') {
                Fifu_Metadata_Import_Service::process_post_meta_in_row($id);
            } elseif ($type === 'term') {
                Fifu_Metadata_Import_Service::process_term_meta_in_row($id);
            } else {
                $repository->delete_meta_in_row($id);
            }
            $remaining = max(0, (new Fifu_Migration_Stats())->count_meta_in_operations());
            self::sync_counter($remaining);
            if ($remaining === 0) {
                self::persist(self::STATE_OPTION, 'complete');
            }
            return self::status();
        } finally {
            self::release_processing_lock();
        }
    }

    public static function status(): array
    {
        $state = self::state();
        $remaining = max(0, (new Fifu_Migration_Stats())->count_meta_in_operations());
        $total = max(0, (int) get_option(self::TOTAL_OPTION, 0));
        if ($remaining > $total) {
            $total = $remaining;
            self::persist(self::TOTAL_OPTION, $total);
        }
        $processed = max(0, $total - $remaining);
        $done = $state === 'complete' && $remaining === 0;
        $percent = $done ? 100 : ($total <= 0 ? 0 : min(99, max(0, (int) floor($processed * 100 / $total))));
        $nextId = null;
        foreach ((new Fifu_Metadata_Queue_Repository())->get_meta_in() as $row) {
            $nextId = self::row_id($row);
            if ($nextId !== null) {
                break;
            }
        }
        return ['success' => true, 'state' => $state, 'running' => $state === 'running', 'done' => $done, 'total' => $total, 'remaining' => $remaining, 'processed' => $processed, 'percent' => $percent, 'next_id' => $nextId];
    }

    private static function state(): string
    {
        $state = get_option(self::STATE_OPTION, 'idle');
        return in_array($state, self::STATES, true) ? $state : 'idle';
    }

    private static function persist(string $name, $value): void
    {
        update_option($name, $value, false);
    }

    private static function sync_counter(int $remaining): void
    {
        Fifu_Transient_Manager::set('fifu_metadata_counter', max(0, $remaining), 0);
    }

    private static function processing_lock_name(): string
    {
        global $wpdb;
        return 'fifu_metadata_' . substr(md5(DB_NAME . ':' . $wpdb->prefix), 0, 32);
    }

    private static function acquire_processing_lock(): void
    {
        global $wpdb;
        $name = self::processing_lock_name();
        $result = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $name));
        if ($result === 1 || $result === '1') {
            return;
        }
        if ($result === 0 || $result === '0') {
            throw new Fifu_Manual_Metain_Busy_Exception('Manual metadata operation is busy.');
        }
        throw new RuntimeException('Unable to acquire manual metadata lock.');
    }

    private static function release_processing_lock(): void
    {
        global $wpdb;
        $name = self::processing_lock_name();
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
    }

    private static function row_id($row): ?int
    {
        $value = is_object($row) ? ($row->post_id ?? null) : (is_array($row) ? ($row['post_id'] ?? null) : null);
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
