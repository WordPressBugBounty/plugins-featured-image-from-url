<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
class Fifu_Elementor_Integration {

    public static function register_hooks(): void {
        if (!self::is_active()) {
            return;
        }

        add_action('init', [self::class, 'i18n']);
        add_action('elementor/widgets/register', [self::class, 'on_widgets_register']);
        add_action('elementor/controls/register', [self::class, 'on_controls_register']);
        add_action('elementor/frontend/after_enqueue_scripts', [self::class, 'enqueue_widget_scripts']);
        add_action(
            'elementor/editor/after_save',
            [self::class, 'image_after_save_elementor_data'],
            10,
            2
        );
    }

    private static function is_active(): bool {
        $detector_active = class_exists('Fifu_Plugin_Detector')
            && Fifu_Plugin_Detector::is_elementor_active();

        return $detector_active
            || did_action('elementor/loaded') > 0
            || class_exists('\Elementor\Plugin');
    }

    public static function i18n(): void {
        load_plugin_textdomain(FIFU_SLUG);
    }

    public static function on_widgets_register(\Elementor\Widgets_Manager $widgets_manager): void {
        require_once __DIR__ . '/widgets/class-elementor-fifu-image-widget.php';
        $widgets_manager->register(new \Elementor_FIFU_Widget());
    }

    public static function on_controls_register($controls_manager): void {
        // Custom controls are not registered for this extension.
    }

    public static function enqueue_widget_scripts(): void {
        // Frontend assets are not enqueued via this integration.
    }

    public static function image_after_save_elementor_data(int $post_id, $editor_data): void {
        if (!is_array($editor_data)) {
            return;
        }

        $candidates = self::collect_fifu_elementor_media_candidates($editor_data);
        $action = self::resolve_final_elementor_media_action($candidates);
        if ($action === null) {
            return;
        }

        self::apply_final_elementor_media_action($post_id, $action);
    }

    private static function collect_fifu_elementor_media_candidates(array $elements, int $depth = 0): array {
        $candidates = [];
        foreach ($elements as $el) {
            if (!is_array($el)) {
                continue;
            }

            if (isset($el['elType'], $el['widgetType']) && $el['elType'] === 'widget') {
                $settings = $el['settings'] ?? [];

                if ($el['widgetType'] === 'fifu-elementor') {
                    if (!array_key_exists('fifu_input_url', $settings)) {
                        continue;
                    }

                    $raw_url = $settings['fifu_input_url'];
                    $trimmed_url = trim((string) $raw_url);
                    if ($trimmed_url === '') {
                        $candidates[] = ['type' => 'image_clear'];
                        continue;
                    }

                    $image_url = self::normalize_elementor_image_url($raw_url);
                    if ($image_url === null) {
                        continue;
                    }

                    $candidates[] = [
                        'type' => 'image',
                        'url' => $image_url,
                        'alt' => self::normalize_elementor_alt_value($settings['fifu_input_alt'] ?? null),
                        'depth' => $depth,
                    ];
                }
            }

            if (!empty($el['elements']) && is_array($el['elements'])) {
                $candidates = array_merge($candidates, self::collect_fifu_elementor_media_candidates($el['elements'], $depth + 1));
            }
        }

        return $candidates;
    }

    private static function resolve_final_elementor_media_action(array $candidates): ?array {
        if (empty($candidates)) {
            return null;
        }

        $last_image = null;
        $last_effective = null;

        foreach ($candidates as $index => $candidate) {
            if (!is_array($candidate) || !isset($candidate['type'])) {
                continue;
            }

            $candidate['index'] = $index;
            if ($candidate['type'] === 'image') {
                $last_image = $candidate;
                $last_effective = $candidate;
                continue;
            }

            if ($candidate['type'] === 'image_clear') {
                $last_effective = $candidate;
            }
        }

        if ($last_effective === null) {
            return null;
        }

        if ($last_effective['type'] === 'image') {
            return [
                'action' => 'set_image',
                'url' => $last_effective['url'],
                'alt' => $last_effective['alt'] ?? null,
            ];
        }

        if ($last_effective['type'] === 'image_clear') {
            for ($i = $last_effective['index'] + 1; $i < count($candidates); $i++) {
                $candidate = $candidates[$i];
                if (is_array($candidate) && in_array($candidate['type'] ?? '', ['image'], true)) {
                    return null;
                }
            }

            return ['action' => 'clear_image'];
        }

        return null;
    }

