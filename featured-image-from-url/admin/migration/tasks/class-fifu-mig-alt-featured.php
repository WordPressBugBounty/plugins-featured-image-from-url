<?php
declare( strict_types=1 );

class Fifu_Mig_Alt_Featured implements Fifu_Migration_Task_Interface {

    /** @var wpdb */
    protected $wpdb;

    /** @var Fifu_Migration_State */
    protected $state;

    /** @var Fifu_Migration_Logger */
    protected $logger;

    /** @var string */
    protected $meta_key = 'fifu_image_alt';

    /** @var string */
    protected $table_postmeta;

    /** @var string */
    protected $table_fifu_alt;

    /** @var string */
    protected $table_fifu_key;

    /** @var string */
    protected $table_fifu_alt_map;

    /** @var int|null */
    protected $image_key_id = null;

    public function __construct( ?wpdb $wpdb = null, ?Fifu_Migration_State $state = null, ?Fifu_Migration_Logger $logger = null ) {
        if ( null === $wpdb ) {
            global $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            $this->wpdb = $wpdb;
        } else {
            $this->wpdb = $wpdb;
        }

        $this->state  = $state  ?? new Fifu_Migration_State();
        $this->logger = $logger ?? new Fifu_Migration_Logger();

        $this->table_postmeta = $this->wpdb->postmeta;
        $this->table_fifu_alt = $this->wpdb->prefix . 'fifu_alt';
        $this->table_fifu_key = $this->wpdb->prefix . 'fifu_key';
        $this->table_fifu_alt_map = $this->wpdb->prefix . 'fifu_alt_map';
    }

    public function get_name(): string {
        return 'alt_featured';
    }

    public function get_label(): string {
        return 'Featured image ALT migration';
    }

    public function run_batch( int $limit, int $time_limit_seconds ): void {
        $limit              = max( 1, $limit );
        $time_limit_seconds = max( 1, $time_limit_seconds );

        $state   = $this->state->get_task_state( $this->get_name() );
        $last_id = isset( $state['last_id'] ) ? (int) $state['last_id'] : 0;

        $this->logger->info( 'Starting featured alt batch.', array(
            'limit'      => $limit,
            'time_limit' => $time_limit_seconds,
            'last_id'    => $last_id,
        ) );

        $this->image_key_id = $this->get_image_key_id();
        if ( null === $this->image_key_id ) {
            $this->logger->error( 'Missing "image" key, cannot proceed with featured alt migration.' );
            return;
        }

        $query = $this->wpdb->prepare(
            "SELECT meta_id, post_id, meta_value AS alt FROM {$this->table_postmeta} WHERE meta_id > %d AND meta_key = %s ORDER BY meta_id ASC LIMIT %d",
            $last_id,
            $this->meta_key,
            $limit
        );

        $rows = $this->wpdb->get_results( $query, ARRAY_A );
        if ( false === $rows ) {
            $this->logger->error( 'Failed to fetch legacy featured alt meta rows.' );
            return;
        }

        if ( empty( $rows ) ) {
            $this->logger->info( 'No legacy featured alts found, marking task as finished.' );
            $this->state->update_task_state( $this->get_name(), array( 'status' => 'finished' ) );
            return;
        }

        $processed_in_batch   = 0;
        $error_count_in_batch = 0;
        $last_meta_id_in_batch = $last_id;
        $start_time            = microtime( true );
        $rows_count            = count( $rows );
        $items                 = array();

        foreach ( $rows as $row ) {
            if ( microtime( true ) - $start_time >= $time_limit_seconds ) {
                break;
            }

            $meta_id = isset( $row['meta_id'] ) ? (int) $row['meta_id'] : 0;
            $post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
            $alt = Fifu_Db2_Normalizer::normalize_alt( isset( $row['alt'] ) ? (string) $row['alt'] : null );

            if ( null === $alt ) {
                $last_meta_id_in_batch = $meta_id;
                continue;
            }

            $items[] = array(
                'meta_id'   => $meta_id,
                'post_id'   => $post_id,
                'key_id'    => $this->image_key_id,
                'key_index' => 0,
                'hash'      => md5( $alt ),
                'alt'       => $alt,
            );
        }

        foreach ( $this->group_items_by_post_id( $items ) as $post_items ) {
            if ( microtime( true ) - $start_time >= $time_limit_seconds ) {
                break;
            }

            if ( empty( $post_items ) ) {
                continue;
            }

            $post_items_count = count( $post_items );

            if ( ! $this->start_transaction() ) {
                $error_count_in_batch += $post_items_count;
                break;
            }

            if ( ! $this->bulk_insert_alts( $post_items ) ) {
                $this->rollback_transaction();
                $error_count_in_batch += $post_items_count;
                break;
            }

            if ( ! $this->bulk_upsert_alt_maps( $post_items ) ) {
                $this->rollback_transaction();
                $error_count_in_batch += $post_items_count;
                break;
            }

            if ( ! $this->bulk_delete_legacy_postmeta_rows( $post_items ) ) {
                $this->rollback_transaction();
                $error_count_in_batch += $post_items_count;
                break;
            }

            if ( ! $this->commit_transaction() ) {
                $this->rollback_transaction();
                $error_count_in_batch += $post_items_count;
                break;
            }

            $processed_in_batch += $post_items_count;
            $last_meta_id_in_batch = max(
                $last_meta_id_in_batch,
                $this->last_meta_id_from_items( $post_items )
            );
            $this->clear_post_meta_cache_for_items( $post_items );
        }

        $current_state   = $this->state->get_task_state( $this->get_name() );
        $total_processed = (int) ( $current_state['processed_count'] ?? 0 ) + $processed_in_batch;
        $total_errors    = (int) ( $current_state['error_count'] ?? 0 ) + $error_count_in_batch;
        $status          = 'running';

        if ( 0 === $processed_in_batch && 0 === $error_count_in_batch && $rows_count < $limit ) {
            $status = 'finished';
        }

        $this->state->update_task_state( $this->get_name(), array(
            'status'          => $status,
            'processed_count' => $total_processed,
            'error_count'     => $total_errors,
            'last_id'         => $last_meta_id_in_batch,
        ) );

        $this->logger->info( 'Finished featured alt batch.', array(
            'processed' => $processed_in_batch,
            'errors'    => $error_count_in_batch,
            'last_id'   => $last_meta_id_in_batch,
            'status'    => $status,
        ) );
    }

