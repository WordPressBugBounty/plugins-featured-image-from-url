<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

final class Fifu_Admin_Featured_Media_Filter
{
    private const PARAMETER = 'fifu_featured_media';

    private const QUERY_VAR = 'fifu_featured_media_status';

    private const STATUSES = [
        'any',
        'none',
        'image',
        'remote_image',
        'local_image',
    ];

    public static function register(): void
    {
        if (!is_user_logged_in() || !current_user_can('publish_posts')) {
            return;
        }

        add_action('restrict_manage_posts', [self::class, 'render_dropdown'], 10, 2);
        add_action('pre_get_posts', [self::class, 'filter_query'], 10, 1);
        add_filter('posts_clauses', [self::class, 'filter_posts_clauses'], 10, 2);
    }

    public static function render_dropdown(string $postType, string $which): void
    {
        if ($which !== 'top' || !post_type_supports($postType, 'thumbnail')) {
            return;
        }

        $selected = isset($_REQUEST[self::PARAMETER])
            ? sanitize_key(wp_unslash((string) $_REQUEST[self::PARAMETER]))
            : '';
        echo '<select name="' . esc_attr(self::PARAMETER)
            . '" id="' . esc_attr(self::PARAMETER)
            . '" class="fifu-featured-media-filter"'
            . ' style="min-width: 240px; padding-right: 28px;">';

        echo '<option value="">'
            . esc_html__('All featured media statuses', 'featured-image-from-url')
            . '</option>';

        echo '<optgroup label="'
            . esc_attr__('Status', 'featured-image-from-url')
            . '">';
        self::renderOption('any', __('Any featured media', 'featured-image-from-url'), $selected);
        self::renderOption('none', __('No assigned featured media', 'featured-image-from-url'), $selected);
        echo '</optgroup>';

        echo '<optgroup label="'
            . esc_attr__('Images', 'featured-image-from-url')
            . '">';
        self::renderOption('image', __('Featured image', 'featured-image-from-url'), $selected);
        self::renderOption('remote_image', __('Remote featured image', 'featured-image-from-url'), $selected);
        self::renderOption('local_image', __('Local featured image', 'featured-image-from-url'), $selected);
        echo '</optgroup>';

        echo '</select>';
    }

    private static function renderOption(string $value, string $label, string $selected): void
    {
        printf(
            '<option value="%1$s"%2$s>%3$s</option>',
            esc_attr($value),
            selected($selected, $value, false),
            esc_html($label)
        );
    }

    private static function isEligibleQuery(WP_Query $query): bool
    {
        global $pagenow;

        if (
            !is_admin()
            || $pagenow !== 'edit.php'
            || !$query->is_main_query()
        ) {
            return false;
        }

        $postType = $query->get('post_type') ?: 'post';

        return post_type_supports(
            (string) $postType,
            'thumbnail'
        );
    }

    public static function filter_query(WP_Query $query): void
    {
        if (!self::isEligibleQuery($query)) {
            return;
        }

        $status = $query->get(self::PARAMETER);

        if ((!is_string($status) || $status === '') && isset($_REQUEST[self::PARAMETER])) {
            $status = wp_unslash((string) $_REQUEST[self::PARAMETER]);
        }

        $status = sanitize_key((string) $status);
        if (in_array($status, self::STATUSES, true)) {
            $query->set(self::QUERY_VAR, $status);
        }
    }

    public static function filter_posts_clauses(array $clauses, WP_Query $query): array
    {
        if (!self::isEligibleQuery($query)) {
            return $clauses;
        }

        $status = $query->get(self::QUERY_VAR);
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            return $clauses;
        }

        $image = self::db2Expression(['image'], 0) . ' OR ' . self::legacyExpression(['fifu_image_url']);
        $remote = '(' . $image . ')';
        $local = self::localImageExpression($remote);
        $featured = '(' . $remote . ' OR ' . $local . ')';
        $any = $featured;

        $condition = [
            'any' => $any,
            'none' => 'NOT ' . $any,
            'image' => $featured,
            'remote_image' => $remote,
            'local_image' => $local,
        ][$status];
        $clauses['where'] .= ' AND (' . $condition . ')';

        return $clauses;
    }

    private static function db2Expression(array $types, ?int $index = null): string
    {
        global $wpdb;
        $typesSql = implode(', ', array_map(static fn (string $type): string => "'" . esc_sql($type) . "'", $types));
        $indexSql = $index === null ? '' : ' AND m.key_index = ' . $index;
        return "EXISTS (SELECT 1 FROM {$wpdb->prefix}fifu_map m INNER JOIN {$wpdb->prefix}fifu_key k ON k.key_id = m.key_id INNER JOIN {$wpdb->prefix}fifu_url u ON u.hash = m.hash WHERE m.post_id = {$wpdb->posts}.ID{$indexSql} AND k.key_type IN ({$typesSql}) AND TRIM(u.url) <> '')";
    }

    private static function legacyExpression(array $keys): string
    {
        global $wpdb;
        $parts = [];
        foreach ($keys as $key) {
            $parts[] = "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = '" . esc_sql($key) . "' AND TRIM(pm.meta_value) <> '')";
        }
        return '(' . implode(' OR ', $parts) . ')';
    }

    private static function localImageExpression(string $remote): string
    {
        global $wpdb;
        $author = (int) Fifu_Options_Utils::get_author();
        return "(EXISTS (SELECT 1 FROM {$wpdb->postmeta} thumb INNER JOIN {$wpdb->posts} attachment ON attachment.ID = CAST(thumb.meta_value AS UNSIGNED) WHERE thumb.post_id = {$wpdb->posts}.ID AND thumb.meta_key = '_thumbnail_id' AND CAST(thumb.meta_value AS UNSIGNED) > 0 AND attachment.post_type = 'attachment' AND attachment.post_author <> {$author}) AND NOT {$remote})";
    }
}
