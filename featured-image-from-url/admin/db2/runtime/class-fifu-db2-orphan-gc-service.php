<?php
declare(strict_types=1);

class Fifu_Db2_Orphan_Gc_Service {
    public const CRON_HOOK = 'fifu_db2_orphan_gc_cron';
    public const CRON_RECURRENCE = 'fifu_every_fifteen_minutes';
    public const OPTION_URL_CURSOR = 'fifu_db2_orphan_gc_url_cursor';
    public const OPTION_ALT_CURSOR = 'fifu_db2_orphan_gc_alt_cursor';
    public const LOCK_TRANSIENT = 'fifu_db2_orphan_gc_lock';

    private const WINDOW_SIZE = 5000;
    private const MAX_RUNTIME_SECONDS = 4;
    private const MAX_ITERATIONS = 12;
    private const LOCK_TTL_SECONDS = 300;

    private Fifu_Db2_Orphan_Gc_Repository $repository;
    private int $windowSize;
    private int $maxRuntimeSeconds;
    private int $maxIterations;
    private int $lockTtlSeconds;

    public function __construct(
        Fifu_Db2_Orphan_Gc_Repository $repository,
        int $windowSize = self::WINDOW_SIZE,
        int $maxRuntimeSeconds = self::MAX_RUNTIME_SECONDS,
        int $maxIterations = self::MAX_ITERATIONS,
        int $lockTtlSeconds = self::LOCK_TTL_SECONDS
    ) {
        $this->repository = $repository;
        $this->windowSize = $windowSize;
        $this->maxRuntimeSeconds = $maxRuntimeSeconds;
        $this->maxIterations = $maxIterations;
        $this->lockTtlSeconds = $lockTtlSeconds;

        $this->registerHooks();
    }

    public function addCronSchedule(array $schedules): array {
        if (isset($schedules[self::CRON_RECURRENCE])) {
            return $schedules;
        }

        $schedules[self::CRON_RECURRENCE] = [
            'interval' => 900,
            'display' => 'Every fifteen minutes',
        ];

        return $schedules;
    }

    public function maybeSchedule(): void {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (!$this->shouldRun()) {
            $this->unschedule();
            return;
        }

        if (wp_next_scheduled(self::CRON_HOOK) !== false) {
            return;
        }

        wp_schedule_event(time(), self::CRON_RECURRENCE, self::CRON_HOOK);
    }

    public function unschedule(): void {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            return;
        }

        if (!function_exists('wp_next_scheduled') || !function_exists('wp_unschedule_event')) {
            return;
        }

        while (($timestamp = wp_next_scheduled(self::CRON_HOOK)) !== false) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    public function disableAutomaticRuntime(): void {
        if (function_exists('remove_filter')) {
            remove_filter('cron_schedules', [$this, 'addCronSchedule']);
        }

        if (function_exists('remove_action')) {
            remove_action(self::CRON_HOOK, [$this, 'runOnce']);
            remove_action('init', [$this, 'maybeSchedule']);
        }

        $this->unschedule();
    }

    public function runOnce(bool $force = false): array {
        $stats = [
            'iterations' => 0,
            'url_windows' => 0,
            'alt_windows' => 0,
            'url_scanned' => 0,
            'alt_scanned' => 0,
            'url_deleted' => 0,
            'alt_deleted' => 0,
            'stopped_due_to_time' => false,
            'stopped_due_to_iteration_limit' => false,
        ];

        if (!$this->shouldRun()) {
            return $stats;
        }

        if (!$force && function_exists('get_transient') && get_transient(self::LOCK_TRANSIENT)) {
            return $stats;
        }

        if (function_exists('set_transient')) {
            set_transient(self::LOCK_TRANSIENT, '1', $this->lockTtlSeconds);
        }

        $startedAt = microtime(true);
        $stoppedByBreak = false;

        try {
            while ($stats['iterations'] < $this->maxIterations && (microtime(true) - $startedAt) < $this->maxRuntimeSeconds) {
                $urlStats = $this->processUrlWindow();
                $altStats = $this->processAltWindow();

                $stats['iterations']++;
                $stats['url_scanned'] += $urlStats['scanned'];
                $stats['alt_scanned'] += $altStats['scanned'];
                $stats['url_deleted'] += $urlStats['deleted'];
                $stats['alt_deleted'] += $altStats['deleted'];

                if ($urlStats['scanned'] > 0) {
                    $stats['url_windows']++;
                }

                if ($altStats['scanned'] > 0) {
                    $stats['alt_windows']++;
                }

                if ($urlStats['scanned'] === 0 && $altStats['scanned'] === 0) {
                    $stoppedByBreak = true;
                    break;
                }

                if ($urlStats['has_more'] === false && $altStats['has_more'] === false) {
                    $stoppedByBreak = true;
                    break;
                }
            }
        } finally {
            if (function_exists('delete_transient')) {
                delete_transient(self::LOCK_TRANSIENT);
            }
        }

        if (!$stoppedByBreak) {
            if ((microtime(true) - $startedAt) >= $this->maxRuntimeSeconds) {
                $stats['stopped_due_to_time'] = true;
            }

            if ($stats['iterations'] >= $this->maxIterations) {
                $stats['stopped_due_to_iteration_limit'] = true;
            }
        }

        return $stats;
    }

