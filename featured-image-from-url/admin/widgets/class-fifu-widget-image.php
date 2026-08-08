<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('fifu_main_image_url')) {
    function fifu_main_image_url(int $post_id, bool $front = false)
    {
        return Fifu_Post_Main_Image_Resolver::get_main_image_url($post_id, $front);
    }
}

if (!function_exists('fifu_process_external_url')) {
    function fifu_process_external_url(string $url, int $attachment_id, $size = null): string
    {
        return Fifu_Local_Media_Renderer::enrich_attachment_url($url, $attachment_id, $size);
    }
}

final class Fifu_Widget_Image extends WP_Widget
{
    public function __construct()
    {
        $fifu = Fifu_Widget_Strings::get_strings();
        parent::__construct(
            'fifu_widget_image',
            '(FIFU) ' . $fifu['title']['media'](),
            ['description' => $fifu['description']['media']()]
        );
    }

    public function widget($args, $instance)
    {
        if (isset($args['before_widget'])) {
            echo $args['before_widget'];
        }

        global $post;

        $post_id = 0;
        if (isset($post) && is_object($post) && !empty($post->ID)) {
            $post_id = (int) $post->ID;
        } elseif (function_exists('get_the_ID')) {
            $post_id = (int) get_the_ID();
        }

        if ($post_id <= 0) {
            if (isset($args['after_widget'])) {
                echo $args['after_widget'];
            }
            return;
        }

        $url = trim((string) fifu_main_image_url($post_id, true));
        if ($url === '') {
            if (isset($args['after_widget'])) {
                echo $args['after_widget'];
            }
            return;
        }

        self::enrich_attachment_url($url, get_post_thumbnail_id($post_id), null);

        echo '<img src="' . self::escape_url($url) . '">';
        if (isset($args['after_widget'])) {
            echo $args['after_widget'];
        }
    }

    public function form($instance)
    {
        include FIFU_ADMIN_DIR . '/widgets/html/widget-image.php';
    }

    public function update($new_instance, $old_instance)
    {
        return [];
    }

    private static function escape_url(string $url): string
    {
        if (function_exists('esc_url')) {
            return esc_url($url);
        }

        return 'esc_url(' . $url . ')';
    }

    private static function enrich_attachment_url(string $url, int $attachment_id, $size = null): void
    {
        if (
            class_exists('Fifu_Local_Media_Renderer')
            && method_exists(
                'Fifu_Local_Media_Renderer',
                'enrich_attachment_url'
            )
        ) {
            Fifu_Local_Media_Renderer::enrich_attachment_url(
                $url,
                $attachment_id,
                $size
            );
        }
    }
}
