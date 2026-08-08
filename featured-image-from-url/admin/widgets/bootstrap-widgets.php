<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers FIFU widgets and their admin UI assets.
 */
final class Fifu_Widgets_Registrar
{
    /**
     * Registers all FIFU widgets with WordPress.
     */
    public static function register(): void
    {
        register_widget(Fifu_Widget_Image::class);
    }

    /**
     * Prints the custom widget icon CSS on the widgets admin screen.
     */
    public static function render_admin_icon_css(): void
    {
        echo
        '<style>
            *[id*="fifu_widget_"] > div.widget-top > div.widget-title > h3:before {
                font-family: "dashicons";
                content: "\f306";
                width:18px;
                float:left;
                height:6px;
                font-size:15px;
            }
        </style>';
    }
}

add_action('widgets_init', [Fifu_Widgets_Registrar::class, 'register']);
add_action('admin_head-widgets.php', [Fifu_Widgets_Registrar::class, 'render_admin_icon_css']);
