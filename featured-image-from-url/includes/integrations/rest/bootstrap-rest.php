<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', static function (): void {
    // Public WordPress/WooCommerce REST compatibility is a PRO feature.
    // The free version keeps only FIFU internal REST routes.
    Fifu_Rest_Routing_Integration::register_hooks();
});
