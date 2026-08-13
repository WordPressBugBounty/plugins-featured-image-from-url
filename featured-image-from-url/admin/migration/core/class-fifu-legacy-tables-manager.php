<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fifu_Legacy_Tables_Manager {

	/** @var wpdb */
	private $wpdb;

	private $table_invalid_media_su;
	private $table_meta_in;
	private $table_meta_out;
	private $schema_dir;

	/**
	 * @param wpdb|null $wpdb_instance
	 */
	public function __construct( ?wpdb $wpdb_instance = null ) {
		if ( null === $wpdb_instance ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			global $wpdb;
			$wpdb_instance = $wpdb;
		}

		$this->wpdb = $wpdb_instance;
		$prefix = $this->wpdb->prefix;

		$this->table_invalid_media_su = $prefix . 'fifu_invalid_media_su';
		$this->table_meta_in = $prefix . 'fifu_meta_in';
		$this->table_meta_out = $prefix . 'fifu_meta_out';

		$schema_dir = __DIR__ . '/../schema';
		$resolved = realpath( $schema_dir );
		$this->schema_dir = false === $resolved ? rtrim( $schema_dir, '/\\' ) : $resolved;
	}

	/**
	 * @return void
	 */
	public function ensure_all_tables(): void {
		$this->create_invalid_media_su_table();
		$this->create_meta_in_table();
		$this->create_meta_out_table();
	}

	/**
	 * @return void
	 */
	/**
	 * @return void
	 */
	public function create_invalid_media_su_table(): void {
		$this->run_schema_file( '009_create_fifu_invalid_media_su.sql' );
	}

	/**
	 * @return void
	 */
	/**
	 * @return void
	 */
	public function create_meta_in_table(): void {
		$this->run_schema_file( '013_create_fifu_meta_in.sql' );
	}

	/**
	 * @return void
	 */
	public function create_meta_out_table(): void {
		$this->run_schema_file( '014_create_fifu_meta_out.sql' );
	}

	/**
	 * @param string $filename
	 * @return void
	 */
	protected function run_schema_file( string $filename ): void {
		$path = $this->schema_dir . '/' . $filename;
		if ( ! is_file( $path ) ) {
			return;
		}

		$content = file_get_contents( $path );
		if ( false === $content ) {
			return;
		}

		$sql = str_replace(
			[ '{PREFIX}', '{CHARSET_COLLATE}' ],
			[ $this->wpdb->prefix, $this->wpdb->get_charset_collate() ],
			$content
		);
		$statements = array_filter(
			array_map( 'trim', explode( ';', $sql ) )
		);

		foreach ( $statements as $statement ) {
			if ( '' === $statement ) {
				continue;
			}

			$this->wpdb->query( $statement );
		}
	}

}
