<?php

defined('ABSPATH') || exit;

/**
 * Entry point for the featured image column UI inside the WordPress admin area.
 *
 * This file bootstraps the class-based column renderer while the legacy procedural
 * hooks that were previously in `admin/column.php` are still registered.
 */

require_once __DIR__ . '/class-fifu-quick-edit-read-service.php';
require_once __DIR__ . '/class-fifu-admin-featured-image-column.php';
require_once __DIR__ . '/class-fifu-admin-featured-media-filter.php';

add_action(
    'admin_init',
    [Fifu_Admin_Featured_Media_Filter::class, 'register']
);

add_action(
    'admin_init',
    [Fifu_Admin_Featured_Image_Column::class, 'register']
);
add_action(
    'admin_head',
    [Fifu_Admin_Featured_Image_Column::class, 'enqueue_assets']
);
add_action(
    'admin_footer',
    [Fifu_Admin_Featured_Image_Column::class, 'print_footer_quick_edit_payload']
);
