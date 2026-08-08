<?php

declare(strict_types=1);

/**
 * Bootstrap for FIFU migration admin UI.
 *
 * This file wires the migration admin page into the FIFU menu while keeping
 * all migration logic inside the admin/migration directory.
 */

if (!defined('ABSPATH')) {
    return;
}

$migration_core_dir = FIFU_ADMIN_DIR . '/migration/core';
$migration_ui_dir   = FIFU_ADMIN_DIR . '/migration/ui';

$core_files = array(
    $migration_core_dir . '/class-fifu-migration-task-interface.php',
    $migration_core_dir . '/class-fifu-migration-state.php',
    $migration_core_dir . '/class-fifu-migration-logger.php',
    $migration_core_dir . '/class-fifu-migration-registry.php',
    $migration_core_dir . '/class-fifu-migration-runner.php',
    $migration_core_dir . '/class-fifu-migration-stats.php',
);

foreach ($core_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

$admin_page_file = $migration_ui_dir . '/class-fifu-migration-admin-page.php';
if (file_exists($admin_page_file)) {
    require_once $admin_page_file;
}

if (!function_exists('fifu_migration_admin_menu')) {
    /**
     * Adds the FIFU Migration submenu to the admin menu.
     */
    function fifu_migration_admin_menu() {
        if (is_network_admin()) {
            return;
        }

        if (!Fifu_Options_Utils::is_on('fifu_debug')) {
            return;
        }

        add_submenu_page(
            FIFU_SLUG,
            'DB Migration',
            'DB Migration',
            'manage_options',
            'fifu-migration',
            'fifu_migration_admin_page'
        );
    }
}

if (!function_exists('fifu_migration_admin_page')) {
    /**
     * Renders the FIFU Migration admin page.
     */
    function fifu_migration_admin_page() {
        if (!class_exists('Fifu_Migration_Admin_Page')) {
            echo '<div class="wrap"><h1>DB Migration</h1><p>Migration module is not available.</p></div>';
            return;
        }

        $page = new Fifu_Migration_Admin_Page();
        $page->render_page();
    }
}

if (function_exists('add_action')) {
    add_action('admin_menu', 'fifu_migration_admin_menu', 20);
}
