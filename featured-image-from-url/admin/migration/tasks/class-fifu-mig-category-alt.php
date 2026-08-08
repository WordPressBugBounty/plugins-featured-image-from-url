<?php
declare( strict_types=1 );

class Fifu_Mig_Category_Alt implements Fifu_Migration_Task_Interface {

    /** @var wpdb */
    protected $wpdb;

    /** @var Fifu_Migration_State */
    protected $state;

    /** @var Fifu_Migration_Logger */
    protected $logger;

    /** @var string */
    protected $meta_key = 'fifu_image_alt';

    /** @var string */
    protected $table_termmeta;

    /** @var string */
    protected $alt_table;

    /** @var Fifu_Db2_Write_Service|null */
    private $write_service;

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
        $this->table_termmeta = $this->wpdb->termmeta;
    }

    /**
     * {@inheritdoc}
     */
    public function get_name(): string {
        return 'category_alt';
    }

    /**
     * {@inheritdoc}
     */
    public function get_label(): string {
        return 'Term ALT migration';
    }

    /**
     * {@inheritdoc}
     */
    public function run_batch( int $limit, int $time_limit_seconds ): void {
        $limit              = max( 1, $limit );
        $time_limit_seconds = max( 1, $time_limit_seconds );

        $write_service = $this->ensure_write_service();
        if ( null === $write_service ) {
            $this->logger->error( 'DB2 write service unavailable for term alt migration.' );
            return;
        }

        $state   = $this->state->get_task_state( $this->get_name() );
        $last_id = isset( $state['last_id'] ) ? (int) $state['last_id'] : 0;

        $this->logger->info( sprintf(
            'Starting %s batch.',
            $this->get_name()
        ), array(
            'limit'   => $limit,
            'last_id' => $last_id,
        ) );

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT meta_id, term_id, meta_value AS alt FROM {$this->table_termmeta} WHERE meta_id > %d AND meta_key = %s ORDER BY meta_id ASC LIMIT %d",
                $last_id,
                $this->meta_key,
                $limit
            ),
            ARRAY_A
        );

        if ( false === $rows ) {
            $this->logger->error( 'Failed to fetch legacy term alt meta rows.' );
            return;
        }

        if ( empty( $rows ) ) {
            $this->logger->info( 'No legacy term alts found, marking task as finished.' );
            $this->state->update_task_state( $this->get_name(), array( 'status' => 'finished' ) );
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
            $alt     = isset( $row['alt'] ) ? trim( (string) $row['alt'] ) : '';

            if ( '' === $alt ) {
                $last_meta_id_in_batch = $meta_id;
                continue;
            }

            if ( ! $write_service->save_term_image_alt( $term_id, $alt ) ) {
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

        if ( 0 === $processed_in_batch && $rows_count < $limit ) {
            $status = 'finished';
        }

        $this->state->update_task_state( $this->get_name(), array(
            'status'          => $status,
            'processed_count' => $total_processed,
            'error_count'     => $total_errors,
            'last_id'         => $last_meta_id_in_batch,
        ) );

        $this->logger->info( sprintf(
            'Finished %s batch.',
            $this->get_name()
        ), array(
            'processed' => $processed_in_batch,
            'errors'    => $errors_in_batch,
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
     * @return Fifu_Db2_Write_Service|null
     */
    private function ensure_write_service(): ?Fifu_Db2_Write_Service {
        if ( null !== $this->write_service ) {
            return $this->write_service;
        }

        if ( ! function_exists( 'fifu_db2_write_service' ) ) {
            return null;
        }

        $service = fifu_db2_write_service();
        if ( ! $service instanceof Fifu_Db2_Write_Service ) {
            return null;
        }

        $this->write_service = $service;
        return $service;
    }

    /**
     * @param int $meta_id
     * @return void
     */
    private function delete_legacy_termmeta_row( int $meta_id, int $term_id ): bool {
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
