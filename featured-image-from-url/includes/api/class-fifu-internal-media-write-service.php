<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
/**
 * Handles developer-only media operations for FIFU.
 */
class Fifu_Internal_Media_Write_Service
{
    public static function set_image(
        int $post_id,
        $image_url,
        bool $force_featured_attachment_sync = false
    ): bool
    {
        $post_id = (int) $post_id;
        $normalized = trim(
            html_entity_decode(
                (string) $image_url,
                ENT_QUOTES | ENT_HTML5
            )
        );
        $updater = FIFU_Post_Meta_Updater::instance();
        if ($normalized === '') {
            $manager = function_exists('fifu_db2_manager')
                ? fifu_db2_manager()
                : null;
            $previous_alt = $manager instanceof Fifu_Db2_Manager
                ? $manager->getPostAltMapping($post_id, 'image', 0)
                : null;

            if (!$updater->update_or_delete_with_status($post_id, 'fifu_image_alt', null)) {
                return false;
            }
            if (!$updater->update_or_delete_with_status($post_id, 'fifu_image_url', null)) {
                if (is_array($previous_alt) && isset($previous_alt['alt'])) {
                    $updater->update_or_delete_with_status(
                        $post_id,
                        'fifu_image_alt',
                        (string) $previous_alt['alt']
                    );
                }
                return false;
            }
        } else {
            $normalized =
                esc_url_raw(
                    $normalized
                );

            if ($normalized === '') {
                return false;
            }

            if (
                self::
                    is_exact_canonical_featured_image_state(
                        $post_id,
                        $normalized
                    )
            ) {
                if (
                    class_exists(
                        'Fifu_Post_Attachment_Sync_Service',
                        false
                    )
                ) {
                    Fifu_Post_Attachment_Sync_Service::
                        sync_featured_attachment(
                            $post_id,
                            $force_featured_attachment_sync
                        );
                }

                return true;
            }

            if (
                !$updater->
                    update_or_delete_with_status(
                        $post_id,
                        'fifu_image_url',
                        $normalized
                    )
            ) {
                return false;
            }
        }
        if (class_exists('Fifu_Post_Attachment_Sync_Service', false)) {
            Fifu_Post_Attachment_Sync_Service::sync_featured_attachment(
                $post_id,
                $force_featured_attachment_sync
            );
        }
        return true;
    }

    private static function is_exact_canonical_featured_image_state(
        int $post_id,
        string $normalized_url
    ): bool {
        if (
            function_exists(
                'metadata_exists'
            )
            && metadata_exists(
                'post',
                $post_id,
                'fifu_image_url'
            )
        ) {
            return false;
        }

        if (
            !function_exists(
                'fifu_db2_manager'
            )
            || !class_exists(
                'Fifu_Db2_Manager',
                false
            )
        ) {
            return false;
        }

        try {
            $manager =
                fifu_db2_manager();

            if (
                !$manager
                    instanceof
                    Fifu_Db2_Manager
                || !method_exists(
                    $manager,
                    'getPostMapping'
                )
            ) {
                return false;
            }

            $mapping =
                $manager->getPostMapping(
                    $post_id,
                    'image',
                    0
                );

            if (!is_array($mapping)) {
                return false;
            }

            $current_url =
                trim(
                    (string) (
                        $mapping['url']
                        ?? ''
                    )
                );

            $expected_url =
                function_exists(
                    'fifu_convert'
                )
                    ? (string)
                        fifu_convert(
                            $normalized_url
                        )
                    : $normalized_url;

            return $current_url !== ''
                && $current_url
                    === $expected_url;
        } catch (\Throwable $throwable) {
            return false;
        }
    }

    public static function set_category_image(int $term_id, $image_url): bool
    {
        $term_id = (int) $term_id;
        $normalized = trim(
            html_entity_decode(
                (string) $image_url,
                ENT_QUOTES | ENT_HTML5
            )
        );
        $updater = FIFU_Term_Meta_Updater::instance();
        if ($normalized === '') {
            if (!$updater->update_or_delete_term_with_status($term_id, 'fifu_image_url', null)) {
                return false;
            }
        } else {
            $normalized = esc_url_raw($normalized);
            if ($normalized === '') {
                return false;
            }
            if (!$updater->update_or_delete_term_with_status($term_id, 'fifu_image_url', $normalized)) {
                return false;
            }
        }
        if (class_exists('Fifu_Post_Attachment_Sync_Service', false)) {
            Fifu_Post_Attachment_Sync_Service::sync_category_attachment($term_id);
        }
        return true;
    }
}
