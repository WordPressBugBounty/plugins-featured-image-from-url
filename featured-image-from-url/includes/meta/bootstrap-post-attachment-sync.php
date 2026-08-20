<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Comments must be in English.
// Registers lifecycle hooks that keep FIFU attachments in sync.
add_action(
    'before_delete_post',
    [
        Fifu_Post_Attachment_Sync_Service::class,
        'handle_post_deleted',
    ]
);

$fifuPostAttachmentSyncCallback = static function (
    $post_id,
    $post,
    $update,
    $earlyPass
): void {
        $post_id = is_numeric($post_id) ? (int) $post_id : 0;

        if ($post_id <= 0 || !$post instanceof \WP_Post) {
            return;
        }

        $postId = (int) ($post->ID ?? 0);

        if ($postId <= 0) {
            return;
        }

        $action = $_POST['action'] ?? '';

        if (
            wp_is_post_revision(
                $postId
            )
        ) {
            return;
        }

        if (
            $action
            === 'woocommerce_do_ajax_product_import'
        ) {
            return;
        }

        if ($earlyPass) {
            $explicitFifuImageUrl =
                Fifu_Post_Image_Url_Read_Service::get_image_url(
                    $postId
                );
            $thumbnailId =
                (int) get_post_thumbnail_id(
                    $postId
                );
            $defaultAttachmentId =
                (int) get_option(
                    'fifu_default_attach_id'
                );
            $thumbnailIsFifuOwned =
                $thumbnailId > 0
                && Fifu_Attachment_Update_Service::is_fifu_owned(
                    $thumbnailId
                );
            $thumbnailUrl =
                $thumbnailId > 0
                    ? trim(
                        htmlspecialchars_decode(
                            Fifu_Attachment_Update_Service::get_attachment_remote_url(
                                $thumbnailId
                            )
                        )
                    )
                    : '';
            $defaultUrl =
                trim(
                    htmlspecialchars_decode(
                        (string) get_option(
                            'fifu_default_url'
                        )
                    )
                );
            $uploadDir = wp_upload_dir();
            $uploadsBaseUrl =
                isset($uploadDir['baseurl'])
                && is_string($uploadDir['baseurl'])
                    ? trim($uploadDir['baseurl'])
                    : '';
            $thumbnailIsLocalUpload = false;
            if ($thumbnailUrl !== '' && $uploadsBaseUrl !== '') {
                $thumbnailHost = strtolower(
                    (string) wp_parse_url(
                        $thumbnailUrl,
                        PHP_URL_HOST
                    )
                );
                $uploadsHost = strtolower(
                    (string) wp_parse_url(
                        $uploadsBaseUrl,
                        PHP_URL_HOST
                    )
                );
                $thumbnailPath = rtrim(
                    (string) wp_parse_url(
                        $thumbnailUrl,
                        PHP_URL_PATH
                    ),
                    '/'
                );
                $uploadsPath = rtrim(
                    (string) wp_parse_url(
                        $uploadsBaseUrl,
                        PHP_URL_PATH
                    ),
                    '/'
                );

                $thumbnailIsLocalUpload =
                    $thumbnailHost !== ''
                    && $uploadsHost !== ''
                    && $thumbnailHost === $uploadsHost
                    && $uploadsPath !== ''
                    && (
                        $thumbnailPath === $uploadsPath
                        || str_starts_with(
                            $thumbnailPath,
                            $uploadsPath . '/'
                        )
                    );
            }
            $hasEligibleFifuImage =
                (
                    is_string($explicitFifuImageUrl)
                    && trim($explicitFifuImageUrl) !== ''
                )
                || (
                    $thumbnailId > 0
                    && $thumbnailId !== $defaultAttachmentId
                    && $thumbnailIsFifuOwned
                    && !$thumbnailIsLocalUpload
                    && (
                        $thumbnailUrl === ''
                        || $thumbnailUrl !== $defaultUrl
                    )
                );

            if (!$hasEligibleFifuImage) {
                return;
            }
        }

        if (
            ($post->post_type ?? '') === 'product'
            && isset($_POST['woocommerce_meta_nonce'])
        ) {
            $thumbnailId =
                (int) get_post_thumbnail_id(
                    $postId
                );

            if (
                $thumbnailId > 0
                && get_post(
                    $thumbnailId
                ) instanceof \WP_Post
            ) {
                return;
            }

            $featuredImageUrl =
                Fifu_Post_Main_Image_Resolver::
                    get_main_image_url(
                        $postId,
                        false
                    );

            if (
                !is_string(
                    $featuredImageUrl
                )
                || trim(
                    $featuredImageUrl
                ) === ''
            ) {
                return;
            }

            Fifu_Post_Attachment_Sync_Service::
                sync_featured_attachment(
                    $postId
                );

            return;
        }

        Fifu_Post_Attachment_Sync_Service::
            sync_featured_attachment(
                $postId
            );
    };

add_action(
    'wp_insert_post',
    static function (
        $post_id,
        $post,
        $update
    ) use ($fifuPostAttachmentSyncCallback): void {
        $fifuPostAttachmentSyncCallback(
            $post_id,
            $post,
            $update,
            true
        );
    },
    10,
    3
);

add_action(
    'wp_after_insert_post',
    static function (
        $post_id,
        $post,
        $update,
        $post_before
    ) use ($fifuPostAttachmentSyncCallback): void {
        $fifuPostAttachmentSyncCallback(
            $post_id,
            $post,
            $update,
            false
        );
    },
    10,
    4
);

unset($fifuPostAttachmentSyncCallback);
