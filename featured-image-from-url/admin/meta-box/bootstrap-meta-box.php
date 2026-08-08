<?php

if (!defined('ABSPATH')) {
    exit;
}

$manager_file = FIFU_ADMIN_DIR . '/meta-box/class-fifu-admin-meta-box-manager.php';
$renderer_file = FIFU_ADMIN_DIR . '/meta-box/class-fifu-admin-meta-box-renderer.php';

if (file_exists($manager_file)) {
    require_once $manager_file;
}

if (file_exists($renderer_file)) {
    require_once $renderer_file;
}

require_once FIFU_ADMIN_DIR . '/meta-box/class-fifu-admin-product-category-fields.php';

add_action(
    'add_meta_boxes',
    [ Fifu_Admin_Meta_Box_Manager::class, 'register_meta_boxes' ]
);
add_action(
    'add_meta_boxes',
    [ Fifu_Admin_Meta_Box_Manager::class, 'remove_native_metaboxes' ],
    50
);
add_action(
    'add_meta_boxes',
    [ Fifu_Admin_Meta_Box_Manager::class, 'enqueue_editor_styles' ]
);
add_filter(
    'admin_body_class',
    [ Fifu_Admin_Meta_Box_Manager::class, 'filter_admin_body_class' ]
);
add_action(
    'admin_enqueue_scripts',
    [ Fifu_Admin_Meta_Box_Manager::class, 'enqueue_assets' ]
);
add_action(
    'enqueue_block_editor_assets',
    [ Fifu_Admin_Meta_Box_Manager::class, 'enqueue_block_editor_assets' ]
);
add_action(
    'save_post',
    [Fifu_Post_Save_Service::class, 'on_save_post'],
    10,
    3
);

Fifu_Admin_Product_Category_Fields::register_hooks();
