<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Bootstrap file for external media auto-detect hooks.
 */

require_once __DIR__ . '/class-fifu-external-media-auto-detect-service.php';

Fifu_External_Media_Auto_Detect_Service::register_hooks();
