<?php
/**
 * Executes the schema SQL files to keep the migration tables up to date.
 */
class Fifu_Schema_Manager {
	/** @var wpdb */
	private $wpdb;

	/** @var string */
	private $schema_dir;

	/** @var string|null */
	private $current_file;

	/** @var bool */
	private $uses_default_schema_dir = false;

	/**
	 * @param wpdb|null $wpdb
	 * @param string    $schema_dir
	 */
	public function __construct( ?wpdb $wpdb = null, string $schema_dir = '' ) {
		$default_schema_dir = __DIR__ . '/../schema';

		if ( null === $wpdb ) {
			global $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$this->wpdb = $wpdb;
		} else {
			$this->wpdb = $wpdb;
		}

		if ( '' === $schema_dir ) {
			$schema_dir = $default_schema_dir;
			$this->uses_default_schema_dir = true;
		} else {
			$default_resolved = realpath( $default_schema_dir );
			$custom_resolved  = realpath( $schema_dir );
			$this->uses_default_schema_dir = false !== $default_resolved && false !== $custom_resolved && $default_resolved === $custom_resolved;
		}

		$resolved = realpath( $schema_dir );
		$this->schema_dir = false === $resolved ? rtrim( $schema_dir, '/\\' ) : $resolved;
	}

	/**
	 * Runs every SQL file inside the schema directory in order.
	 */
	public function run_all(): bool {
		$success = true;

		foreach ( $this->get_schema_files() as $file ) {
			$this->current_file = $file;
			$sql = $this->load_sql_from_file( $file );
			if ( '' === trim( $sql ) ) {
				continue;
			}

			$prepared = $this->prepare_sql( $sql );
			if ( '' === trim( $prepared ) ) {
				continue;
			}

			$file_success = $this->execute_sql( $prepared );
			$success = $file_success && $success;
		}

		if ( $this->uses_default_schema_dir ) {
			$repair_success = $this->repair_timestamp_columns();
			$success = $repair_success && $success;
		}

		return $success;
	}

	/**
	 * Runs only the requested schema files without the full timestamp repair pass.
	 *
	 * @param string[] $filenames
	 * @return bool True when every requested schema operation succeeds.
	 */
	public function run_files( array $filenames ): bool {
		$success = true;

		foreach ( $filenames as $filename ) {
			$file = $this->schema_dir . '/' . basename( $filename );
			if ( ! is_file( $file ) ) {
				$success = false;
				continue;
			}

			$this->current_file = $file;
			$sql = $this->load_sql_from_file( $file );
			if ( '' === trim( $sql ) ) {
				continue;
			}

			$prepared = $this->prepare_sql( $sql );
			if ( '' !== trim( $prepared ) ) {
				$file_success = $this->execute_sql( $prepared );
				$success = $file_success && $success;
			}
		}

		return $success;
	}

	/**
	 * Repairs and verifies only the URL validation schema used by the link checker.
	 *
	 * @return bool True when all required URL validation columns and the queue index exist.
	 */
	public function repair_url_validation_schema(): bool {
		$table = $this->wpdb->prefix . 'fifu_url';

		if ( ! $this->table_exists( $table ) ) {
			return false;
		}

		$success = true;
		$columns = array(
			'validation_failures'    => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
			'validation_checked_at'  => 'DATETIME NULL',
			'validation_queued_at'   => 'DATETIME NULL',
		);

		foreach ( $columns as $column => $definition ) {
			$column_success = $this->add_column_if_missing( $table, $column, $definition );
			$success = $column_success && $success;
		}

		if ( $this->column_exists( $table, 'validation_queued_at' ) ) {
			$index_success = $this->add_index_if_missing( $table, 'idx_validation_queued_at', '(validation_queued_at)' );
			$success = $index_success && $success;
		} else {
			$success = false;
		}

		if ( ! $success ) {
			return false;
		}

		foreach ( array_keys( $columns ) as $column ) {
			if ( ! $this->column_exists( $table, $column ) ) {
				return false;
			}
		}

		$verified = $this->index_exists( $table, 'idx_validation_queued_at' );
		if ( $verified && class_exists( 'Fifu_Db2_Url_Validation_Schema', false ) ) {
			Fifu_Db2_Url_Validation_Schema::forget( $this->wpdb, $table );
		}

		return $verified;
	}

	/**
	 * @return string[]
	 */
	protected function get_schema_files(): array {
		if ( ! is_dir( $this->schema_dir ) ) {
			return [];
		}

		$files = glob( $this->schema_dir . '/*.sql' );
		if ( false === $files ) {
			return [];
		}

		natsort( $files );
		$files = array_values( $files );

		return $files;
	}

	/**
	 * @param string $file
	 *
	 * @return string
	 */
	protected function load_sql_from_file( string $file ): string {
		$content = file_get_contents( $file );

		return false === $content ? '' : $content;
	}

