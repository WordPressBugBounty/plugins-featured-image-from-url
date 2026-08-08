<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Post_Featured_Media_State_Resolver
{
    public static function resolve(int $postId): array
    {
        $imageUrl = self::getMappingUrl($postId, 'image', 0);
        $hasImage = $imageUrl !== '';

        if ($hasImage) {
            return self::state('image', $imageUrl);
        }

        return self::state(null, null);
    }

    private static function getMappingUrl(int $postId, string $type, int $index): string
    {
        if (!function_exists('fifu_db2_manager')) {
            return '';
        }
        $manager = fifu_db2_manager();
        if (!$manager instanceof Fifu_Db2_Manager) {
            return '';
        }
        $mapping = $manager->getPostMapping($postId, $type, $index);
        if (!is_array($mapping)) {
            return '';
        }

        $url = $mapping['url'] ?? null;
        if (!is_string($url) && !is_numeric($url)) {
            return '';
        }

        return trim((string) $url);
    }

    private static function state(?string $type, ?string $url): array
    {
        $type = $type !== null ? trim($type) : null;
        $url = $url !== null ? trim($url) : null;

        return [
            'type' => $type !== '' ? $type : null,
            'url' => $url !== '' ? $url : null,
        ];
    }
}
