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
            $normalized = esc_url_raw($normalized);
            if ($normalized === '') {
                return false;
            }
            if (!$updater->update_or_delete_with_status($post_id, 'fifu_image_url', $normalized)) {
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