    private static function apply_final_elementor_media_action(int $post_id, ?array $action): void {
        if ($action === null || !isset($action['action'])) {
            return;
        }

        if ($action['action'] === 'set_image') {
            Fifu_Developer_Media_Service::set_image($post_id, $action['url']);
            if (
                array_key_exists(
                    'alt',
                    $action
                )
                && $action['alt'] !== null
                && self::
                    image_alt_needs_write(
                        $post_id,
                        (string) $action['alt']
                    )
            ) {
                self::write_image_alt(
                    $post_id,
                    (string) $action['alt'],
                    false
                );
            }

            if (function_exists('get_post_thumbnail_id')) {
                $att_id = get_post_thumbnail_id($post_id);
            } else {
                $att_id = 0;
            }

            if (
                $att_id
                && function_exists(
                    'getimagesize'
                )
                && !self::
                    attachment_has_valid_dimensions(
                        (int) $att_id
                    )
            ) {
                $image_sizes = @getimagesize($action['url']);
                if ($image_sizes && isset($image_sizes[0], $image_sizes[1])) {
                    Fifu_Attachment_Dimensions_Service::update_dimensions($att_id, (int) $image_sizes[0], (int) $image_sizes[1]);
                }
            }
            return;
        }

        if ($action['action'] === 'clear_image') {
            Fifu_Developer_Media_Service::set_image($post_id, '');
        }
    }

    private static function normalize_elementor_image_url($image_url): ?string {
        $image_url = trim((string) $image_url);
        if ($image_url === '') {
            return null;
        }

        if (filter_var($image_url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $validated_url = function_exists('wp_http_validate_url') ? wp_http_validate_url($image_url) : $image_url;
        if ($validated_url === false) {
            if (function_exists('wp_http_validate_url')) {
                wp_http_validate_url($image_url);
            }
            return null;
        }

        return (string) $validated_url;
    }

    private static function image_alt_needs_write(
        int $post_id,
        string $alt_value
    ): bool {
        $stripped_alt =
            function_exists(
                'wp_strip_all_tags'
            )
                ? (string)
                    wp_strip_all_tags(
                        $alt_value
                    )
                : strip_tags(
                    $alt_value
                );

        $expected_alt =
            self::
            normalize_elementor_alt_value(
                $stripped_alt
            );

        if ($expected_alt === null) {
            return true;
        }

        if (
            !function_exists(
                'fifu_db2_manager'
            )
        ) {
            return true;
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
                    'getPostAltMapping'
                )
            ) {
                return true;
            }

            $mapping =
                $manager->
                    getPostAltMapping(
                        $post_id,
                        'image',
                        0
                    );

            if (
                !is_array(
                    $mapping
                )
                || !array_key_exists(
                    'alt',
                    $mapping
                )
            ) {
                return true;
            }

            return self::
                normalize_elementor_alt_value(
                    (string)
                    $mapping['alt']
                )
                !== $expected_alt;
        } catch (\Throwable $throwable) {
            return true;
        }
    }

    private static function attachment_has_valid_dimensions(
        int $attachment_id
    ): bool {
        if (
            $attachment_id <= 0
            || !function_exists(
                'wp_get_attachment_metadata'
            )
        ) {
            return false;
        }

        $metadata =
            wp_get_attachment_metadata(
                $attachment_id
            );

        return is_array(
            $metadata
        )
            && (int) (
                $metadata['width']
                ?? 0
            ) > 0
            && (int) (
                $metadata['height']
                ?? 0
            ) > 0;
    }

    private static function write_image_alt(int $post_id, string $alt_value, bool $force_delete_first): void {
        $stripped_alt = function_exists('wp_strip_all_tags')
            ? (string) wp_strip_all_tags($alt_value)
            : strip_tags($alt_value);
        $value = self::normalize_elementor_alt_value($stripped_alt);

        if (class_exists('FIFU_Post_Meta_Updater')) {
            FIFU_Post_Meta_Updater::instance()->update_or_delete_with_status($post_id, 'fifu_image_alt', $value);
            return;
        }

        if (function_exists('fifu_update_or_delete')) {
            fifu_update_or_delete($post_id, 'fifu_image_alt', $value ?? '');
            return;
        }

        if ($force_delete_first && function_exists('delete_post_meta')) {
            delete_post_meta($post_id, 'fifu_image_alt');
        }
        if ($value !== null && function_exists('update_post_meta')) {
            update_post_meta($post_id, 'fifu_image_alt', $value);
        }
    }

    private static function normalize_elementor_alt_value($value): ?string {
        $trimmed = trim((string) $value);
        if ($trimmed === '' || preg_match('/^(null|undefined)$/i', $trimmed) === 1) {
            return null;
        }

        return $trimmed;
    }
}
