<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Content_Egg_Integration
{
    public static function register_hooks(): void
    {
        add_action(
            'content_egg_save_data',
            [self::class, 'on_save_data'],
            12,
            4
        );
    }

    /**
     * Action callback for content_egg_save_data.
     * It will eventually replace the legacy anonymous function.
     *
     * @param mixed $data
     * @param mixed $moduleId
     * @param mixed $postId
     * @param mixed $isLastIteration
     */
    public static function on_save_data(
        $data,
        $moduleId,
        $postId,
        $isLastIteration
    ): void {
        if (!is_array($data) || !$isLastIteration) {
            return;
        }

        $postId = is_numeric($postId) ? (int) $postId : 0;
        if ($postId <= 0) {
            return;
        }

        $first = reset($data);
        $image = is_array($first)
            ? ($first['img'] ?? null)
            : null;

        if (
            !is_array($first)
            || !is_scalar($image)
            || empty($image)
        ) {
            remove_action(
                'content_egg_save_data',
                [\ContentEgg\application\components\ExternalFeaturedImage::class, 'setImage'],
                13
            );

            return;
        }

        Fifu_Developer_Media_Service::set_image(
            $postId,
            (string) $image
        );

        remove_action(
            'content_egg_save_data',
            [\ContentEgg\application\components\ExternalFeaturedImage::class, 'setImage'],
            13
        );
    }
}
