<?php
declare(strict_types=1);

/**
 * Builds safe URL validation column selections for DB2 repositories.
 */
final class Fifu_Db2_Url_Validation_Schema {
	/** @var array<string,array<string,bool>> */
	private static array $columnCache = array();

	/**
	 * Returns available validation columns, substituting safe defaults for missing columns.
	 */
	public static function select_columns( wpdb $wpdb, string $table, string $alias = '', bool $defaults_only = false ): string {
		$columns = $defaults_only ? array() : self::columns( $wpdb, $table );
		$prefix  = '' !== $alias ? $alias . '.' : '';
		$select  = array();

		foreach ( array( 'validation_failures', 'validation_checked_at', 'validation_queued_at' ) as $column ) {
			if ( is_array( $columns ) && isset( $columns[ $column ] ) ) {
				$select[] = $prefix . $column;
			} else {
				$default  = 'validation_failures' === $column ? '0' : 'NULL';
				$select[] = $default . ' AS ' . $column;
			}
		}

		return implode( ', ', $select );
	}

	/**
	 * Invalidates the request-local schema result after an unknown-column error.
	 */
	public static function forget( wpdb $wpdb, string $table ): void {
		unset( self::$columnCache[ self::cache_key( $wpdb, $table ) ] );
	}

	/**
	 * Identifies only failures caused by the optional URL validation columns.
	 */
	public static function is_missing_validation_column_error( wpdb $wpdb ): bool {
		$error = (string) $wpdb->last_error;

		return false !== stripos( $error, 'unknown column' )
			&& ( false !== stripos( $error, 'validation_failures' )
				|| false !== stripos( $error, 'validation_checked_at' )
				|| false !== stripos( $error, 'validation_queued_at' ) );
	}

	/**
	 * @return array<string,bool>|null Null when the schema inspection itself fails.
	 */
	private static function columns( wpdb $wpdb, string $table ): ?array {
		$key = self::cache_key( $wpdb, $table );
		if ( ! isset( self::$columnCache[ $key ] ) ) {
			$rows = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
			if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
				return null;
			}

			$set  = array();
			foreach ( $rows as $column ) {
				$set[ (string) $column ] = true;
			}
			self::$columnCache[ $key ] = $set;
		}

		return self::$columnCache[ $key ];
	}

	private static function cache_key( wpdb $wpdb, string $table ): string {
		return spl_object_id( $wpdb ) . '|' . $table;
	}
}
