<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Polylang_Integration
{
    private static function get_author_id(): int
    {
        if (function_exists('fifu_get_author')) {
            $author = (int) fifu_get_author();
            if ($author > 0) {
                return $author;
            }
        }

        if (
            class_exists('Fifu_Options_Utils', false)
            && method_exists('Fifu_Options_Utils', 'get_author')
        ) {
            $author = (int) Fifu_Options_Utils::get_author();
            if ($author > 0) {
                return $author;
            }
        }

        if (defined('FIFU_AUTHOR')) {
            $author = (int) FIFU_AUTHOR;
            if ($author > 0) {
                return $author;
            }
        }

        return 7777777777;
    }

    public static function register_hooks(): void
    {
        add_filter(
            'pll_copy_post_metas',
            [self::class, 'on_copy_post_metas'],
            10,
            5
        );
    }

    /**
     * Prevent Polylang from copying a FIFU-managed attachment ID.
     *
     * The normal Free image URL metadata remains available for Polylang to
     * copy. Product-gallery and image-list synchronization are Premium-only.
     *
     * @param array $metas
     * @param bool $sync
     * @param int $from
     * @param int $to
     * @param string $lang
     * @return array
     */
    public static function on_copy_post_metas(
        array $metas,
        bool $sync,
        int $from,
        int $to,
        string $lang
    ): array {
        if (!$sync) {
            return $metas;
        }

        if (!self::meta_list_contains($metas, '_thumbnail_id')) {
            return $metas;
        }

        $thumbnailId = (int) get_post_thumbnail_id($to);

        if (self::is_fifu_managed_attachment($thumbnailId)) {
            unset($metas['_thumbnail_id']);
        }

        return $metas;
    }

    private static function is_fifu_managed_attachment(
        int $attachmentId
    ): bool {
        if ($attachmentId <= 0) {
            return false;
        }

        if (
            class_exists('Fifu_Attachment_Update_Service', false)
            && method_exists(
                'Fifu_Attachment_Update_Service',
                'is_fifu_owned'
            )
        ) {
            return Fifu_Attachment_Update_Service::is_fifu_owned(
                $attachmentId
            );
        }

        if (function_exists('fifu_is_fifu_attachment')) {
            return fifu_is_fifu_attachment($attachmentId);
        }

        $author = (int) get_post_field(
            'post_author',
            $attachmentId
        );

        if ($author <= 0) {
            return false;
        }

        $authors = [self::get_author_id()];

        if (defined('FIFU_AUTHOR')) {
            $constantAuthor = (int) FIFU_AUTHOR;
            if ($constantAuthor > 0) {
                $authors[] = $constantAuthor;
            }
        }

        $authors[] = 7777777777;
        $authors[] = 77777;

        $authors = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $authors),
                    static fn(int $candidate): bool => $candidate > 0
                )
            )
        );

        return in_array($author, $authors, true);
    }

    private static function meta_list_contains(
        array $metas,
        string $key
    ): bool {
        foreach ($metas as $metaKey => $value) {
            if ((string) $metaKey === $key) {
                return true;
            }

            if ($value === $key) {
                return true;
            }

            if (
                is_array($value)
                && in_array($key, $value, true)
            ) {
                return true;
            }
        }

        return false;
    }
}