    public function is_finished(): bool {
        $state = $this->state->get_task_state( $this->get_name() );

        return isset( $state['status'] ) && 'finished' === $state['status'];
    }

    protected function get_image_key_id(): ?int {
        $query = $this->wpdb->prepare(
            "SELECT key_id FROM {$this->table_fifu_key} WHERE key_type = %s LIMIT 1",
            'image'
        );

        $key_id = $this->wpdb->get_var( $query );

        if ( null === $key_id ) {
            return null;
        }

        return (int) $key_id;
    }

    protected function insert_alt_if_not_exists( string $hash, string $alt ): bool {
        $query = $this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->table_fifu_alt} (hash, alt) VALUES (%s, %s)",
            $hash,
            $alt
        );

        $result = $this->wpdb->query( $query );

        if ( false === $result ) {
            $this->logger->error( 'Failed to insert ALT record.', array( 'hash' => $hash ) );
            return false;
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function group_items_by_post_id( array $items ): array {
        $groups = array();

        foreach ( $items as $item ) {
            $post_id = (int) $item['post_id'];

            if ( ! isset( $groups[ $post_id ] ) ) {
                $groups[ $post_id ] = array();
            }

            $groups[ $post_id ][] = $item;
        }

        return array_values( $groups );
    }

    protected function start_transaction(): bool {
        return false !== $this->wpdb->query( 'START TRANSACTION' );
    }

    protected function commit_transaction(): bool {
        return false !== $this->wpdb->query( 'COMMIT' );
    }

    protected function rollback_transaction(): void {
        $this->wpdb->query( 'ROLLBACK' );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function last_meta_id_from_items( array $items ): int {
        $last_meta_id = 0;

        foreach ( $items as $item ) {
            $last_meta_id = max( $last_meta_id, (int) $item['meta_id'] );
        }

        return $last_meta_id;
    }

    /**
     * Keeps only the latest item for each post/key/index ALT map target.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    protected function latest_items_by_map_key( array $items ): array {
        $latest = array();

        foreach ( $items as $item ) {
            $map_key = implode( '|', array(
                (int) $item['post_id'],
                (int) $item['key_id'],
                (int) $item['key_index'],
            ) );

            if ( ! isset( $latest[ $map_key ] ) || (int) $item['meta_id'] >= (int) $latest[ $map_key ]['meta_id'] ) {
                $latest[ $map_key ] = $item;
            }
        }

        return array_values( $latest );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function bulk_insert_alts( array $items ): bool {
        if ( empty( $items ) ) {
            return true;
        }

        $placeholders = array();
        $args = array();

        foreach ( $items as $item ) {
            $placeholders[] = '(%s, %s)';
            $args[] = (string) $item['hash'];
            $args[] = (string) $item['alt'];
        }

        $query = $this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->table_fifu_alt} (hash, alt) VALUES " . implode( ', ', $placeholders ),
            ...$args
        );

        $result = $this->wpdb->query( $query );

        if ( false === $result ) {
            $this->logger->error( $this->get_alt_insert_error_message() );
            return false;
        }

        return true;
    }

    protected function get_alt_insert_error_message(): string {
        return 'Failed to insert ALT record.';
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function bulk_upsert_alt_maps( array $items ): bool {
        $map_items = $this->latest_items_by_map_key( $items );

        if ( empty( $map_items ) ) {
            return true;
        }

        $placeholders = array();
        $args = array();

        foreach ( $map_items as $item ) {
            $placeholders[] = '(%d, %d, %d, %s)';
            $args[] = (int) $item['post_id'];
            $args[] = (int) $item['key_id'];
            $args[] = (int) $item['key_index'];
            $args[] = (string) $item['hash'];
        }

        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->table_fifu_alt_map} (post_id, key_id, key_index, hash) VALUES " . implode( ', ', $placeholders ) . ' ON DUPLICATE KEY UPDATE hash = VALUES(hash)',
            ...$args
        );

        $result = $this->wpdb->query( $query );

        if ( false === $result ) {
            $this->logger->error( $this->get_alt_map_upsert_error_message() );
            return false;
        }

        return true;
    }

    protected function get_alt_map_upsert_error_message(): string {
        return 'Failed to upsert featured alt map.';
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function bulk_delete_legacy_postmeta_rows( array $items ): bool {
        $meta_ids = array_values( array_unique( array_filter(
            array_map( static fn( array $item ): int => (int) $item['meta_id'], $items ),
            static fn( int $meta_id ): bool => $meta_id > 0
        ) ) );

        if ( empty( $meta_ids ) ) {
            return false;
        }

        $placeholders = implode( ', ', array_fill( 0, count( $meta_ids ), '%d' ) );

        $query = $this->wpdb->prepare(
            "DELETE FROM {$this->table_postmeta} WHERE meta_id IN ({$placeholders})",
            ...$meta_ids
        );

        return false !== $this->wpdb->query( $query );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function clear_post_meta_cache_for_items( array $items ): void {
        $post_ids = array();

        foreach ( $items as $item ) {
            $post_id = (int) $item['post_id'];
            if ( $post_id > 0 ) {
                $post_ids[ $post_id ] = true;
            }
        }

        foreach ( array_keys( $post_ids ) as $post_id ) {
            wp_cache_delete( $post_id, 'post_meta' );
        }
    }

    protected function upsert_map( int $post_id, int $key_id, int $key_index, string $hash ): bool {
        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->table_fifu_alt_map} (post_id, key_id, key_index, hash) VALUES (%d, %d, %d, %s) ON DUPLICATE KEY UPDATE hash = VALUES(hash)",
            $post_id,
            $key_id,
            $key_index,
            $hash
        );

        $result = $this->wpdb->query( $query );

        if ( false === $result ) {
            $this->logger->error( 'Failed to upsert featured alt map.', array(
                'post_id' => $post_id,
                'hash'    => $hash,
            ) );
            return false;
        }

        return true;
    }

    protected function delete_legacy_postmeta_row( int $meta_id, int $post_id ): bool {
        if ( $meta_id <= 0 ) {
            return false;
        }

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_postmeta} WHERE meta_id = %d",
                $meta_id
            )
        );

        return false !== $result;
    }
}
