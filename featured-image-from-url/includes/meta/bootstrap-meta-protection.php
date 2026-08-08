<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-fifu-meta-protection-service.php';

/**
 * Bootstraps meta protection services introduced during migration.
 */
Fifu_Meta_Protection_Service::register_hooks();
