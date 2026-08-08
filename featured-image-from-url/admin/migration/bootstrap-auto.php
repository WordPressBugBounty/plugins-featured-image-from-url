<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    return;
}

$migration_core_dir    = __DIR__ . '/core';
$migration_runtime_dir = __DIR__ . '/runtime';

$core_files = array(
    $migration_core_dir . '/class-fifu-migration-task-interface.php',
    $migration_core_dir . '/class-fifu-migration-state.php',
    $migration_core_dir . '/class-fifu-migration-logger.php',
    $migration_core_dir . '/class-fifu-migration-registry.php',
    $migration_core_dir . '/class-fifu-migration-runner.php',
);

foreach ($core_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

$runtime_file = $migration_runtime_dir . '/class-fifu-migration-auto-runner.php';
if (file_exists($runtime_file)) {
    require_once $runtime_file;
}

$multisite_runner_file = $migration_runtime_dir . '/class-fifu-migration-multisite-runner.php';
if (file_exists($multisite_runner_file)) {
    require_once $multisite_runner_file;
}

$tick_controller_file = $migration_runtime_dir . '/class-fifu-migration-tick-rest-controller.php';
if (file_exists($tick_controller_file)) {
    require_once $tick_controller_file;
}

if (class_exists('Fifu_Migration_Tick_Rest_Controller')) {
    Fifu_Migration_Tick_Rest_Controller::register_routes();
}

if (class_exists('Fifu_Migration_Auto_Runner')) {
    if (defined('FIFU_DISABLE_AUTO_MIGRATION') && FIFU_DISABLE_AUTO_MIGRATION) {
        Fifu_Migration_Auto_Runner::disable_automatic_runtime();
    } else {
        Fifu_Migration_Auto_Runner::register_hooks();
    }
}
