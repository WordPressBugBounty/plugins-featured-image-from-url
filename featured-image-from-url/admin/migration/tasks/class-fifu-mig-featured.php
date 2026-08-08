<?php
declare( strict_types=1 );

/**
 * Handles migration of the legacy FIFU featured image URL metadata.
 */
class Fifu_Mig_Featured implements Fifu_Migration_Task_Interface {

    /** @var wpdb */
    protected $wpdb;

    /** @var Fifu_Migration_State */
    protected $state;

    /** @var Fifu_Migration_Logger */
    protected $logger;

    /** @var string */
    protected $meta_key = 'fifu_image_url';

    /** @var string */
    protected $table_postmeta;

    /** @var string */
    protected $table_fifu_url;

    /** @var string */
    protected $table_fifu_key;

    /** @var string */
    protected $table_fifu_map;

    /** @var int|null */
    protected $image_key_id = null;

    /**
     * @param wpdb|null                 $wpdb
     * @param Fifu_Migration_State|null $state
     * @param Fifu_Migration_Logger|null $logger
     */
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
        $this->table_fifu_url = $this->wpdb->prefix . 'fifu_url';
        $this->table_fifu_key = $this->wpdb->prefix . 'fifu_key';
        $this->table_fifu_map = $this->wpdb->prefix . 'fifu_map';
    }

    /**
     * {@inheritdoc}
     */
    public function get_name(): string {
        return 'featured';
    }

    /**
     * {@inheritdoc}
     */
    public function get_label(): string {
        return 'Featured image URL migration';
    }

    /**
     * {@inheritdoc}
     */
    public function run_batch( int $limit, int $time_limit_seconds ): void {
        $limit              = max( 1, $limit );
        $time_limit_seconds = max( 1, $time_limit_seconds );

        $state   = $this->state->get_task_state( $this->get_name() );
        $last_id = isset( $state['last_id'] ) ? (int) $state['last_id'] : 0;

        $this->logger->info( 'Starting featured batch.', array(
            'limit'      => $limit,
            'time_limit' => $time_limit_seconds,
            'last_id'    => $last_id,
        ) );

        $this->image_key_id = $this->get_image_key_id();
        if ( null === $this->image_key_id ) {
            $this->logger->error( 'Missing "image" key, cannot proceed with featured migration.' );
            return;
        }

        $query = $this->wpdb->prepare(
            "SELECT meta_id, post_id, meta_value AS url FROM {$this->table_postmeta} WHERE meta_id > %d AND meta_key = %s ORDER BY meta_id ASC LIMIT %d",
            $last_id,
            $this->meta_key,
            $limit
        );

        $rows = $this->wpdb->get_results( $query, ARRAY_A );
        if ( false === $rows ) {
            $this->logger->error( 'Failed to fetch legacy featured meta rows.' );
            return;
        }

        if ( empty( $rows ) ) {
            $this->logger->info( 'No legacy featured URLs found, marking task as finished.' );
            $this->state->update_task_state( $this->get_name(), array( 'status' => 'finished' ) );
            return;
        }

        $processed_in_batch    = 0;
        $error_count_in_batch  = 0;
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
            $url = Fifu_Db2_Normalizer::normalize_url( isset( $row['url'] ) ? (string) $row['url'] : null );

            if ( null === $url ) {
                $last_meta_id_in_batch = $meta_id;
                continue;
            }

            $items[] = array(
                'meta_id'   => $meta_id,
                'post_id'   => $post_id,
                'key_id'    => $this->image_key_id,
                'key_index' => 0,
                'hash'      => md5( $url ),
                'url'       => $url,
            );
        }

        if ( ! empty( $items ) ) {
            $post_groups = $this->group_items_by_post_id( $items );

            foreach ( $this->chunk_post_groups( $post_groups ) as $post_group_chunk ) {
                if ( microtime( true ) - $start_time >= $time_limit_seconds ) {
                    break;
                }

                $chunk_items = $this->flatten_post_groups( $post_group_chunk );

                if ( empty( $chunk_items ) ) {
                    continue;
                }

                $chunk_items_count = count( $chunk_items );

                if ( ! $this->start_transaction() ) {
                    $error_count_in_batch += $chunk_items_count;
                    break;
                }

                if ( ! $this->bulk_insert_urls( $chunk_items ) ) {
                    $this->rollback_transaction();
                    $error_count_in_batch += $chunk_items_count;
                    break;
                }

                if ( ! $this->bulk_upsert_maps( $chunk_items ) ) {
                    $this->rollback_transaction();
                    $error_count_in_batch += $chunk_items_count;
                    break;
                }

                if ( ! $this->bulk_delete_legacy_postmeta_rows( $chunk_items ) ) {
                    $this->rollback_transaction();
                    $error_count_in_batch += $chunk_items_count;
                    break;
                }

                if ( ! $this->commit_transaction() ) {
                    $this->rollback_transaction();
                    $error_count_in_batch += $chunk_items_count;
                    break;
                }

                $processed_in_batch += $chunk_items_count;
                $last_meta_id_in_batch = max(
                    $last_meta_id_in_batch,
                    $this->last_meta_id_from_items( $chunk_items )
                );

                $this->clear_post_meta_cache_for_items( $chunk_items );
            }
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

        $this->logger->info( 'Finished featured batch.', array(
            'processed' => $processed_in_batch,
            'errors'    => $error_count_in_batch,
            'last_id'   => $last_meta_id_in_batch,
            'status'    => $status,
        ) );
    }

    /**
     * {@inheritdoc}
     */
    public function is_finished(): bool {
        $state = $this->state->get_task_state( $this->get_name() );

        return isset( $state['status'] ) && 'finished' === $state['status'];
    }

    /**
     * Retrieves the image key ID from the schema table.
     */
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

    /**
     * Inserts a URL record only if it does not already exist.
     */
    protected function insert_url_if_not_exists( string $hash, string $url ): bool {
        $query = $this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->table_fifu_url} (hash, url) VALUES (%s, %s)",
            $hash,
            $url
        );

        $result = $this->wpdb->query( $query );

        if ( false === $result ) {
            $this->logger->error( 'Failed to insert URL record.', array( 'hash' => $hash ) );
            return false;
        }

        return true;
    }

    /**
     * Inserts or updates the mapping between post/key and URL.
     */
    protected function upsert_map( int $post_id, int $key_id, int $key_index, string $hash ): bool {
        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->table_fifu_map} (post_id, key_id, key_index, hash) VALUES (%d, %d, %d, %s) ON DUPLICATE KEY UPDATE hash = VALUES(hash)",
            $post_id,
            $key_id,
            $key_index,
            $hash
        );

        $result = $this->wpdb->query( $query );

        if ( false === $result ) {
            $this->logger->error( 'Failed to upsert featured map.', array(
                'post_id' => $post_id,
                'hash'    => $hash,
            ) );
            return false;
        }

        return true;
    }

    /**
     * Removes the legacy postmeta row after migrating it to DB2.
     *
     * @param int $meta_id
     * @return void
     */
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

    protected function get_post_group_chunk_size(): int {
        return 50;
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $post_groups
     * @return array<int, array<int, array<int, array<string, mixed>>>>
     */
    protected function chunk_post_groups( array $post_groups ): array {
        $chunk_size = max( 1, $this->get_post_group_chunk_size() );

        return array_chunk( $post_groups, $chunk_size );
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $post_groups
     * @return array<int, array<string, mixed>>
     */
    protected function flatten_post_groups( array $post_groups ): array {
        $items = array();

        foreach ( $post_groups as $post_items ) {
            foreach ( $post_items as $item ) {
                $items[] = $item;
            }
        }

        return $items;
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
     * Inserts all URL records for a batch in a single query.
     *
     * @param array<int, array<string, mixed>> $items
     */
    protected function bulk_insert_urls( array $items ): bool {
        if ( empty( $items ) ) {
            return true;
        }

        $placeholders = array();
        $args = array();

        foreach ( $items as $item ) {
            $placeholders[] = '(%s, %s)';
            $args[] = (string) $item['hash'];
            $args[] = (string) $item['url'];
        }

        $query = $this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->table_fifu_url} (hash, url) VALUES " . implode( ', ', $placeholders ),
            ...$args
        );

        $result = $this->wpdb->query( $query );

        if ( false === $result ) {
            $this->logger->error( 'Failed to insert URL record.' );
            return false;
        }

        return true;
    }

    /**
     * Upserts all map rows for a batch in a single query.
     *
     * @param array<int, array<string, mixed>> $items
     */
    protected function bulk_upsert_maps( array $items ): bool {
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
            "INSERT INTO {$this->table_fifu_map} (post_id, key_id, key_index, hash) VALUES " . implode( ', ', $placeholders ) . ' ON DUPLICATE KEY UPDATE hash = VALUES(hash)',
            ...$args
        );

        $result = $this->wpdb->query( $query );

        if ( false === $result ) {
            $this->logger->error( 'Failed to upsert featured map.' );
            return false;
        }

        return true;
    }

    /**
     * Keeps only the latest item for each post/key/index map target.
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
     * Deletes all migrated legacy rows for a batch in a single query.
     *
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
}