    private function registerHooks(): void {
        if (function_exists('add_filter')) {
            $alreadyRegistered = function_exists('has_filter') && has_filter('cron_schedules', [$this, 'addCronSchedule']);
            if (!$alreadyRegistered) {
                add_filter('cron_schedules', [$this, 'addCronSchedule']);
            }
        }

        if (function_exists('add_action')) {
            $cronRegistered = function_exists('has_action') && has_action(self::CRON_HOOK, [$this, 'runOnce']);
            if (!$cronRegistered) {
                add_action(self::CRON_HOOK, [$this, 'runOnce']);
            }

            $initRegistered = function_exists('has_action') && has_action('init', [$this, 'maybeSchedule']);
            if (!$initRegistered) {
                add_action('init', [$this, 'maybeSchedule']);
            }
        }
    }

    private function shouldRun(): bool {
        if (class_exists('Fifu_Db2_Mode', false) && Fifu_Db2_Mode::is_legacy()) {
            return false;
        }

        return $this->repository->urlTablesExist() || $this->repository->altTablesExist();
    }

    private function processUrlWindow(): array {
        if (!$this->repository->urlTablesExist()) {
            return ['scanned' => 0, 'deleted' => 0, 'has_more' => false];
        }

        $cursor = function_exists('get_option') ? (string) get_option(self::OPTION_URL_CURSOR, '') : '';
        $hashes = $this->repository->getNextUrlHashWindow($cursor, $this->windowSize);

        if ($hashes === []) {
            if ($cursor !== '' && function_exists('update_option')) {
                update_option(self::OPTION_URL_CURSOR, '');
            }

            return ['scanned' => 0, 'deleted' => 0, 'has_more' => false];
        }

        $endHash = $hashes[count($hashes) - 1];
        $scanned = count($hashes);
        $deleted = $this->repository->deleteOrphanUrlsInRange($cursor, $endHash);
        $hasMore = ($scanned === $this->windowSize);

        if (function_exists('update_option')) {
            update_option(self::OPTION_URL_CURSOR, $hasMore ? $endHash : '');
        }

        return [
            'scanned' => $scanned,
            'deleted' => $deleted,
            'has_more' => $hasMore,
        ];
    }

    private function processAltWindow(): array {
        if (!$this->repository->altTablesExist()) {
            return ['scanned' => 0, 'deleted' => 0, 'has_more' => false];
        }

        $cursor = function_exists('get_option') ? (string) get_option(self::OPTION_ALT_CURSOR, '') : '';
        $hashes = $this->repository->getNextAltHashWindow($cursor, $this->windowSize);

        if ($hashes === []) {
            if ($cursor !== '' && function_exists('update_option')) {
                update_option(self::OPTION_ALT_CURSOR, '');
            }

            return ['scanned' => 0, 'deleted' => 0, 'has_more' => false];
        }

        $endHash = $hashes[count($hashes) - 1];
        $scanned = count($hashes);
        $deleted = $this->repository->deleteOrphanAltsInRange($cursor, $endHash);
        $hasMore = ($scanned === $this->windowSize);

        if (function_exists('update_option')) {
            update_option(self::OPTION_ALT_CURSOR, $hasMore ? $endHash : '');
        }

        return [
            'scanned' => $scanned,
            'deleted' => $deleted,
            'has_more' => $hasMore,
        ];
    }
}
