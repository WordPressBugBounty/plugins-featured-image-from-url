<?php
declare(strict_types=1);

/**
 * Bootstrap for FIFU migration WP-CLI commands.
 *
 * This file is included from the main plugin file while running under WP-CLI.
 * It loads the migration engine and registers the "wp fifu-migrate" commands.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
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
    $migration_core_dir . '/class-fifu-schema-manager.php',
);

foreach ( $core_files as $file ) {
    if ( file_exists( $file ) ) {
        require_once $file;
    }
}

$cli_file = $migration_ui_dir . '/class-fifu-migration-cli.php';
if ( file_exists( $cli_file ) ) {
    require_once $cli_file;
}

if ( class_exists( 'Fifu_Migration_CLI' ) ) {
    Fifu_Migration_CLI::register_commands();
}
