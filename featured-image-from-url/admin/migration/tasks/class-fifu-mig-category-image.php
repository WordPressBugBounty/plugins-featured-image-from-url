<?php
declare( strict_types=1 );

/**
 * Handles migration of legacy term image URL metadata.
 */
class Fifu_Mig_Category_Image implements Fifu_Migration_Task_Interface {

    /** @var wpdb */
    protected $wpdb;

    /** @var Fifu_Migration_State */
    protected $state;

    /** @var Fifu_Migration_Logger */
    protected $logger;

    /** @var string */
    protected $meta_key = 'fifu_image_url';

    /** @var string */
    protected $table_termmeta;

    /** @var string */
    protected $table_fifu_url;

    /** @var string */
    protected $table_fifu_key;

    /** @var string */
    protected $table_fifu_term_map;

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

        $this->table_termmeta      = $this->wpdb->termmeta;
        $this->table_fifu_url      = $this->wpdb->prefix . 'fifu_url';
        $this->table_fifu_key      = $this->wpdb->prefix . 'fifu_key';
        $this->table_fifu_term_map = $this->wpdb->prefix . 'fifu_term_map';
    }

    /**
     * {@inheritdoc}
     */
    public function get_name(): string {
        return 'category_image';
    }

    /**
     * {@inheritdoc}
     */
    public function get_label(): string {
        return 'Term image URL migration';
    }

    /**
     * {@inheritdoc}
     */
    public function run_batch( int $limit, int $time_limit_seconds ): void {
        $limit              = max( 1, $limit );
        $time_limit_seconds = max( 1, $time_limit_seconds );

        $state   = $this->state->get_task_state( $this->get_name() );
        $last_id = isset( $state['last_id'] ) ? (int) $state['last_id'] : 0;

        $this->logger->info(
            sprintf(
                'Starting category_image batch. %s',
                wp_json_encode(
                    array(
                        'limit'      => $limit,
                        'time_limit' => $time_limit_seconds,
                        'last_id'    => $last_id,
                    )
                )
            )
        );

        $this->image_key_id = $this->get_image_key_id();
        if ( null === $this->image_key_id ) {
            $this->logger->error( 'Missing "image" key, cannot proceed with category image migration.' );
            return;
        }

        $query = $this->wpdb->prepare(
            "SELECT meta_id, term_id, meta_value AS url FROM {$this->table_termmeta} WHERE meta_id > %d AND meta_key = %s ORDER BY meta_id ASC LIMIT %d",
            $last_id,
            $this->meta_key,
            $limit
        );

        $rows = $this->wpdb->get_results( $query, ARRAY_A );
        if ( false === $rows ) {
            $this->logger->error( 'Failed to fetch legacy term meta rows.' );
            return;
        }

        $processed_in_batch    = 0;
        $errors_in_batch       = 0;
        $last_meta_id_in_batch = $last_id;
        $start_time            = microtime( true );
        $rows_count            = count( $rows );

        foreach ( $rows as $row ) {
            if ( microtime( true ) - $start_time >= $time_limit_seconds ) {
                break;
            }

            $meta_id = isset( $row['meta_id'] ) ? (int) $row['meta_id'] : 0;
            $term_id = isset( $row['term_id'] ) ? (int) $row['term_id'] : 0;
            $url = Fifu_Db2_Normalizer::normalize_url( isset( $row['url'] ) ? (string) $row['url'] : null );

            if ( null === $url ) {
                $last_meta_id_in_batch = $meta_id;
                continue;
            }

            $hash = md5( $url );

            if ( ! $this->upsert_url( $hash, $url ) ) {
                $errors_in_batch++;
                $last_meta_id_in_batch = $meta_id;
                continue;
            }

            if ( ! $this->upsert_term_map( $term_id, $this->image_key_id, $hash ) ) {
                $errors_in_batch++;
                $last_meta_id_in_batch = $meta_id;
                continue;
            }

            if ( $this->delete_legacy_termmeta_row( $meta_id, $term_id ) ) {
                wp_cache_delete( $term_id, 'term_meta' );
            }

            $processed_in_batch++;
            $last_meta_id_in_batch = $meta_id;
        }

        $current_state   = $this->state->get_task_state( $this->get_name() );
        $total_processed = (int) ( $current_state['processed_count'] ?? 0 ) + $processed_in_batch;
        $total_errors    = (int) ( $current_state['error_count'] ?? 0 ) + $errors_in_batch;
        $status          = 'running';

        $last_id_for_state = $last_meta_id_in_batch > 0 ? $last_meta_id_in_batch : $last_id;

        if ( 0 === $processed_in_batch && $rows_count < $limit ) {
            $status = 'finished';
            $this->logger->info( 'No legacy term image URLs found, marking task as finished.' );
        } else {
            $this->logger->info(
                sprintf(
                    'Finished category_image batch. %s',
                    wp_json_encode(
                        array(
                            'processed' => $processed_in_batch,
                            'errors'    => $errors_in_batch,
                            'last_id'   => $last_meta_id_in_batch,
                            'status'    => $status,
                        )
                    )
                )
            );
        }

        $this->state->update_task_state( $this->get_name(), array(
            'status'          => $status,
            'processed_count' => $total_processed,
            'error_count'     => $total_errors,
            'last_id'         => $last_id_for_state,
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
    protected function upsert_url( string $hash, string $url ): bool {
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
     * Inserts or updates the mapping between term/key and URL.
     */
    protected function upsert_term_map( int $term_id, int $key_id, string $hash ): bool {
        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->table_fifu_term_map} (term_id, key_id, hash) VALUES (%d, %d, %s) ON DUPLICATE KEY UPDATE hash = VALUES(hash)",
            $term_id,
            $key_id,
            $hash
        );

        $result = $this->wpdb->query( $query );

        if ( false === $result ) {
            $this->logger->error( 'Failed to upsert term image map.', array(
                'term_id' => $term_id,
                'hash'    => $hash,
            ) );
            return false;
        }

        return true;
    }

    /**
     * Removes the legacy termmeta row after migrating it to DB2.
     *
     * @param int $meta_id
     * @return void
     */
    protected function delete_legacy_termmeta_row( int $meta_id, int $term_id ): bool {
        if ( $meta_id <= 0 ) {
            return false;
        }

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_termmeta} WHERE meta_id = %d",
                $meta_id
            )
        );

        return false !== $result;
    }
}