	/**
	 * @param string $sql
	 *
	 * @return string
	 */
	protected function prepare_sql( string $sql ): string {
		return str_replace(
			array( '{PREFIX}', '{CHARSET_COLLATE}' ),
			array( $this->wpdb->prefix, $this->wpdb->get_charset_collate() ),
			$sql
		);
	}

	/**
	 * @param string $sql
	 */
	protected function execute_sql( string $sql ): bool {
		$success = true;
		$statements = array_filter(
			array_map( 'trim', explode( ';', $sql ) )
		);

		$file = $this->current_file ?? 'unknown';

		foreach ( $statements as $statement ) {
			if ( '' === $statement ) {
				continue;
			}

			$result = $this->wpdb->query( $statement );
			if ( false === $result ) {
				$success = false;
				$snippet = substr( $statement, 0, 200 );
				error_log(
					sprintf(
						'Fifu_Schema_Manager: failed to execute %s (%s) – SQL: %s',
						$file,
						$this->wpdb->last_error,
						$snippet
					)
				);
			}
		}

		return $success;
	}

	/**
	 * Repairs timestamp columns that can be missing on upgraded installs.
	 */
	protected function repair_timestamp_columns(): bool {
		$success = true;
		$repairs = [
			'fifu_url'  => [
				'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
				'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
			],
			'fifu_alt'  => [
				'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
				'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
			],
			'fifu_sent' => [
				'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
			],
		];

		foreach ( $repairs as $table_suffix => $columns ) {
			$table = $this->wpdb->prefix . $table_suffix;

			if ( ! $this->table_exists( $table ) ) {
				continue;
			}

			foreach ( $columns as $column => $definition ) {
				$column_success = $this->add_column_if_missing( $table, $column, $definition );
				$success = $column_success && $success;
			}

			if ( 'fifu_url' === $table_suffix ) {
				foreach ( [ 'is_valid', 'validation_attempts', 'validation_last_attempt' ] as $obsolete_column ) {
					$drop_success = $this->drop_column_if_exists( $table, $obsolete_column );
					$success = $drop_success && $success;
				}

				$validation_success = $this->repair_url_validation_schema();
				$success = $validation_success && $success;
			}
		}

		return $success;
	}

	/**
	 * Adds a column when it is missing.
	 *
	 * @param string $table
	 * @param string $column
	 * @param string $definition
	 */
	protected function add_column_if_missing( string $table, string $column, string $definition ): bool {
		if ( $this->column_exists( $table, $column ) ) {
			return true;
		}

		$result = $this->wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}" );

		if ( false === $result ) {
			error_log(
				sprintf(
					'Fifu_Schema_Manager: failed to repair %s.%s (%s)',
					$table,
					$column,
					$this->wpdb->last_error
				)
			);

			return false;
		}

		return true;
	}

	/**
	 * Drops an obsolete column when it exists.
	 *
	 * @param string $table
	 * @param string $column
	 */
	protected function drop_column_if_exists( string $table, string $column ): bool {
		if ( ! $this->column_exists( $table, $column ) ) {
			return true;
		}

		$result = $this->wpdb->query( "ALTER TABLE {$table} DROP COLUMN {$column}" );
		if ( false === $result ) {
			error_log( sprintf( 'Fifu_Schema_Manager: failed to remove obsolete %s.%s (%s)', $table, $column, $this->wpdb->last_error ) );
			return false;
		}

		return true;
	}

	/**
	 * Adds an index when it is missing.
	 *
	 * @param string $table
	 * @param string $index
	 * @param string $columns
	 */
	protected function add_index_if_missing( string $table, string $index, string $columns ): bool {
		if ( $this->index_exists( $table, $index ) ) {
			return true;
		}

		$result = $this->wpdb->query( "ALTER TABLE {$table} ADD KEY {$index} {$columns}" );
		if ( false === $result ) {
			error_log( sprintf( 'Fifu_Schema_Manager: failed to add index %s on %s (%s)', $index, $table, $this->wpdb->last_error ) );
			return false;
		}

		return true;
	}

	/**
	 * Checks whether a table exists in the current database.
	 *
	 * @param string $table
	 * @return bool
	 */
	protected function table_exists( string $table ): bool {
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		return $result === $table;
	}

	/**
	 * Checks whether a column exists in the current database.
	 *
	 * @param string $table
	 * @param string $column
	 * @return bool
	 */
	protected function column_exists( string $table, string $column ): bool {
		$sql = $this->wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column );

		return null !== $this->wpdb->get_var( $sql );
	}

	/**
	 * Checks whether an index exists in the current database.
	 *
	 * @param string $table
	 * @param string $index
	 * @return bool
	 */
	protected function index_exists( string $table, string $index ): bool {
		$sql = $this->wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $index );
		return null !== $this->wpdb->get_var( $sql );
	}
}
