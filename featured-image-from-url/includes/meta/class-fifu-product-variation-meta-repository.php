<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Reads product variation attributes required by Free read-only views.
 */
class Fifu_Product_Variation_Meta_Repository
{
    private wpdb $wpdb;

    private string $postmeta_table;

    public function __construct(?wpdb $wpdb = null)
    {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
        $this->postmeta_table = $this->wpdb->postmeta;
    }

    /**
     * Returns raw attribute metadata for a set of variation IDs.
     *
     * @param int[] $variation_ids Variation post IDs.
     * @return array<int, array<string, string>>
     */
    public function get_variation_attributes_by_ids(
        array $variation_ids
    ): array {
        if (empty($variation_ids)) {
            return [];
        }

        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($variation_ids),
                '%d'
            )
        );

        $sql = "
            SELECT post_id, meta_key, meta_value
            FROM {$this->postmeta_table}
            WHERE post_id IN ({$placeholders})
              AND meta_key LIKE 'attribute_%'
        ";

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                $sql,
                $variation_ids
            ),
            ARRAY_A
        );

        $attributes = [];

        foreach ($results as $result) {
            $attributes[
                $result['post_id']
            ][
                $result['meta_key']
            ] = $result['meta_value'];
        }

        return $attributes;
    }
}
