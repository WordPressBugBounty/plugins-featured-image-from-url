<?php

defined( 'ABSPATH' ) || exit;

// Bootstrap file for FIFU CDN-related services.

$includes = dirname( __DIR__, 2 );
$url_dir = dirname( __DIR__ );

if ( ! class_exists( 'Fifu_Image_Url_Utils', false ) ) {
    require_once $url_dir . '/class-fifu-image-url-utils.php';
}
if ( ! class_exists( 'Fifu_Options_Utils', false ) ) {
    require_once $includes . '/compat/class-fifu-options-utils.php';
}
if ( ! class_exists( 'Fifu_Generic_Utils', false ) ) {
    require_once $includes . '/compat/class-fifu-generic-utils.php';
}
if ( ! class_exists( 'Fifu_Post_Meta_Utils', false ) ) {
    require_once $includes . '/meta/class-fifu-post-meta-utils.php';
}

require_once __DIR__ . '/class-fifu-cdn-signature-service.php';
require_once __DIR__ . '/class-fifu-image-cdn-resize-service.php';
require_once __DIR__ . '/class-fifu-image-size-usage-tracker.php';
require_once __DIR__ . '/class-fifu-speedup-url-service.php';
require_once __DIR__ . '/class-fifu-cdn-thumbnail-resolver.php';
require_once __DIR__ . '/class-fifu-jetpack-cdn-service.php';
require_once __DIR__ . '/class-fifu-pubcdn-url-service.php';

add_filter(
    'image_downsize',
    array( Fifu_Image_Cdn_Resize_Service::class, 'filter_image_downsize' ),
    10,
    3
);

add_filter(
    'image_downsize',
    array( Fifu_Image_Size_Usage_Tracker::class, 'track_usage' ),
    10,
    3
);

add_filter(
    'jetpack_photon_skip_image',
    array( Fifu_Jetpack_Cdn_Service::class, 'filter_jetpack_photon_skip_image' ),
    10,
    3
);

// TODO: Orchestrate any additional CDN initialization hooks here once stats or other services expand.
