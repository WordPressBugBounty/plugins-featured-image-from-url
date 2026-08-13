<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Handles auto detection of external media URLs on save_post.
 */
final class Fifu_External_Media_Auto_Detect_Service
{
    /** @var array<int,string> */
    private static array $rawPostContentByPostId = [];

    /**
     * Register WordPress hooks related to external media auto detection.
     */
    public static function register_hooks(): void
    {
        add_filter('wp_insert_post_data', [self::class, 'capture_raw_post_content'], 10, 4);
        add_action('save_post', [self::class, 'handle_save_post'], 10, 2);
    }

    public static function capture_raw_post_content($data, $postarr, $unsanitized_postarr = [], $update = false)
    {
        if (!is_array($data)) {
            return $data;
        }

        if (!is_array($postarr)) {
            return $data;
        }

        $postId = (int) ($postarr['ID'] ?? $postarr['post_ID'] ?? 0);
        if ($postId > 0) {
            $rawContent = null;
            if (
                is_array($unsanitized_postarr)
                && isset($unsanitized_postarr['post_content'])
                && !is_array($unsanitized_postarr['post_content'])
            ) {
                $rawContent = (string) $unsanitized_postarr['post_content'];
            } elseif (
                $unsanitized_postarr instanceof \WP_Post
                && isset($unsanitized_postarr->post_content)
                && !is_array($unsanitized_postarr->post_content)
            ) {
                $rawContent = (string) $unsanitized_postarr->post_content;
            } elseif (isset($postarr['post_content']) && !is_array($postarr['post_content'])) {
                $rawContent = (string) $postarr['post_content'];
            }

            if ($rawContent !== null) {
                self::$rawPostContentByPostId[$postId] = wp_unslash($rawContent);
            }
        }

        return $data;
    }

    /**
     * Handle save_post to detect external media URLs and apply defaults.
     *
     * @param mixed $postId
     * @param mixed $post
     */
    public static function handle_save_post($postId, $post): void
    {
        $postId = is_numeric($postId) ? (int) $postId : 0;

        if ($postId <= 0 || !$post instanceof \WP_Post) {
            return;
        }

        if (isset($_POST['fifu_input_url'])) {
            return;
        }

        if (!self::should_skip_post($postId)) {
            $content = self::get_content_for_auto_detect($postId, $post);
            $selectedMedia = self::find_selected_media($postId, $content);

            if (is_array($selectedMedia)) {
                $type = (string) ($selectedMedia['type'] ?? '');
                $url = (string) ($selectedMedia['url'] ?? '');
                if ($url !== '') {
                    if (self::should_preserve_existing_featured_media($postId, $url)) {
                        return;
                    }

                    if ($type === 'image') {
                        self::set_detected_image($postId, $url);
                        return;
                    }
                }
            }
        }

        self::apply_default_if_needed($postId);
        self::handle_slotslaunch_image($postId);
    }

    private static function get_content_for_auto_detect(int $postId, \WP_Post $post): string
    {
        if (isset($_POST['content']) && !is_array($_POST['content'])) {
            return wp_unslash((string) $_POST['content']);
        }

        if (isset($_POST['post_content']) && !is_array($_POST['post_content'])) {
            return wp_unslash((string) $_POST['post_content']);
        }

        if ($postId > 0 && isset(self::$rawPostContentByPostId[$postId])) {
            $content = wp_unslash(self::$rawPostContentByPostId[$postId]);
            unset(self::$rawPostContentByPostId[$postId]);
            return $content;
        }

        return (string) $post->post_content;
    }

    private static function should_skip_post(int $postId): bool
    {
        if (($_POST['action'] ?? '') === 'elementor_ajax') {
            return true;
        }

        if (!Fifu_Options_Utils::is_on('fifu_get_first')) {
            return true;
        }

        if (self::has_blocking_local_featured_image($postId)) {
            return true;
        }

        if (!Fifu_Post_Type_Utils::is_valid_cpt($postId)) {
            return true;
        }

        return false;
    }

    /**
     * Returns the first image that should win for Free auto-detection.
     *
     * @param int    $postId
     * @param string $content
     * @return array{type:string,url:string,alt?:?string,tag?:string}|null
     */
    private static function find_selected_media(int $postId, ?string $content): ?array
    {
        $selectedMedia = Fifu_Content_Url_Scanner::find_first_media($postId, $content, 'image');
        if (!is_array($selectedMedia)) {
            return null;
        }
        return $selectedMedia;
    }

    private static function should_preserve_existing_featured_media(int $postId, string $detectedUrl): bool
    {
        $existingUrl = self::get_existing_effective_featured_media_url($postId);
        if ($existingUrl === '') {
            return false;
        }

        if ($detectedUrl === '' || $existingUrl === $detectedUrl) {
            return false;
        }

        return !Fifu_Options_Utils::is_on('fifu_ovw_first');
    }

	private static function get_existing_effective_featured_media_url(int $postId): string
	{
		if (function_exists('fifu_db2_manager')) {
			$manager = fifu_db2_manager();
			if ($manager) {
				$imageMapping = $manager->getPostMapping($postId, 'image', 0);
				if (is_array($imageMapping) && trim((string) ($imageMapping['url'] ?? '')) !== '') {
					return trim((string) $imageMapping['url']);
				}
			}
		}

		if (trim((string) get_post_meta($postId, 'fifu_image_url', true)) !== '') {
			return trim((string) get_post_meta($postId, 'fifu_image_url', true));
		}

		return '';
	}

    private static function has_db2_featured_media(int $postId, string $type): bool
    {
        if (!function_exists('fifu_db2_manager')) {
            return false;
        }

        $manager = fifu_db2_manager();
        $mapping = $manager ? $manager->getPostMapping($postId, $type, 0) : null;
        $url = is_array($mapping) ? trim((string) ($mapping['url'] ?? '')) : '';
        return $url !== '';
    }

    private static function set_detected_image(int $postId, string $imageUrl): void
    {
        $imageUrl = esc_url_raw(rtrim($imageUrl));
        if ($imageUrl === '') {
            return;
        }

        Fifu_Developer_Media_Service::set_image($postId, $imageUrl);
    }

    private static function apply_default_if_needed(int $postId): void
    {
        if (!Fifu_Options_Utils::is_on('fifu_enable_default_url')) {
            return;
        }

        $defaultUrl = get_option('fifu_default_url');
        if (!$defaultUrl) {
            return;
        }

        if (!Fifu_Post_Type_Utils::is_valid_default_cpt($postId)) {
            return;
        }

        Fifu_Post_Attachment_Sync_Service::sync_featured_attachment($postId);
    }

    private static function handle_slotslaunch_image(int $postId): void
    {
        if (!Fifu_Plugin_Detector::is_slotslaunch_active()) {
            return;
        }

        $url = esc_url_raw(rtrim((string) get_post_meta($postId, 'slimg', true)));
        if (!$url) {
            return;
        }

        Fifu_Developer_Media_Service::set_image(
            $postId,
            $url
        );
    }

    private static function has_blocking_local_featured_image(int $postId): bool
    {
        if (!Fifu_Post_Meta_Utils::has_local_featured_image($postId)) {
            return false;
        }

        if (self::get_existing_effective_featured_media_url($postId) !== '') {
            return false;
        }

        return true;
    }
}
