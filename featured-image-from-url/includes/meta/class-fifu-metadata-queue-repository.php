<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Repository for FIFU metadata-in and metadata-out queue tables.
 */
class Fifu_Metadata_Queue_Repository {

    /** @var wpdb */
    private wpdb $wpdb;

    /** @var string */
    private string $table_meta_in;

    /** @var string */
    private string $table_meta_out;

    /** @var string */
    /**
     * Constructor.
     *
     * @param wpdb|null $wpdb_instance Optional database abstraction.
     */
    public function __construct(?wpdb $wpdb_instance = null) {
        $this->wpdb = $wpdb_instance ?? $GLOBALS['wpdb'];
        $this->table_meta_in = $this->wpdb->prefix . 'fifu_meta_in';
        $this->table_meta_out = $this->wpdb->prefix . 'fifu_meta_out';
    }

    /**
     * Retrieve the pending meta_in rows.
     *
     * @return array
     */
    public function get_meta_in(): array {
        $sql = "
        SELECT id AS post_id
        FROM {$this->table_meta_in}
        ORDER BY id ASC
    ";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Retrieve the pending meta_out rows.
     *
     * @return array
     */
    public function get_meta_out(): array {
        $sql = "
        SELECT id AS post_id
        FROM {$this->table_meta_out}
        ORDER BY id ASC
    ";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Return the last inserted meta_in ID for a specific type.
     *
     * @param string $type
     * @return int|null
     */
    public function get_last_meta_in_id(string $type): ?int {
        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->table_meta_in} WHERE type = %s ORDER BY id DESC LIMIT 1",
            $type
        );
        $value = $this->wpdb->get_var($sql);
        return $value !== null ? (int) $value : null;
    }

    /**
     * Return the last inserted meta_out ID for a specific type.
     *
     * @param string $type
     * @return int|null
     */
    public function get_last_meta_out_id(string $type): ?int {
        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->table_meta_out} WHERE type = %s ORDER BY id DESC LIMIT 1",
            $type
        );
        $value = $this->wpdb->get_var($sql);
        return $value !== null ? (int) $value : null;
    }

    /**
     * Get the metadata type recorded for a meta_in entry.
     *
     * @param int $id
     * @return string|null
     */
    public function get_meta_in_type(int $id): ?string {
        $sql = $this->wpdb->prepare(
            "SELECT type FROM {$this->table_meta_in} WHERE id = %d",
            $id
        );
        return $this->wpdb->get_var($sql);
    }

    /**
     * Get the metadata type recorded for a meta_out entry.
     *
     * @param int $id
     * @return string|null
     */
    public function get_meta_out_type(int $id): ?string {
        $sql = $this->wpdb->prepare(
            "SELECT type FROM {$this->table_meta_out} WHERE id = %d",
            $id
        );
        return $this->wpdb->get_var($sql);
    }

    /**
     * Delete one metadata-in queue row.
     */
    public function delete_meta_in_row(int $id): bool {
        if ($id <= 0) {
            return false;
        }

        return $this->wpdb->delete(
            $this->table_meta_in,
            ['id' => $id],
            ['%d']
        ) !== false;
    }

    /**
     * Delete one metadata-out queue row.
     */
    public function delete_meta_out_row(int $id): bool {
        if ($id <= 0) {
            return false;
        }

        return $this->wpdb->delete(
            $this->table_meta_out,
            ['id' => $id],
            ['%d']
        ) !== false;
    }

    /**
     * Log information about a recently inserted queue record.
     *
     * @param int    $last_insert_id
     * @param string $table
     */
    public function log_prepare(int $last_insert_id, string $table): void {
        $inserted_records = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, post_ids, type FROM {$table} WHERE id = %d",
                $last_insert_id
            )
        );

        foreach ($inserted_records as $record) {
            Fifu_File_Logger::plugin([$table => [
                'id' => $record->id,
                'post_ids' => $record->post_ids,
                'type' => $record->type
            ]]);
        }
    }

}
