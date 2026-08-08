<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers structured data hooks introduced during the FIFU migration.
 */

// Legacy: fifu_render_structured_data hooked via wp_head.
add_action(
    'wp_head',
    [Fifu_Structured_Data_Renderer::class, 'render_current_page']
);
