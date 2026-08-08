<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    return;
}

if (!class_exists('Fifu_Migration_Multisite_Runner')) {
    class Fifu_Migration_Multisite_Runner {
        /** Sites page size / completion scan cooldown. */
        public const PAGE_SIZE = 50;
        /** Network sweep offset (sites pagination). */
        public const CURSOR_OPTION = 'fifu_migration_multisite_offset';
        /** Sticky blog id: keep migrating this blog until it finishes. */
        public const CURRENT_BLOG_OPTION = 'fifu_migration_multisite_current_blog_id';
        /** Resume offset after CURRENT_BLOG finishes. */
        public const NEXT_OFFSET_OPTION = 'fifu_migration_multisite_next_offset';
        /** Throttle timestamp for the end-of-list completion scan. */
        public const LAST_SWEEP_TS_OPTION = 'fifu_migration_multisite_last_sweep_ts';
        /** Sites page size / completion scan cooldown. */
        public const SWEEP_COOLDOWN_SECONDS = 300;

        public static function run_once_network(bool $force = false): void {
            if (!function_exists('get_sites') ||
                !function_exists('switch_to_blog') ||
                !function_exists('restore_current_blog') ||
                !function_exists('get_blog_option') ||
                !function_exists('get_site_option') ||
                !function_exists('update_site_option') ||
                !function_exists('delete_site_option')) {
                return;
            }

            $offset = (int) get_site_option(self::CURSOR_OPTION, 0);
            $current_blog = (int) get_site_option(self::CURRENT_BLOG_OPTION, 0);
            $next_offset = (int) get_site_option(self::NEXT_OFFSET_OPTION, $offset + self::PAGE_SIZE);

            if ($current_blog > 0) {
                if (!self::is_migratable_site($current_blog)) {
                    update_site_option(self::CURSOR_OPTION, max(0, $next_offset));
                    self::clear_blog_cursor();
                    return;
                }

                self::process_current_blog($current_blog, $next_offset, $force);
                return;
            }

            $sites = self::get_sites_page($offset);

            // End of sites list: run a throttled scan to decide network completion.
            if (empty($sites)) {
                // End-of-list throttle: only run the expensive full sweep every SWEEP_COOLDOWN_SECONDS.
                $last = (int) get_site_option(self::LAST_SWEEP_TS_OPTION, 0);
                $now = time();

                if ($last > 0 && ($now - $last) < self::SWEEP_COOLDOWN_SECONDS) {
                    update_site_option(self::CURSOR_OPTION, 0);
                    return;
                }

                update_site_option(self::LAST_SWEEP_TS_OPTION, $now);

                if (!self::any_blog_needs_backfill_in_network()) {
                    self::finalize_network_completion();
                    return;
                }

                update_site_option(self::CURSOR_OPTION, 0);
                return;
            }

            foreach ($sites as $index => $site_id) {
                $blog_id = is_object($site_id) ? (int) $site_id->blog_id : (int) $site_id;

                if ($blog_id <= 0) {
                    continue;
                }

                if (!self::is_migratable_site($blog_id)) {
                    continue;
                }

                // $sites is already filtered to migratable blogs, so this offset is logical.
                $computed_next_offset = $offset + $index + 1;
                switch_to_blog($blog_id);

                try {
                    if (!Fifu_Migration_Auto_Runner::needs_backfill_for_current_blog()) {
                        continue;
                    }

                    $paused_until = (int) get_option(Fifu_Migration_Auto_Runner::OPTION_PAUSED_UNTIL, 0);
                    if ($paused_until > time()) {
                        update_site_option(self::CURSOR_OPTION, max(0, $computed_next_offset));
                        self::clear_blog_cursor();
                        return;
                    }

                    update_site_option(self::CURRENT_BLOG_OPTION, $blog_id);
                    update_site_option(self::NEXT_OFFSET_OPTION, $computed_next_offset);
                    Fifu_Migration_Auto_Runner::run_once_single_blog($force);

                    if (Fifu_Migration_Auto_Runner::needs_backfill_for_current_blog()) {
                        return;
                    }

                    update_site_option(self::CURSOR_OPTION, max(0, $computed_next_offset));
                    self::clear_blog_cursor();
                    return;
                } finally {
                    restore_current_blog();
                }
            }

            update_site_option(self::CURSOR_OPTION, $offset + self::PAGE_SIZE);
            self::clear_blog_cursor();
        }

        private static function process_current_blog(int $blog_id, int $next_offset, bool $force): void {
            switch_to_blog($blog_id);

            try {
                if (!Fifu_Migration_Auto_Runner::needs_backfill_for_current_blog()) {
                    update_site_option(self::CURSOR_OPTION, max(0, $next_offset));
                    self::clear_blog_cursor();
                    return;
                }

                $paused_until = (int) get_option(Fifu_Migration_Auto_Runner::OPTION_PAUSED_UNTIL, 0);
                if ($paused_until > time()) {
                    update_site_option(self::CURSOR_OPTION, max(0, $next_offset));
                    self::clear_blog_cursor();
                    return;
                }

                Fifu_Migration_Auto_Runner::run_once_single_blog($force);

                if (Fifu_Migration_Auto_Runner::needs_backfill_for_current_blog()) {
                    return;
                }

                update_site_option(self::CURSOR_OPTION, max(0, $next_offset));
                self::clear_blog_cursor();
            } finally {
                restore_current_blog();
            }
        }

        private static function clear_blog_cursor(): void {
            if (!function_exists('update_site_option')) {
                return;
            }

            update_site_option(self::CURRENT_BLOG_OPTION, 0);
            update_site_option(self::NEXT_OFFSET_OPTION, 0);
        }

        private static function finalize_network_completion(): void {
            $network_option = Fifu_Migration_Auto_Runner::get_network_option_name();

            if (function_exists('update_site_option')) {
                update_site_option(
                    $network_option,
                    Fifu_Migration_Auto_Runner::BACKFILL_TARGET_VERSION
                );
            }

            if (function_exists('delete_site_option')) {
                self::reset_cursor_options();
            }
        }

        private static function reset_cursor_options(): void {
            if (!function_exists('delete_site_option')) {
                return;
            }

            delete_site_option(self::CURSOR_OPTION);
            delete_site_option(self::CURRENT_BLOG_OPTION);
            delete_site_option(self::NEXT_OFFSET_OPTION);
            delete_site_option(self::LAST_SWEEP_TS_OPTION);
        }

        // Expensive scan; only used at end-of-list and throttled.
        private static function any_blog_needs_backfill_in_network(): bool {
            if (!function_exists('get_sites') ||
                !function_exists('switch_to_blog') ||
                !function_exists('restore_current_blog')) {
                return true;
            }

            $offset = 0;

            while (true) {
                $sites = self::get_active_site_ids_raw_page(200, $offset);

                if (empty($sites)) {
                    break;
                }

                foreach ($sites as $site_id) {
                    $blog_id = is_object($site_id) ? (int) $site_id->blog_id : (int) $site_id;

                    if ($blog_id <= 0) {
                        continue;
                    }

                    if (!self::is_migratable_site($blog_id)) {
                        continue;
                    }

                    switch_to_blog($blog_id);

                    try {
                        if (Fifu_Migration_Auto_Runner::needs_backfill_for_current_blog()) {
                            return true;
                        }
                    } finally {
                        restore_current_blog();
                    }
                }

                $offset += 200;
            }

            return false;
        }

        private static function get_sites_page(int $offset): array {
            return self::get_sites_page_raw(self::PAGE_SIZE, max(0, $offset));
        }

        /**
         * Return live migratable site IDs from the current network.
         *
         * The public multisite cursor is a logical offset over migratable sites only.
         * It must not count stale wp_blogs rows, deleted/archived/spam sites, or blogs
         * whose own options table is no longer readable.
         *
         * @return int[]
         */
        private static function get_sites_page_raw(int $number, int $offset): array {
            $number = max(1, $number);
            $offset = max(0, $offset);

            $sites = array();
            $skipped_migratable = 0;
            $raw_offset = 0;
            $raw_batch_size = max(200, $number * 4);

            while (true) {
                $raw_site_ids = self::get_active_site_ids_raw_page($raw_batch_size, $raw_offset);

                if (empty($raw_site_ids)) {
                    break;
                }

                foreach ($raw_site_ids as $blog_id) {
                    $blog_id = (int) $blog_id;

                    if ($blog_id <= 0) {
                        continue;
                    }

                    if (!self::is_migratable_site($blog_id)) {
                        continue;
                    }

                    if ($skipped_migratable < $offset) {
                        $skipped_migratable++;
                        continue;
                    }

                    $sites[] = $blog_id;

                    if (count($sites) >= $number) {
                        return $sites;
                    }
                }

                $raw_offset += $raw_batch_size;
            }

            return $sites;
        }

        /**
         * Return raw active site IDs from the current network.
         *
         * This method intentionally does not apply the migration usability check.
         * `get_sites_page_raw()` is responsible for filtering these raw rows into
         * the logical migratable cursor list.
         *
         * @return int[]
         */
        private static function get_active_site_ids_raw_page(int $number, int $offset): array {
            global $wpdb;

            $number = max(1, $number);
            $offset = max(0, $offset);

            if (!isset($wpdb) || !is_object($wpdb) || empty($wpdb->blogs)) {
                return self::get_sites_page_fallback($number, $offset);
            }

            $network_id = self::get_current_network_id_safe();

            if ($network_id > 0) {
                $sql = $wpdb->prepare(
                    "SELECT blog_id
                     FROM {$wpdb->blogs}
                     WHERE site_id = %d
                       AND deleted = 0
                       AND archived = 0
                       AND spam = 0
                     ORDER BY blog_id ASC
                     LIMIT %d OFFSET %d",
                    $network_id,
                    $number,
                    $offset
                );
            } else {
                $sql = $wpdb->prepare(
                    "SELECT blog_id
                     FROM {$wpdb->blogs}
                     WHERE deleted = 0
                       AND archived = 0
                       AND spam = 0
                     ORDER BY blog_id ASC
                     LIMIT %d OFFSET %d",
                    $number,
                    $offset
                );
            }

            return array_values(array_map('intval', (array) $wpdb->get_col($sql)));
        }

        /**
         * Fallback only for unusual environments where $wpdb->blogs is unavailable.
         *
         * @return int[]
         */
        private static function get_sites_page_fallback(int $number, int $offset): array {
            if (!function_exists('get_sites')) {
                return array();
            }

            $args = array(
                'fields' => 'ids',
                'number' => max(1, $number),
                'offset' => max(0, $offset),
                'deleted' => 0,
                'archived' => 0,
                'spam' => 0,
                'orderby' => 'id',
                'order' => 'ASC',
                'cache_results' => false,
                'update_site_cache' => false,
                'update_site_meta_cache' => false,
                'no_found_rows' => true,
            );

            $network_id = self::get_current_network_id_safe();
            if ($network_id > 0) {
                $args['network_id'] = $network_id;
            }

            return array_values(array_map('intval', (array) get_sites($args)));
        }

        private static function get_current_network_id_safe(): int {
            if (function_exists('get_current_network_id')) {
                return (int) get_current_network_id();
            }

            if (function_exists('get_current_site')) {
                $network = get_current_site();
                if (is_object($network) && isset($network->id)) {
                    return (int) $network->id;
                }
            }

            global $wpdb;
            if (isset($wpdb) && is_object($wpdb) && isset($wpdb->siteid)) {
                return (int) $wpdb->siteid;
            }

            return 0;
        }

        private static function is_migratable_site(int $blog_id): bool {
            if (!self::is_active_site_row($blog_id)) {
                return false;
            }

            return self::blog_has_readable_options_table($blog_id);
        }

        private static function is_active_site_row(int $blog_id): bool {
            if ($blog_id <= 0) {
                return false;
            }

            global $wpdb;

            if (!isset($wpdb) || !is_object($wpdb) || empty($wpdb->blogs)) {
                return self::is_active_site_fallback($blog_id);
            }

            $network_id = self::get_current_network_id_safe();

            if ($network_id > 0) {
                $sql = $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$wpdb->blogs}
                     WHERE blog_id = %d
                       AND site_id = %d
                       AND deleted = 0
                       AND archived = 0
                       AND spam = 0",
                    $blog_id,
                    $network_id
                );
            } else {
                $sql = $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$wpdb->blogs}
                     WHERE blog_id = %d
                       AND deleted = 0
                       AND archived = 0
                       AND spam = 0",
                    $blog_id
                );
            }

            return (int) $wpdb->get_var($sql) > 0;
        }

        private static function is_active_site_fallback(int $blog_id): bool {
            if ($blog_id <= 0 || !function_exists('get_blog_details')) {
                return false;
            }

            $site = get_blog_details($blog_id, false);
            if (!$site) {
                return false;
            }

            return empty($site->deleted) && empty($site->archived) && empty($site->spam);
        }

        private static function blog_has_readable_options_table(int $blog_id): bool {
            if ($blog_id <= 0) {
                return false;
            }

            global $wpdb;

            if (!isset($wpdb) ||
                !is_object($wpdb) ||
                !method_exists($wpdb, 'get_blog_prefix') ||
                !method_exists($wpdb, 'get_var') ||
                !method_exists($wpdb, 'prepare')) {
                return true;
            }

            $table = $wpdb->get_blog_prefix($blog_id) . 'options';

            if (!is_string($table) || '' === $table) {
                return false;
            }

            $table = '`' . str_replace('`', '``', $table) . '`';

            $previous_suppress = false;
            if (method_exists($wpdb, 'suppress_errors')) {
                $previous_suppress = (bool) $wpdb->suppress_errors(true);
            }

            try {
                $siteurl = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1",
                        'siteurl'
                    )
                );
            } finally {
                if (method_exists($wpdb, 'suppress_errors')) {
                    $wpdb->suppress_errors($previous_suppress);
                }
            }

            return is_string($siteurl) && '' !== trim($siteurl);
        }
    }
}
