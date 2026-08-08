<?php

declare(strict_types=1);

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Bootstraps the generic `wp fifu` command and delegates execution to `Fifu_CLI_Command`.
 */
require_once __DIR__ . '/class-fifu-cli-command.php';

\WP_CLI::add_command('fifu', Fifu_CLI_Command::class);
