<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    return;
}

if (!class_exists('Fifu_Migration_Tick_Rest_Controller')) {
    class Fifu_Migration_Tick_Rest_Controller {
        public const REST_NAMESPACE = FIFU_REST_NAMESPACE_V2;
        public const TICK_ROUTE = '/migration/tick';
        public const STATUS_ROUTE = '/migration/status';
        public const RESET_ROUTE = '/migration/reset';
        public const CONTROL_ROUTE = '/migration/control';
        private const REMAINING_CACHE_TTL = 30;

        public static function register_routes(): void {
            if (!function_exists('add_action')) {
                return;
            }

            add_action('rest_api_init', array(__CLASS__, 'register_internal_routes'));
        }

        public static function register_internal_routes(): void {
            if (!function_exists('register_rest_route') || !class_exists('WP_REST_Server')) {
                return;
            }

            register_rest_route(
                self::REST_NAMESPACE,
                self::TICK_ROUTE,
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'permission_callback' => '__return_true',
                    'callback'            => array(__CLASS__, 'handle_tick'),
                )
            );

            register_rest_route(
                self::REST_NAMESPACE,
                self::STATUS_ROUTE,
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'permission_callback' => '__return_true',
                    'callback'            => array(__CLASS__, 'handle_status'),
                )
            );

            register_rest_route(
                self::REST_NAMESPACE,
                self::RESET_ROUTE,
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'permission_callback' => '__return_true',
                    'callback'            => array(__CLASS__, 'handle_reset'),
                )
            );

            register_rest_route(
                self::REST_NAMESPACE,
                self::CONTROL_ROUTE,
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'permission_callback' => '__return_true',
                    'callback'            => array(__CLASS__, 'handle_control'),
                )
            );
        }

        public static function handle_tick(WP_REST_Request $request): WP_REST_Response {
            if (!class_exists('Fifu_Migration_Auto_Runner')) {
                return self::respond_service_unavailable();
            }

            if (!self::validate_token($request)) {
                return self::respond_forbidden();
            }

            $summary = Fifu_Migration_Auto_Runner::get_progress_summary();
            $paused_until = (int) ($summary['paused_until'] ?? 0);

            if ($paused_until > time()) {
                return self::create_paused_response($paused_until);
            }

            $force = self::is_truthy_param($request->get_param('force'));
            if ($force && !self::force_is_allowed()) {
                $force = false;
            }

            Fifu_Migration_Auto_Runner::run_once($force);

            return new WP_REST_Response(array(
                'ok'      => true,
                'summary' => Fifu_Migration_Auto_Runner::get_progress_summary(),
            ), 200);
        }

        public static function handle_status(WP_REST_Request $request): WP_REST_Response {
            if (!class_exists('Fifu_Migration_Auto_Runner')) {
                return self::respond_service_unavailable();
            }

            if (!self::validate_token($request)) {
                return self::respond_forbidden();
            }

            $response = array(
                'ok'      => true,
                'summary' => Fifu_Migration_Auto_Runner::get_progress_summary(),
            );

            if (self::should_include_details($request)) {
                $response['tasks'] = self::get_tasks_payload(
                    self::should_include_remaining_counts($request)
                );
            }

            return new WP_REST_Response($response, 200);
        }

        public static function handle_reset(WP_REST_Request $request): WP_REST_Response {
            if (!class_exists('Fifu_Migration_Auto_Runner')) {
                return self::respond_service_unavailable();
            }

            if (!self::validate_token($request)) {
                return self::respond_forbidden();
            }

            $network_reset = self::is_truthy_param($request->get_param('network'));

            if ($network_reset) {
                if (!self::network_reset_is_allowed()) {
                    return self::respond_forbidden();
                }
            } elseif (!self::reset_is_allowed()) {
                return self::respond_forbidden();
            }

            self::reset_migration_state($network_reset);

            return new WP_REST_Response(array(
                'ok'      => true,
                'network_reset' => $network_reset,
                'summary' => Fifu_Migration_Auto_Runner::get_progress_summary(),
            ), 200);
        }

        public static function handle_control(WP_REST_Request $request): WP_REST_Response {
            if (!class_exists('Fifu_Migration_Auto_Runner')) {
                return self::respond_service_unavailable();
            }

            if (!self::validate_token($request)) {
                return self::respond_forbidden();
            }

            $action = self::normalize_control_action($request->get_param('action'));

            if (!in_array($action, array('start', 'pause', 'resume', 'retry', 'scan'), true)) {
                return new WP_REST_Response(array(
                    'ok'    => false,
                    'error' => 'invalid_action',
                ), 400);
            }

            $loop_request = self::is_loop_request($request);

            if ('pause' === $action) {
                $pause_seconds = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600;
                self::set_paused_until(time() + $pause_seconds);
            } elseif ('resume' === $action) {
                self::set_paused_until(0);
                self::run_forced_batch_and_continue(!$loop_request);
            } elseif ('start' === $action) {
                self::set_paused_until(0);
                self::run_forced_batch_and_continue(!$loop_request);
            } elseif ('retry' === $action) {
                self::clear_retry_state();
                self::clear_task_error_states();
                self::run_forced_batch_and_continue(!$loop_request);
            } elseif ('scan' === $action) {
                self::prepare_incremental_scan();
                self::reopen_task_states_for_scan();

                if (!$loop_request) {
                    self::run_forced_batch_and_continue(true);
                } else {
                    Fifu_Migration_Auto_Runner::maybe_schedule();
                }
            }

            $summary = Fifu_Migration_Auto_Runner::get_progress_summary();

            if ('scan' === $action && $loop_request) {
                $summary['tasks_finished'] = 0;
                $summary['needs_backfill'] = true;
            }

            $response = array(
                'ok'      => true,
                'action'  => $action,
                'summary' => $summary,
            );

            if (self::should_include_details($request)) {
                $response['tasks'] = self::get_tasks_payload(
                    self::should_include_remaining_counts($request)
                );
            }

            return new WP_REST_Response($response, 200);
        }

        private static function validate_token(WP_REST_Request $request): bool {
            $token = self::get_token_from_request($request);

            if ($token === '') {
                return false;
            }

            $expected = Fifu_Migration_Auto_Runner::get_token();

            if ($expected === '') {
                return false;
            }

            return self::token_matches($expected, $token);
        }

        private static function normalize_control_action($action): string {
            if (!is_string($action)) {
                return '';
            }

            $action = strtolower(trim($action));

            if (function_exists('sanitize_key')) {
                return sanitize_key($action);
            }

            return preg_replace('/[^a-z0-9_\-]/', '', $action) ?: '';
        }

        private static function is_loop_request(WP_REST_Request $request): bool {
            return self::is_truthy_param($request->get_param('loop'));
        }

        private static function set_paused_until(int $paused_until): void {
            if (!function_exists('update_option')) {
                return;
            }

            update_option(Fifu_Migration_Auto_Runner::OPTION_PAUSED_UNTIL, $paused_until);
        }

        private static function run_forced_batch_and_continue(bool $fire_async_tick = true): void {
            Fifu_Migration_Auto_Runner::run_once(true);
            Fifu_Migration_Auto_Runner::maybe_schedule();

            if ($fire_async_tick) {
                Fifu_Migration_Auto_Runner::maybe_fire_async_tick(true);
            }
        }

        private static function clear_retry_state(): void {
            if (!function_exists('update_option')) {
                return;
            }

            update_option(Fifu_Migration_Auto_Runner::OPTION_LAST_ERROR, '');
            update_option(Fifu_Migration_Auto_Runner::OPTION_ERROR_COUNT, 0);
            update_option(Fifu_Migration_Auto_Runner::OPTION_PAUSED_UNTIL, 0);
        }

        private static function prepare_incremental_scan(): void {
            if (!function_exists('update_option')) {
                return;
            }

            update_option(Fifu_Migration_Auto_Runner::OPTION_PAUSED_UNTIL, 0);
            update_option(Fifu_Migration_Auto_Runner::OPTION_BACKFILL_VERSION, 0);
        }

        private static function clear_task_error_states(): void {
            if (!class_exists('Fifu_Migration_Registry') || !class_exists('Fifu_Migration_State')) {
                return;
            }

            $registry = new Fifu_Migration_Registry();
            $state = new Fifu_Migration_State();

            foreach ($registry->get_all_tasks() as $task) {
                if (!is_object($task) || !method_exists($task, 'get_name')) {
                    continue;
                }

                $task_name = (string) $task->get_name();
                $task_state = $state->get_task_state($task_name);

                if ((int) ($task_state['error_count'] ?? 0) <= 0) {
                    continue;
                }

                $state->update_task_state($task_name, array(
                    'error_count' => 0,
                    'status'      => 'running',
                ));
            }
        }

        private static function reopen_task_states_for_scan(): void {
            if (!class_exists('Fifu_Migration_Registry') || !class_exists('Fifu_Migration_State')) {
                return;
            }

            $registry = new Fifu_Migration_Registry();
            $state = new Fifu_Migration_State();

            foreach ($registry->get_all_tasks() as $task) {
                if (!is_object($task) || !method_exists($task, 'get_name')) {
                    continue;
                }

                $task_name = (string) $task->get_name();
                $task_state = $state->get_task_state($task_name);
                $task_status = (string) ($task_state['status'] ?? '');

                if ($task_status !== 'finished') {
                    continue;
                }

                $state->update_task_state($task_name, array(
                    'status'                     => 'pending',
                    'error_count'                => 0,
                    'scan_start_processed_count' => (int) ($task_state['processed_count'] ?? 0),
                ));
            }
        }

        private static function get_token_from_request(WP_REST_Request $request): string {
            $header = $request->get_header('x-fifu-token');

            if (is_string($header) && $header !== '') {
                return $header;
            }

            $param = $request->get_param('token');

            if (is_string($param) && $param !== '') {
                return $param;
            }

            return '';
        }

        private static function token_matches(string $expected, string $provided): bool {
            if (function_exists('hash_equals')) {
                return hash_equals($expected, $provided);
            }

            return self::constant_time_equals($expected, $provided);
        }

        private static function constant_time_equals(string $expected, string $provided): bool {
            if (strlen($expected) !== strlen($provided)) {
                return false;
            }

            $result = 0;

            for ($i = 0, $length = strlen($expected); $i < $length; $i++) {
                $result |= ord($expected[$i]) ^ ord($provided[$i]);
            }

            return $result === 0;
        }

        private static function respond_forbidden(): WP_REST_Response {
            return new WP_REST_Response(array('ok' => false, 'error' => 'forbidden'), 403);
        }

        private static function respond_service_unavailable(): WP_REST_Response {
            return new WP_REST_Response(array('ok' => false, 'error' => 'service_unavailable'), 503);
        }

        private static function create_paused_response(int $paused_until): WP_REST_Response {
            return new WP_REST_Response(array(
                'ok'           => true,
                'paused'       => true,
                'paused_until' => $paused_until,
            ), 200);
        }

        private static function reset_migration_state(bool $network_reset = false): void {
            if (!function_exists('update_option') || !function_exists('delete_option') || !function_exists('delete_transient')) {
                return;
            }

            if ($network_reset) {
                self::reset_network_state();
                return;
            }

            self::reset_blog_state();
        }

        private static function reset_blog_state(): void {
            update_option(Fifu_Migration_Auto_Runner::OPTION_BACKFILL_VERSION, 0, false);
            delete_option('fifu_migration_state');
            delete_option(Fifu_Migration_Auto_Runner::OPTION_LAST_RUN);
            delete_option(Fifu_Migration_Auto_Runner::OPTION_LAST_TICK_ATTEMPT);
            delete_option(Fifu_Migration_Auto_Runner::OPTION_PAUSED_UNTIL);
            delete_option(Fifu_Migration_Auto_Runner::OPTION_LAST_ERROR);
            delete_option(Fifu_Migration_Auto_Runner::OPTION_ERROR_COUNT);
            delete_transient(Fifu_Migration_Auto_Runner::LOCK_TRANSIENT);
        }

        private static function reset_network_state(): void {
            if (!function_exists('update_site_option') || !function_exists('delete_site_option')) {
                return;
            }

            update_site_option(Fifu_Migration_Auto_Runner::get_network_option_name(), 0);

            if (class_exists('Fifu_Migration_Multisite_Runner')) {
                delete_site_option(Fifu_Migration_Multisite_Runner::CURSOR_OPTION);
                delete_site_option(Fifu_Migration_Multisite_Runner::CURRENT_BLOG_OPTION);
                delete_site_option(Fifu_Migration_Multisite_Runner::NEXT_OFFSET_OPTION);
                delete_site_option(Fifu_Migration_Multisite_Runner::LAST_SWEEP_TS_OPTION);
            } else {
                // Compatibility fallback in case load order changes.
                delete_site_option('fifu_migration_multisite_offset');
                delete_site_option('fifu_migration_multisite_current_blog_id');
                delete_site_option('fifu_migration_multisite_next_offset');
                delete_site_option('fifu_migration_multisite_last_sweep_ts');
            }

            if (function_exists('get_main_site_id') &&
                function_exists('switch_to_blog') &&
                function_exists('restore_current_blog')) {
                $main_site_id = (int) get_main_site_id();

                if ($main_site_id > 0) {
                    switch_to_blog($main_site_id);

                    try {
                        self::reset_blog_state();
                    } finally {
                        restore_current_blog();
                    }
                    return;
                }
            }

            self::reset_blog_state();
        }

        private static function reset_is_allowed(): bool {
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                return false;
            }

            if (!function_exists('is_user_logged_in') || !function_exists('current_user_can')) {
                return false;
            }

            if (!is_user_logged_in()) {
                return false;
            }

            return current_user_can('manage_options');
        }

        private static function network_reset_is_allowed(): bool {
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                return false;
            }

            if (!function_exists('is_multisite') || !is_multisite()) {
                return false;
            }

            if (!function_exists('is_user_logged_in') || !function_exists('current_user_can')) {
                return false;
            }

            if (!is_user_logged_in()) {
                return false;
            }

            return current_user_can('manage_network_options');
        }

        private static function should_include_details(WP_REST_Request $request): bool {
            return self::is_truthy_param($request->get_param('details'));
        }

        private static function should_include_remaining_counts(WP_REST_Request $request): bool {
            $remaining = $request->get_param('remaining');

            if (null === $remaining) {
                return true;
            }

            return self::is_truthy_param($remaining);
        }

        private static function get_tasks_payload(bool $include_remaining_counts = true): array {
            if (!class_exists('Fifu_Migration_Registry') || !class_exists('Fifu_Migration_State')) {
                return array();
            }

            global $wpdb;

            if (!isset($wpdb) || !$wpdb instanceof \wpdb) {
                return array();
            }

            $registry = new Fifu_Migration_Registry();
            $state = new Fifu_Migration_State();
            $tasks = array();

            foreach ($registry->get_all_tasks() as $task) {
                $name = (string) $task->get_name();
                $task_state = $state->get_task_state($name);
                $raw_status = isset($task_state['status']) ? (string) $task_state['status'] : 'pending';
                $last_id = isset($task_state['last_id']) ? (int) $task_state['last_id'] : 0;
                $processed_count = isset($task_state['processed_count']) ? (int) $task_state['processed_count'] : 0;
                $scan_start_processed_count = array_key_exists('scan_start_processed_count', $task_state) && is_numeric($task_state['scan_start_processed_count'])
                    ? (int) $task_state['scan_start_processed_count']
                    : 0;
                $error_count = isset($task_state['error_count']) ? (int) $task_state['error_count'] : 0;
                $updated_at = isset($task_state['updated_at']) ? (string) $task_state['updated_at'] : '';
                $effectively_finished = 'finished' === $raw_status && 0 === $error_count;
                $status = $raw_status;

                if ('finished' === $raw_status && $error_count > 0) {
                    $status = 'running';
                }

                $task_payload = array(
                    'name'            => $name,
                    'label'           => (string) $task->get_label(),
                    'status'          => $status,
                    'last_id'         => $last_id,
                    'processed_count' => $processed_count,
                    'scan_start_processed_count' => $scan_start_processed_count,
                    'error_count'     => $error_count,
                    'updated_at'      => $updated_at,
                );

                if ($include_remaining_counts) {
                    if ($effectively_finished) {
                        $remaining = 0;
                    } elseif ($error_count > 0) {
                        $remaining = null;
                    } else {
                        $remaining = self::get_task_remaining_count($name, $last_id, $wpdb);
                    }

                    $task_payload['remaining_count'] = $remaining;
                }

                $tasks[] = $task_payload;
            }

            return $tasks;
        }

        private static function get_task_remaining_count(string $task_name, int $last_id, \wpdb $wpdb): ?int {
            $config = self::get_task_meta_config();

            if (!isset($config[$task_name])) {
                return null;
            }

            $task_config = $config[$task_name];
            $cache_key = 'fifu_mig_remaining_' . self::get_task_cache_key($task_name);
            $now = time();
            $cached = function_exists('get_transient') ? get_transient($cache_key) : null;

            if (is_array($cached) && isset($cached['last_id'], $cached['remaining'], $cached['ts'])) {
                if ((int) $cached['last_id'] === $last_id && ($now - (int) $cached['ts']) <= self::REMAINING_CACHE_TTL) {
                    return (int) $cached['remaining'];
                }
            }

            $type = isset($task_config['type']) ? $task_config['type'] : 'default';
            if ($type === 'mixed') {
                $remaining = 0;
                $post_table = $wpdb->postmeta ?? '';
                $term_table = $wpdb->termmeta ?? '';
                $post_meta_key = $task_config['postmeta_key'] ?? '';
                $term_meta_key = $task_config['termmeta_key'] ?? '';
                $post_compare = $task_config['post_compare'] ?? $task_config['compare'] ?? '=';
                $term_compare = $task_config['term_compare'] ?? $task_config['compare'] ?? '=';
                if ($post_table !== '' && $post_meta_key !== '') {
                    $remaining += self::query_remaining_count($wpdb, $post_table, $last_id, $post_meta_key, $post_compare);
                }
                if ($term_table !== '' && $term_meta_key !== '') {
                    $remaining += self::query_remaining_count($wpdb, $term_table, $last_id, $term_meta_key, $term_compare);
                }
            } else {
                $table_alias = $task_config['table'] ?? 'postmeta';
                $table_name = '';
                if ($table_alias === 'termmeta') {
                    $table_name = $wpdb->termmeta ?? '';
                } else {
                    $table_name = $wpdb->postmeta ?? '';
                }

                if ($table_name === '') {
                    return null;
                }

                $remaining = self::query_remaining_count(
                    $wpdb,
                    $table_name,
                    $last_id,
                    $task_config['meta_key'] ?? '',
                    $task_config['compare'] ?? '='
                );
            }

            if (function_exists('set_transient')) {
                set_transient($cache_key, array(
                    'last_id'   => $last_id,
                    'remaining' => $remaining,
                    'ts'        => $now,
                ), self::REMAINING_CACHE_TTL);
            }

            return $remaining;
        }

        private static function query_remaining_count(\wpdb $wpdb, string $table_name, int $last_id, string $meta_key, string $comparison): int {
            if ($table_name === '') {
                return 0;
            }

            $comparison = strtolower($comparison);

            if ($meta_key === '') {
                return 0;
            }

            if ($comparison === 'like') {
                $query = $wpdb->prepare(
                    "SELECT COUNT(1) FROM {$table_name} WHERE meta_id > %d AND meta_key LIKE %s",
                    $last_id,
                    $meta_key
                );
            } elseif ($comparison === 'in') {
                $keys = array_filter(
                    array_map('trim', explode(',', $meta_key)),
                    static fn($value): bool => $value !== ''
                );

                if (empty($keys)) {
                    return 0;
                }

                $placeholders = implode(', ', array_fill(0, count($keys), '%s'));
                $args = array_merge(array($last_id), $keys);

                $query = $wpdb->prepare(
                    "SELECT COUNT(1) FROM {$table_name} WHERE meta_id > %d AND meta_key IN ({$placeholders})",
                    ...$args
                );
            } else {
                $query = $wpdb->prepare(
                    "SELECT COUNT(1) FROM {$table_name} WHERE meta_id > %d AND meta_key = %s",
                    $last_id,
                    $meta_key
                );
            }

            $count = $wpdb->get_var($query);

            return max(0, (int) $count);
        }

        private static function get_task_meta_config(): array {
            return array(
                'featured'       => array('table' => 'postmeta', 'compare' => '=', 'meta_key' => 'fifu_image_url'),
                'alt_featured'   => array('table' => 'postmeta', 'compare' => '=', 'meta_key' => 'fifu_image_alt'),
                'category_image' => array('table' => 'termmeta', 'compare' => '=', 'meta_key' => 'fifu_image_url'),
            );
        }

        private static function get_task_cache_key(string $task_name): string {
            if (function_exists('sanitize_key')) {
                $key = sanitize_key($task_name);
            } else {
                $key = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $task_name));
            }

            if ($key === '') {
                return 'task_' . md5($task_name);
            }

            return $key;
        }

        private static function is_truthy_param($value): bool {
            return $value === '1' || $value === 1 || $value === true || $value === 'true';
        }

        private static function force_is_allowed(): bool {
            if (!function_exists('is_user_logged_in') || !function_exists('current_user_can')) {
                return false;
            }

            if (!is_user_logged_in()) {
                return false;
            }

            return current_user_can('manage_options');
        }
    }
}
