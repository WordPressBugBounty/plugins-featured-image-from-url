<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Repository for retrieving FIFU attachment references.
 */
class Fifu_Attachment_Repository {

    /** @var wpdb */
    private wpdb $wpdb;

    /** @var string */
    private string $posts_table;

    /** @var string */
    private string $postmeta_table;

    /** @var string */
    private string $termmeta_table;

    public function __construct(?wpdb $wpdb_instance = null) {
        $this->wpdb = $wpdb_instance ?? $GLOBALS['wpdb'];
        $this->posts_table = $this->wpdb->posts;
        $this->postmeta_table = $this->wpdb->postmeta;
        $this->termmeta_table = $this->wpdb->termmeta;
    }

    private function getAuthorId(): int {
        return (int) Fifu_Options_Utils::get_author();
    }

    /**
     * Find an attachment ID by parent post, file path and category flag.
     *
     * @param int    $post_parent
     * @param string $file
     * @param bool   $is_category
     * @return int|null
     */
    public function find_attachment_id(int $post_parent, string $file, bool $is_category): ?int {
        $ctgr_sql = $is_category ? "AND p.post_name LIKE 'fifu-category%'" : '';

        $sql = $this->wpdb->prepare(
            "SELECT pm.post_id
            FROM {$this->postmeta_table} pm
            WHERE pm.meta_key = '_wp_attached_file'
              AND pm.meta_value = %s
              AND pm.post_id IN (
                  SELECT p.id
                  FROM {$this->posts_table} p 
                  WHERE p.post_parent = %d
                    AND post_author = %d {$ctgr_sql}
              )
            LIMIT 1",
            $file,
            $post_parent,
            $this->getAuthorId()
        );

        $row = $this->wpdb->get_row($sql);
        return $row ? (int) $row->post_id : null;
    }

    /**
     * Find an incomplete FIFU attachment whose retained remote URL can be used for recovery.
     */
    public function find_incomplete_attachment_id(int $post_parent, string $url, bool $is_category): ?int
    {
        $ctgr_sql = $is_category ? "AND p.post_name LIKE 'fifu-category%'" : '';
        if (!$is_category) {
            $ctgr_sql = "AND p.post_name NOT LIKE 'fifu-category%'";
        }

        $sql = $this->wpdb->prepare(
            "SELECT p.ID
             FROM {$this->posts_table} p
             WHERE p.post_parent = %d
               AND p.post_author = %d
               AND p.post_type = 'attachment'
               {$ctgr_sql}
               AND p.post_content_filtered = %s
               AND NOT EXISTS (
                   SELECT 1
                   FROM {$this->postmeta_table} pm
                   WHERE pm.post_id = p.ID
                     AND pm.meta_key = '_wp_attached_file'
                     AND TRIM(pm.meta_value) <> ''
               )
             ORDER BY p.ID ASC
             LIMIT 1",
            $post_parent,
            $this->getAuthorId(),
            trim(htmlspecialchars_decode($url))
        );

        $attachment_id = $this->wpdb->get_var($sql);
        return $attachment_id ? (int) $attachment_id : null;
    }

    /**
     * Check whether the given attachment ID was created by FIFU.
     *
     * @param int $attachment_id
     * @return bool
     */
    public function is_fifu_attachment(int $attachment_id): bool {
        if ($attachment_id <= 0) {
            return false;
        }

        $sql = $this->wpdb->prepare(
            "SELECT 1
             FROM {$this->posts_table}
             WHERE id = %d
               AND post_type = 'attachment'
               AND post_author = %d",
            $attachment_id,
            $this->getAuthorId()
        );
        return (bool) $this->wpdb->get_var($sql);
    }

    /**
     * Fetch the IDs of orphan attachments that are still linked to a post.
     *
     * @param int $post_id
     * @return string|null
     */
    public function get_orphan_attachments_for_post(int $post_id): ?string {
        $sql = $this->wpdb->prepare(
            "SELECT p.ID
            FROM {$this->posts_table} p
            WHERE p.post_parent = %d
              AND p.post_author = %d
              AND p.post_name NOT LIKE %s
              AND p.post_type = 'attachment'
              AND NOT EXISTS (
                  SELECT 1
                  FROM {$this->postmeta_table} pm2
                  WHERE pm2.post_id = p.post_parent
                    AND pm2.meta_key = '_thumbnail_id'
                    AND pm2.meta_value <> ''
                    AND CAST(pm2.meta_value AS UNSIGNED) = p.ID
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM {$this->postmeta_table} reference_meta
                  WHERE reference_meta.post_id = p.post_parent
                    AND (
                        TRIM(reference_meta.meta_value) = CAST(p.ID AS CHAR)
                        OR FIND_IN_SET(CAST(p.ID AS CHAR), REPLACE(reference_meta.meta_value, ' ', '')) > 0
                    )
              )
            ORDER BY p.ID ASC",
            $post_id,
            $this->getAuthorId(),
            'fifu-category%'
        );
        return $this->format_unreferenced_attachment_ids($this->wpdb->get_col($sql));
    }

