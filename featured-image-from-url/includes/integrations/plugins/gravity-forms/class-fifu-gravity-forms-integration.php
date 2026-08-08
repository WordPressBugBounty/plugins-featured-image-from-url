<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Gravity_Forms_Integration
{
    public static function init(): void
    {
        if (!self::is_active()) {
            return;
        }

        add_action('gform_loaded', [self::class, 'load_addon'], 5);
    }

    private static function is_active(): bool
    {
        if (
            class_exists('Fifu_Plugin_Detector')
            && method_exists('Fifu_Plugin_Detector', 'is_gravity_forms_active')
        ) {
            return Fifu_Plugin_Detector::is_gravity_forms_active();
        }

        return class_exists('GFForms');
    }

    public static function load_addon(): void
    {
        if (!method_exists('GFForms', 'include_addon_framework')) {
            return;
        }

        GFForms::include_addon_framework();

        require_once __DIR__ . '/class-fifu-gravity-forms-addon.php';

        GFAddOn::register(Fifu_Gravity_Forms_Addon::class);
    }
}
