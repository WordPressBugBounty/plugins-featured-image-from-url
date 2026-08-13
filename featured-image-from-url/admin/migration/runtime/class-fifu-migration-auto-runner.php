<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    return;
}

if (!class_exists('Fifu_Migration_Auto_Runner')) {
    class Fifu_Migration_Auto_Runner {
        /**
         * Increment this each time a migration task changes so older installs rerun the backfill.
         */
        public const BACKFILL_TARGET_VERSION = 1;
        public const OPTION_BACKFILL_VERSION = 'fifu_db2_backfill_version';
        public const OPTION_TOKEN = 'fifu_migration_token';
        public const OPTION_LAST_RUN = 'fifu_migration_last_run';
        public const OPTION_LAST_ERROR = 'fifu_migration_last_error';
        public const OPTION_ERROR_COUNT = 'fifu_migration_error_count';
        public const OPTION_PAUSED_UNTIL = 'fifu_migration_paused_until';
        public const OPTION_LAST_TICK_ATTEMPT = 'fifu_migration_last_tick_attempt';
        public const LOCK_TRANSIENT = 'fifu_migration_lock';
        public const CRON_HOOK = 'fifu_migration_cron';

        public const BATCH_LIMIT = 250;
        public const BATCH_TIME_SECONDS = 2;
        public const MIN_SECONDS_BETWEEN_RUN = 30;
        public const LOCK_TTL_SECONDS = 120;

        private const TICK_ROUTE = FIFU_REST_NAMESPACE_V2 . '/migration/tick';
        private const NETWORK_OPTION_BACKFILL_VERSION = 'fifu_db2_backfill_network_version';
        private const MAX_ERROR_LENGTH = 500;
        private const BACKOFF_FIRST = 300;
        private const BACKOFF_SECOND = 900;
        private const BACKOFF_SUBSEQUENT = 3600;
        private const TASK_BATCH_LIMITS = array(
            'featured' => 1000,
        );

        public static function register_hooks(): void {
            if (!function_exists('add_filter') || !function_exists('add_action')) {
                return;
            }

            if (function_exists('has_filter')) {
                if (has_filter('cron_schedules', array(__CLASS__, 'add_cron_schedule')) === false) {
                    add_filter('cron_schedules', array(__CLASS__, 'add_cron_schedule'));
                }
            } else {
                add_filter('cron_schedules', array(__CLASS__, 'add_cron_schedule'));
            }

            if (function_exists('has_action')) {
                if (has_action(self::CRON_HOOK, array(__CLASS__, 'run_once')) === false) {
                    add_action(self::CRON_HOOK, array(__CLASS__, 'run_once'));
                }

                if (has_action('init', array(__CLASS__, 'maybe_schedule')) === false) {
                    add_action('init', array(__CLASS__, 'maybe_schedule'));
                }

                if (has_action('shutdown', array(__CLASS__, 'maybe_fire_async_tick')) === false) {
                    add_action('shutdown', array(__CLASS__, 'maybe_fire_async_tick'), 1);
                }
            } else {
                add_action(self::CRON_HOOK, array(__CLASS__, 'run_once'));
                add_action('init', array(__CLASS__, 'maybe_schedule'));
                add_action('shutdown', array(__CLASS__, 'maybe_fire_async_tick'), 1);
            }
        }

        public static function disable_automatic_runtime(): void {
            if (function_exists('remove_filter')) {
                remove_filter('cron_schedules', array(__CLASS__, 'add_cron_schedule'));
            }

            if (function_exists('remove_action')) {
                remove_action(self::CRON_HOOK, array(__CLASS__, 'run_once'));
                remove_action('init', array(__CLASS__, 'maybe_schedule'));
                remove_action('shutdown', array(__CLASS__, 'maybe_fire_async_tick'), 1);
            }

            self::unschedule();
        }

        public static function maybe_schedule(): void {
            if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
                return;
            }

            if (self::is_network_mode()) {
                if (!function_exists('get_current_blog_id') || !function_exists('get_main_site_id')) {
                    return;
                }

                if ((int) get_current_blog_id() !== (int) get_main_site_id()) {
                    return;
                }

                if (!self::needs_backfill_network()) {
                    self::unschedule();
                    return;
                }
            } elseif (!self::needs_backfill()) {
                self::unschedule();
                return;
            }

            self::ensure_token();

            if (!wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time(), 'fifu_every_minute', self::CRON_HOOK);
            }
        }

        public static function run_once(bool $force = false): void {
            if (!self::has_base_capabilities()) {
                return;
            }

            if (self::is_network_mode()) {
                if (!function_exists('get_current_blog_id') || !function_exists('get_main_site_id')) {
                    return;
                }

                if ((int) get_current_blog_id() !== (int) get_main_site_id()) {
                    return;
                }

                if (!self::needs_backfill_network()) {
                    self::unschedule();
                    return;
                }

                if (class_exists('Fifu_Migration_Multisite_Runner')) {
                    Fifu_Migration_Multisite_Runner::run_once_network($force);
                }

                return;
            }

            self::run_once_single_blog($force);
        }

        public static function run_once_single_blog(bool $force = false): void {
            if (!self::has_single_blog_capabilities()) {
                return;
            }

            if (!self::needs_backfill()) {
                self::mark_complete();
                return;
            }

            if (self::is_paused()) {
                return;
            }

            $now = time();

            if (!$force) {
                $last_run = (int) get_option(self::OPTION_LAST_RUN, 0);

                if ($last_run > 0 && ($now - $last_run) < self::MIN_SECONDS_BETWEEN_RUN) {
                    return;
                }
            }

            if (get_transient(self::LOCK_TRANSIENT)) {
                return;
            }

            set_transient(self::LOCK_TRANSIENT, '1', self::LOCK_TTL_SECONDS);

            try {
                if (!class_exists('Fifu_Migration_Registry') ||
                    !class_exists('Fifu_Migration_State') ||
                    !class_exists('Fifu_Migration_Runner')) {
                    return;
                }

                $registry = new Fifu_Migration_Registry();
                $state = new Fifu_Migration_State();
                $task_name = self::get_pending_task($registry, $state);

                if (null === $task_name) {
                    self::finalize_migration($now);
                    return;
                }

                $runner = new Fifu_Migration_Runner($state, null, $registry);
                $runner->run_task_batch(
                    $task_name,
                    self::get_batch_limit_for_task($task_name),
                    self::BATCH_TIME_SECONDS
                );

                update_option(self::OPTION_LAST_RUN, $now);
                $has_task_errors = self::has_unresolved_task_errors($registry, $state);

                if (!$has_task_errors) {
                    self::reset_error_state();
                }

                if (self::is_all_tasks_finished($registry, $state)) {
                    self::finalize_migration($now);
                }
            } catch (\Throwable $throwable) {
                self::handle_run_exception($throwable, $now);
            } finally {
                delete_transient(self::LOCK_TRANSIENT);
            }
        }

        public static function maybe_fire_async_tick(bool $force = false): void {
            if (!function_exists('get_option') ||
                !function_exists('rest_url') ||
                !function_exists('wp_remote_post') ||
                !function_exists('add_query_arg') ||
                !function_exists('apply_filters')) {
                return;
            }

            if (self::is_network_mode()) {
                if (!function_exists('get_current_blog_id') || !function_exists('get_main_site_id')) {
                    return;
                }

                if ((int) get_current_blog_id() !== (int) get_main_site_id()) {
                    return;
                }

                if (!self::needs_backfill_network()) {
                    return;
                }
            } elseif (!self::needs_backfill() || self::is_paused()) {
                return;
            }

            $now = time();
            $last_attempt = (int) get_option(self::OPTION_LAST_TICK_ATTEMPT, 0);

            if (!$force && $last_attempt > 0 && ($now - $last_attempt) < self::MIN_SECONDS_BETWEEN_RUN) {
                return;
            }

            update_option(self::OPTION_LAST_TICK_ATTEMPT, $now);

            $token = self::get_token();

            if ('' === $token) {
                return;
            }

            $base_url = rest_url(self::TICK_ROUTE);

            if (!is_string($base_url) || '' === $base_url) {
                return;
            }

            $tick_url = add_query_arg('token', $token, $base_url);

            wp_remote_post(
                $tick_url,
                array(
                    'timeout'   => 0.01,
                    'blocking'  => false,
                    'sslverify' => apply_filters('https_local_ssl_verify', false),
                )
            );
        }

        public static function get_progress_summary(): array {
            $summary = self::get_default_summary();

            if (!function_exists('get_option')) {
                return $summary;
            }

            $network_mode = self::is_network_mode();
            $summary['mode'] = $network_mode ? 'network' : 'single';

            if ($network_mode) {
                $summary['needs_backfill'] = self::needs_backfill_network();
                $summary['backfill_version'] = (int) get_site_option(self::NETWORK_OPTION_BACKFILL_VERSION, 0);

                if (class_exists('Fifu_Migration_Multisite_Runner')) {
                    $summary['network_current_blog_id'] = (int) get_site_option(Fifu_Migration_Multisite_Runner::CURRENT_BLOG_OPTION, 0);
                    $summary['network_cursor_offset'] = (int) get_site_option(Fifu_Migration_Multisite_Runner::CURSOR_OPTION, 0);
                } else {
                    $summary['network_current_blog_id'] = (int) get_site_option('fifu_migration_multisite_current_blog_id', 0);
                    $summary['network_cursor_offset'] = (int) get_site_option('fifu_migration_multisite_offset', 0);
                }
            } else {
                $summary['needs_backfill'] = self::needs_backfill_for_current_blog();
                $summary['backfill_version'] = (int) get_option(self::OPTION_BACKFILL_VERSION, 0);
            }

            $summary['last_error'] = (string) get_option(self::OPTION_LAST_ERROR, '');
            $summary['paused_until'] = (int) get_option(self::OPTION_PAUSED_UNTIL, 0);
            $summary['last_run'] = (int) get_option(self::OPTION_LAST_RUN, 0);
            $summary['last_tick_attempt'] = (int) get_option(self::OPTION_LAST_TICK_ATTEMPT, 0);

            if (!class_exists('Fifu_Migration_Registry') || !class_exists('Fifu_Migration_State')) {
                return $summary;
            }

            $registry = new Fifu_Migration_Registry();
            $state = new Fifu_Migration_State();
            $tasks = $registry->get_all_tasks();
            $summary['tasks_total'] = count($tasks);

            foreach ($tasks as $task) {
                $task_name = $task->get_name();
                $task_state = $state->get_task_state($task_name);
                $error_count = (int) ($task_state['error_count'] ?? 0);
                $status = $task_state['status'] ?? '';
                $effectively_finished = self::is_task_effectively_finished($task_state);

                if ($effectively_finished) {
                    $summary['tasks_finished']++;
                    continue;
                }

                if (null === $summary['current_task']) {
                    $summary['current_task'] = $task_name;
                }

                if ($error_count > 0) {
                    $summary['needs_backfill'] = true;
                }
            }

            return $summary;
        }

        public static function get_token(): string {
            return self::ensure_token();
        }

        private static function get_default_summary(): array {
            return array(
                'needs_backfill'      => false,
                'backfill_version'     => 0,
                'target_version'       => self::get_target_version(),
                'tasks_total'          => 0,
                'tasks_finished'       => 0,
                'current_task'         => null,
                'last_error'           => '',
                'paused_until'         => 0,
                'last_run'             => 0,
                'last_tick_attempt'    => 0,
                'mode'                 => 'single',
                'network_current_blog_id' => 0,
                'network_cursor_offset' => 0,
            );
        }

        private static function has_base_capabilities(): bool {
            return function_exists('get_option') && function_exists('update_option');
        }

        private static function has_single_blog_capabilities(): bool {
            return self::has_base_capabilities() &&
                function_exists('get_transient') &&
                function_exists('set_transient') &&
                function_exists('delete_transient');
        }

        public static function needs_backfill_network(): bool {
            if (!function_exists('get_site_option')) {
                return false;
            }

            $version = (int) get_site_option(self::NETWORK_OPTION_BACKFILL_VERSION, 0);
            return $version < self::BACKFILL_TARGET_VERSION;
        }

        public static function get_network_option_name(): string {
            return self::NETWORK_OPTION_BACKFILL_VERSION;
        }

        public static function get_target_version(): int {
            return self::BACKFILL_TARGET_VERSION;
        }

        private static function is_network_mode(): bool {
            if (!function_exists('is_multisite') || !is_multisite()) {
                return false;
            }

            return self::is_network_active();
        }

        private static function is_network_active(): bool {
            $basename = self::resolve_plugin_basename();

            if ($basename === '') {
                return false;
            }

            if (function_exists('is_plugin_active_for_network')) {
                return is_plugin_active_for_network($basename);
            }

            if (!function_exists('get_site_option')) {
                return false;
            }

            $active = get_site_option('active_sitewide_plugins', array());

            if (!is_array($active)) {
                return false;
            }

            return isset($active[$basename]);
        }

        private static function resolve_plugin_basename(): string {
            if (defined('FIFU_PLUGIN_BASENAME') && is_string(FIFU_PLUGIN_BASENAME) && FIFU_PLUGIN_BASENAME !== '') {
                return FIFU_PLUGIN_BASENAME;
            }

            if (defined('FIFU_PLUGIN_FILE') && function_exists('plugin_basename')) {
                return plugin_basename(FIFU_PLUGIN_FILE);
            }

            return '';
        }

        private static function reset_error_state(): void {
            update_option(self::OPTION_LAST_ERROR, '');
            update_option(self::OPTION_ERROR_COUNT, 0);
            update_option(self::OPTION_PAUSED_UNTIL, 0);
        }

        private static function mark_complete(): void {
            self::finalize_migration(time());
        }

        private static function finalize_migration(int $now): void {
            update_option(self::OPTION_LAST_RUN, $now);
            update_option(self::OPTION_BACKFILL_VERSION, self::BACKFILL_TARGET_VERSION);
            self::reset_error_state();
            self::unschedule();
        }

        private static function get_batch_limit_for_task(string $task_name): int {
            return self::TASK_BATCH_LIMITS[$task_name] ?? self::BATCH_LIMIT;
        }

        private static function handle_run_exception(\Throwable $throwable, int $now): void {
            $message = self::normalize_error_message($throwable);
            update_option(self::OPTION_LAST_ERROR, $message);
            $errors = (int) get_option(self::OPTION_ERROR_COUNT, 0);
            $errors++;
            update_option(self::OPTION_ERROR_COUNT, $errors);
            $backoff = self::calculate_backoff_seconds($errors);
            update_option(self::OPTION_PAUSED_UNTIL, $now + $backoff);
        }

        private static function normalize_error_message(\Throwable $throwable): string {
            $message = $throwable->getMessage();
            $message = trim($message);

            if ('' === $message) {
                $message = 'Migration batch failed.';
            }

            if (strlen($message) > self::MAX_ERROR_LENGTH) {
                $message = substr($message, 0, self::MAX_ERROR_LENGTH);
            }

            return $message;
        }

        private static function calculate_backoff_seconds(int $errors): int {
            if ($errors <= 1) {
                return self::BACKOFF_FIRST;
            }

            if (2 === $errors) {
                return self::BACKOFF_SECOND;
            }

            return self::BACKOFF_SUBSEQUENT;
        }

        private static function is_paused(): bool {
            $paused_until = (int) get_option(self::OPTION_PAUSED_UNTIL, 0);
            return $paused_until > time();
        }

        private static function get_pending_task(Fifu_Migration_Registry $registry, Fifu_Migration_State $state): ?string {
            foreach ($registry->get_all_tasks() as $task) {
                $task_name = $task->get_name();
                $task_state = $state->get_task_state($task_name);

                if (self::is_task_effectively_finished($task_state)) {
                    continue;
                }

                return $task_name;
            }

            return null;
        }

        private static function is_all_tasks_finished(Fifu_Migration_Registry $registry, Fifu_Migration_State $state): bool {
            foreach ($registry->get_all_tasks() as $task) {
                $task_name = $task->get_name();
                $task_state = $state->get_task_state($task_name);

                if (!self::is_task_effectively_finished($task_state)) {
                    return false;
                }
            }

            return true;
        }

        private static function needs_backfill(): bool {
            if (!function_exists('get_option')) {
                return false;
            }

            $version = (int) get_option(self::OPTION_BACKFILL_VERSION, 0);
            if ($version < self::BACKFILL_TARGET_VERSION) {
                return true;
            }

            return self::has_unresolved_task_errors();
        }

        public static function needs_backfill_for_current_blog(): bool {
            return self::needs_backfill();
        }

        private static function is_task_effectively_finished(array $task_state): bool {
            return isset($task_state['status']) &&
                'finished' === $task_state['status'] &&
                (int) ($task_state['error_count'] ?? 0) === 0;
        }

        private static function has_unresolved_task_errors(?Fifu_Migration_Registry $registry = null, ?Fifu_Migration_State $state = null): bool {
            if (!class_exists('Fifu_Migration_Registry') || !class_exists('Fifu_Migration_State')) {
                return false;
            }

            $registry = $registry ?? new Fifu_Migration_Registry();
            $state = $state ?? new Fifu_Migration_State();

            foreach ($registry->get_all_tasks() as $task) {
                $task_name = $task->get_name();
                $task_state = $state->get_task_state($task_name);

                if ((int) ($task_state['error_count'] ?? 0) > 0) {
                    return true;
                }
            }

            return false;
        }

        private static function unschedule(): void {
            if (function_exists('wp_clear_scheduled_hook')) {
                wp_clear_scheduled_hook(self::CRON_HOOK);
                return;
            }

            if (!function_exists('wp_next_scheduled') || !function_exists('wp_unschedule_event')) {
                return;
            }

            while ($timestamp = wp_next_scheduled(self::CRON_HOOK)) {
                wp_unschedule_event($timestamp, self::CRON_HOOK);
            }
        }

        public static function add_cron_schedule($schedules) {
            if (!is_array($schedules)) {
                return $schedules;
            }

            if (!isset($schedules['fifu_every_minute'])) {
                $schedules['fifu_every_minute'] = array(
                    'interval' => 60,
                    'display' => 'Every minute',
                );
            }

            return $schedules;
        }

        private static function ensure_token(): string {
            if (!function_exists('get_option') || !function_exists('update_option')) {
                return '';
            }

            $token = get_option(self::OPTION_TOKEN, '');

            if (!is_string($token) || '' === $token) {
                if (function_exists('wp_generate_password')) {
                    $token = wp_generate_password(32, false, false);
                } elseif (function_exists('random_bytes')) {
                    $token = bin2hex(random_bytes(32));
                } elseif (function_exists('openssl_random_pseudo_bytes')) {
                    $bytes = openssl_random_pseudo_bytes(32);
                    if ($bytes !== false) {
                        $token = bin2hex($bytes);
                    } else {
                        $token = hash('sha256', mt_rand() . microtime(true));
                    }
                } else {
                    $token = hash('sha256', mt_rand() . microtime(true));
                }

                update_option(self::OPTION_TOKEN, $token);
            }

            return $token;
        }
    }
}