    /**
     * Find recoverable, incomplete FIFU attachments that are not active or referenced media.
     *
     * @param int[] $protected_ids Attachment IDs that must not be deleted.
     */
    public function get_incomplete_orphan_attachments_for_post(int $post_id, array $protected_ids = []): ?string
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $protected_ids),
            static fn(int $id): bool => $id > 0
        )));
        $exclude_sql = $ids ? 'AND p.ID NOT IN (' . implode(',', $ids) . ')' : '';

        $sql = $this->wpdb->prepare(
             "SELECT p.ID
             FROM {$this->posts_table} p
             WHERE p.post_parent = %d
               AND p.post_author = %d
               AND p.post_name NOT LIKE %s
               AND p.post_type = 'attachment'
               AND (
                   TRIM(p.post_content_filtered) <> ''
                   OR TRIM(p.guid) = ''
               )
               {$exclude_sql}
               AND NOT EXISTS (
                   SELECT 1
                   FROM {$this->postmeta_table} file_meta
                   WHERE file_meta.post_id = p.ID
                     AND file_meta.meta_key = '_wp_attached_file'
                     AND TRIM(file_meta.meta_value) <> ''
               )
               AND NOT EXISTS (
                   SELECT 1
                   FROM {$this->postmeta_table} thumbnail_meta
                   WHERE thumbnail_meta.post_id = p.post_parent
                     AND thumbnail_meta.meta_key = '_thumbnail_id'
                     AND CAST(thumbnail_meta.meta_value AS UNSIGNED) = p.ID
               )
               AND NOT EXISTS (
                   SELECT 1
                   FROM {$this->postmeta_table} reference_meta
                   WHERE reference_meta.post_id = p.post_parent
                     AND (
                         TRIM(reference_meta.meta_value) = CAST(p.ID AS CHAR)
                         OR FIND_IN_SET(CAST(p.ID AS CHAR), REPLACE(reference_meta.meta_value, ' ', '')) > 0
                     )
               )
             ORDER BY p.ID ASC",
            $post_id,
            $this->getAuthorId(),
            'fifu-category%'
        );

        return $this->format_unreferenced_attachment_ids($this->wpdb->get_col($sql));
    }

    /**
     * Return candidate IDs not referenced by supported WordPress media fields.
     *
     * The candidate set is already scoped to one parent post. One additional
     * query checks those IDs against references across posts, rather than
     * scanning postmeta separately for each attachment.
     *
     * @param mixed $candidate_ids
     */
    private function format_unreferenced_attachment_ids($candidate_ids): ?string
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $candidate_ids),
            static fn(int $id): bool => $id > 0
        )));
        if (!$ids) {
            return null;
        }

        $referenced_ids = $this->get_referenced_attachment_ids($ids);
        $orphan_ids = array_values(array_diff($ids, $referenced_ids));

        return $orphan_ids ? implode(',', $orphan_ids) : null;
    }

    /**
     * Find candidate attachment IDs referenced by supported post metadata.
     *
     * Supported forms are exact attachment IDs, WordPress product gallery
     * lists, and serialized arrays with an explicit attachment_id key.
     *
     * @param int[] $attachment_ids
     * @return int[]
     */
    public function get_referenced_attachment_ids(array $attachment_ids, int $exclude_post_id = 0): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $attachment_ids),
            static fn(int $id): bool => $id > 0
        )));
        if (!$ids) {
            return [];
        }

        $rows = $this->get_attachment_reference_rows($ids, $exclude_post_id);
        $referenced = [];
        foreach ($rows as $row) {
            $meta_key = is_object($row) ? (string) ($row->meta_key ?? '') : (string) ($row['meta_key'] ?? '');
            $meta_value = is_object($row) ? (string) ($row->meta_value ?? '') : (string) ($row['meta_value'] ?? '');
            $serialized_references = $this->serialized_value_attachment_ids($meta_value, $ids);

            foreach ($ids as $id) {
                if ($this->meta_value_references_attachment($meta_key, $meta_value, $id, $serialized_references)) {
                    $referenced[$id] = true;
                }
            }
        }

        foreach ($this->get_attachment_term_reference_rows($ids) as $row) {
            $meta_key = is_object($row) ? (string) ($row->meta_key ?? '') : (string) ($row['meta_key'] ?? '');
            $meta_value = is_object($row) ? (string) ($row->meta_value ?? '') : (string) ($row['meta_value'] ?? '');
            if (!in_array($meta_key, ['thumbnail_id', 'product_attribute_image'], true)) {
                continue;
            }

            foreach ($ids as $id) {
                if (trim($meta_value) === (string) $id) {
                    $referenced[$id] = true;
                }
            }
        }

        return array_map('intval', array_keys($referenced));
    }

    /**
     * Return term IDs that use an attachment through supported category media fields.
     *
     * Arbitrary numeric term metadata is deliberately excluded: only the standard
     * category thumbnail and WooCommerce variation-swatches image fields represent
     * supported term media references.
     *
     * @return int[]
     */
    public function get_attachment_reference_term_ids(int $attachment_id, int $exclude_term_id = 0): array
    {
        if ($attachment_id <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT tm.term_id
                FROM {$this->termmeta_table} tm
                WHERE tm.meta_key IN ('thumbnail_id', 'product_attribute_image')
                  AND tm.meta_value = %s";
        $args = [(string) $attachment_id];
        if ($exclude_term_id > 0) {
            $sql .= ' AND tm.term_id <> %d';
            $args[] = $exclude_term_id;
        }
        $sql = $this->wpdb->prepare($sql, $args);

        return array_values(array_unique(array_filter(
            array_map('intval', (array) $this->wpdb->get_col($sql)),
            static fn(int $term_id): bool => $term_id > 0
        )));
    }

    /**
     * Return post IDs that refer to an attachment through supported media fields.
     *
     * @return int[]
     */
    public function get_attachment_reference_post_ids(int $attachment_id, int $exclude_post_id = 0): array
    {
        if ($attachment_id <= 0) {
            return [];
        }

        $rows = $this->get_attachment_reference_rows([$attachment_id], $exclude_post_id);
        $referencing_posts = [];
        foreach ($rows as $row) {
            $meta_key = is_object($row) ? (string) ($row->meta_key ?? '') : (string) ($row['meta_key'] ?? '');
            $meta_value = is_object($row) ? (string) ($row->meta_value ?? '') : (string) ($row['meta_value'] ?? '');
            $serialized_references = $this->serialized_value_attachment_ids($meta_value, [$attachment_id]);
            if (!$this->meta_value_references_attachment($meta_key, $meta_value, $attachment_id, $serialized_references)) {
                continue;
            }

            $post_id = is_object($row) ? (int) ($row->post_id ?? 0) : (int) ($row['post_id'] ?? 0);
            if ($post_id > 0) {
                $referencing_posts[$post_id] = true;
            }
        }

        return array_map('intval', array_keys($referencing_posts));
    }

    /**
     * Return posts with supported media references, ordered for ownership selection.
     *
     * Featured-image references take priority over galleries and explicit
     * serialized attachment_id structures. Candidates at the same priority are
     * ordered by post ID, making replacement ownership deterministic. Arbitrary
     * exact numeric metadata remains available through the conservative reference
     * APIs, but is not ownership evidence.
     *
     * @return int[]
     */
    public function get_media_reference_post_ids(int $attachment_id, int $exclude_post_id = 0): array
    {
        if ($attachment_id <= 0) {
            return [];
        }

        $rows = $this->get_attachment_reference_rows([$attachment_id], $exclude_post_id);
        $priorities = [];
        foreach ($rows as $row) {
            $meta_key = is_object($row) ? (string) ($row->meta_key ?? '') : (string) ($row['meta_key'] ?? '');
            $meta_value = is_object($row) ? (string) ($row->meta_value ?? '') : (string) ($row['meta_value'] ?? '');
            $post_id = is_object($row) ? (int) ($row->post_id ?? 0) : (int) ($row['post_id'] ?? 0);
            if ($post_id <= 0) {
                continue;
            }

            $priority = null;
            if ($meta_key === '_thumbnail_id' && trim($meta_value) === (string) $attachment_id) {
                $priority = 0;
            } elseif (
                in_array($meta_key, ['_product_image_gallery', '_wc_additional_variation_images'], true)
                && $this->meta_value_references_attachment($meta_key, $meta_value, $attachment_id, [])
            ) {
                $priority = 1;
            } elseif (isset($this->serialized_value_attachment_ids($meta_value, [$attachment_id])[$attachment_id])) {
                $priority = 2;
            }

            if ($priority !== null && (!isset($priorities[$post_id]) || $priority < $priorities[$post_id])) {
                $priorities[$post_id] = $priority;
            }
        }

        uksort($priorities, static function (int $left, int $right) use ($priorities): int {
            $priorityOrder = $priorities[$left] <=> $priorities[$right];
            return $priorityOrder !== 0 ? $priorityOrder : ($left <=> $right);
        });

        return array_map('intval', array_keys($priorities));
    }

    /** @param int[] $ids @return array<int, object|array<string,mixed>> */
    private function get_attachment_reference_rows(array $ids, int $exclude_post_id = 0): array
    {

        $placeholders = implode(',', array_fill(0, count($ids), '%s'));
        $clauses = ["pm.meta_value IN ({$placeholders})"];
        $args = array_map('strval', $ids);

        foreach ($ids as $id) {
            $clauses[] = "(
                pm.meta_key IN ('_product_image_gallery', '_wc_additional_variation_images')
                AND FIND_IN_SET(%s, REPLACE(pm.meta_value, ' ', '')) > 0
            )";
            $args[] = (string) $id;

            $serialized_integer_needle = '"attachment_id";i:' . $id . ';';
            $clauses[] = 'pm.meta_value LIKE %s';
            $args[] = '%' . $this->wpdb->esc_like($serialized_integer_needle) . '%';

            $serialized_string_needle = '"attachment_id";s:' . strlen((string) $id) . ':"' . $id . '";';
            $clauses[] = 'pm.meta_value LIKE %s';
            $args[] = '%' . $this->wpdb->esc_like($serialized_string_needle) . '%';
        }

        $sql = "SELECT pm.post_id, pm.meta_key, pm.meta_value
                FROM {$this->postmeta_table} pm
                WHERE (" . implode(' OR ', $clauses) . ')';
        if ($exclude_post_id > 0) {
            $sql .= ' AND pm.post_id <> %d';
            $args[] = $exclude_post_id;
        }

        return (array) $this->wpdb->get_results($this->wpdb->prepare($sql, $args));
    }

    /** @param int[] $ids @return array<int, object|array<string,mixed>> */
    private function get_attachment_term_reference_rows(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '%s'));
        $sql = "SELECT tm.term_id, tm.meta_key, tm.meta_value
                FROM {$this->termmeta_table} tm
                WHERE tm.meta_key IN ('thumbnail_id', 'product_attribute_image')
                  AND tm.meta_value IN ({$placeholders})";

        return (array) $this->wpdb->get_results(
            $this->wpdb->prepare($sql, array_map('strval', $ids))
        );
    }

    /** @param array<int,true> $serialized_references */
    private function meta_value_references_attachment(string $meta_key, string $meta_value, int $attachment_id, array $serialized_references): bool
    {
        if (trim($meta_value) === (string) $attachment_id) {
            return true;
        }

        if (
            in_array($meta_key, ['_product_image_gallery', '_wc_additional_variation_images'], true)
            && in_array((string) $attachment_id, array_map('trim', explode(',', $meta_value)), true)
        ) {
            return true;
        }

        return isset($serialized_references[$attachment_id]);
    }

    /**
     * Decode each candidate metadata value once, without instantiating objects.
     *
     * @param int[] $candidate_ids
     * @return array<int, true>
     */
    private function serialized_value_attachment_ids(string $value, array $candidate_ids): array
    {
        if ($value === '' || strpos($value, 'attachment_id') === false) {
            return [];
        }

        $value = trim($value);
        if (function_exists('is_serialized') && !is_serialized($value)) {
            return [];
        }

        $decoded = @unserialize($value, [
            'allowed_classes' => false,
            'max_depth' => 128,
        ]);
        if (!is_array($decoded)) {
            return [];
        }

        $candidate_lookup = array_fill_keys($candidate_ids, true);
        $references = [];
        $this->collect_serialized_attachment_ids($decoded, $candidate_lookup, $references);

        return $references;
    }

    /**
     * @param array<int|string, mixed> $value
     * @param array<int, true> $candidate_lookup
     * @param array<int, true> $references
     */
    private function collect_serialized_attachment_ids(array $value, array $candidate_lookup, array &$references): void
    {
        foreach ($value as $key => $item) {
            if (
                $key === 'attachment_id'
                && (is_int($item) || (is_string($item) && ctype_digit($item)))
                && isset($candidate_lookup[(int) $item])
            ) {
                $references[(int) $item] = true;
            }

            if (is_array($item)) {
                $this->collect_serialized_attachment_ids($item, $candidate_lookup, $references);
            }
        }
    }

    /**
     * Fetch the IDs of orphan attachments that are still linked to a category term.
     *
     * @param int $term_id
     * @return string|null
     */
    public function get_orphan_attachments_for_term(int $term_id): ?string {
        $sql = $this->wpdb->prepare(
            "SELECT GROUP_CONCAT(p.ID) AS ids
            FROM {$this->posts_table} p
            WHERE p.post_parent = %d
              AND p.post_author = %d
              AND p.post_name LIKE %s
              AND p.post_type = 'attachment'
              AND NOT EXISTS (
                  SELECT 1
                  FROM {$this->termmeta_table} tm
                  WHERE tm.term_id = p.post_parent
                    AND tm.meta_key = 'thumbnail_id'
                    AND tm.meta_value = p.ID
              )",
            $term_id,
            $this->getAuthorId(),
            'fifu-category%'
        );
        return $this->wpdb->get_var($sql) ?: null;
    }

}
