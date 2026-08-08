<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-fifu-attachment-image-src-filter.php';

/**
 * Bootstrap file for registering attachment-related filters and actions.
 */

// Legacy attachment file replacement behavior that now lives in Fifu_Featured_Image_Filter.
add_filter(
    'get_attached_file',
    [Fifu_Attachment_File_Filters::class, 'filter_get_attached_file'],
    10,
    2
);

// Legacy attachment URL replacement behavior that now lives in Fifu_Featured_Image_Filter.
add_filter(
    'wp_get_attachment_url',
    [Fifu_Attachment_File_Filters::class, 'filter_attachment_url'],
    10,
    2
);

// Legacy: admin_post_thumbnail_html hook.
add_filter(
    'admin_post_thumbnail_html',
    [Fifu_Attachment_Compat_Filters::class, 'passthrough_admin_post_thumbnail_html'],
    10,
    3
);

// Legacy: woocommerce_single_product_image_thumbnail_html hook.
add_filter(
    'woocommerce_single_product_image_thumbnail_html',
    [Fifu_Attachment_Compat_Filters::class, 'passthrough_woocommerce_thumbnail_html'],
    10,
    2
);

// Legacy: wp_get_attachment_metadata hook.
add_filter(
    'wp_get_attachment_metadata',
    [Fifu_Attachment_Compat_Filters::class, 'passthrough_attachment_metadata'],
    10,
    2
);

// Legacy: wp_get_attachment_image hook.
add_filter(
    'wp_get_attachment_image',
    [Fifu_Attachment_Compat_Filters::class, 'passthrough_attachment_image'],
    10,
    5
);

// Legacy: wp_calculate_image_sizes hook.
add_filter(
    'wp_calculate_image_sizes',
    [Fifu_Attachment_Compat_Filters::class, 'passthrough_calculate_image_sizes'],
    10,
    3
);

// Legacy: wp_calculate_image_srcset hook.
add_filter(
    'wp_calculate_image_srcset',
    [Fifu_Attachment_Compat_Filters::class, 'passthrough_calculate_image_srcset'],
    10,
    5
);

// Legacy: wp_calculate_image_srcset_meta hook.
add_filter(
    'wp_calculate_image_srcset_meta',
    [Fifu_Attachment_Compat_Filters::class, 'passthrough_calculate_image_srcset_meta'],
    10,
    4
);

// Legacy: max_srcset_image_width hook.
add_filter(
    'max_srcset_image_width',
    [Fifu_Attachment_Compat_Filters::class, 'passthrough_max_srcset_image_width'],
    10,
    2
);

// Legacy: posts_where filters for media library and admin lists.
add_filter(
    'posts_where',
    [Fifu_Attachment_Query_Filters::class, 'filter_media_library_where']
);
add_filter(
    'posts_where',
    [Fifu_Attachment_Query_Filters::class, 'filter_admin_attachment_list_where'],
    10,
    2
);

// Legacy: facetwp_filtered_post_ids handling via fifu_add_parameters_single_post.
add_filter(
    'facetwp_filtered_post_ids',
    [Fifu_Local_Media_Renderer::class, 'register_for_facetwp'],
    10,
    2
);

// Legacy: wp_footer attachment/export rendering.

// Legacy: wp_ajax_get-attachment handler.
add_action(
    'wp_ajax_get-attachment',
    [Fifu_Attachment_Ajax_Filters::class, 'intercept_get_attachment'],
    0
);

// Legacy: fifu_get_photon_url customization.
add_filter(
    'wp_get_attachment_image_src',
    [Fifu_Attachment_Image_Src_Filter::class, 'filter_attachment_image_src'],
    10,
    3
);
