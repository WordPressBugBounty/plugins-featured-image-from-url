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
     * @param array $data
     * @param string $moduleId
     * @param int $postId
     * @param bool $isLastIteration
     */
    public static function on_save_data(
        array $data,
        string $moduleId,
        int $postId,
        bool $isLastIteration
    ): void {
        if (!$isLastIteration) {
            return;
        }

        $first = reset($data);
        if (!empty($first['img'])) {
            Fifu_Developer_Media_Service::set_image(
                $postId,
                (string) $first['img']
            );
        }

        remove_action(
            'content_egg_save_data',
            [\ContentEgg\application\components\ExternalFeaturedImage::class, 'setImage'],
            13
        );
    }
}
