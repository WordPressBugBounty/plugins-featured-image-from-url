<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Safe bridge between legacy sent metadata and the DB2 sent layer.
 */
class Fifu_Sent_Bridge {

	/** Pattern that all event keys must satisfy. */
	private const EVENT_KEY_PATTERN = '/^[a-z0-9_]+$/';
	private const DB2_ONLY_EVENT_KEYS = array(
		'isbn',
		'asin',
		'finder',
		'metadatapost',
	);

	/**
	 * DB2 writes are hardcoded as enabled for now.
	 */
	public static function is_db2_sent_write_enabled(): bool {
		return true;
	}

	/**
	 * DB2 reads remain disabled until the migration is complete.
	 */
	public static function is_db2_sent_read_enabled(): bool {
		return false;
	}

	/**
	 * Mirrors the legacy post meta attempts + syncs the row into DB2.
	 */
	public static function set_post_sent_attempts( int $post_id, string $event_key, int $attempts, ?string $last_error = null ): bool {
		$key = self::normalize_event_key( $event_key );
		if ( $key === null ) {
			return false;
		}

		$meta_key = self::legacy_meta_key( $key );

		if ( self::is_db2_only_event( $key ) ) {
			if ( ! self::is_db2_sent_write_enabled() ) {
				return false;
			}

			$ok = self::try_db2_set_attempts( 'post', $post_id, $key, $attempts, $last_error );
			if ( $ok ) {
				self::delete_legacy_postmeta_raw( $post_id, $meta_key );
			}

			return $ok;
		}

		$legacy_result = update_post_meta( $post_id, $meta_key, $attempts );

		if ( self::is_db2_sent_write_enabled() ) {
			self::try_db2_set_attempts( 'post', $post_id, $key, $attempts, $last_error );
		}

		return $legacy_result !== false;
	}

	/**
	 * Mirrors the legacy term meta attempts + syncs the row into DB2.
	 */
	public static function set_term_sent_attempts( int $term_id, string $event_key, int $attempts, ?string $last_error = null ): bool {
		$key = self::normalize_event_key( $event_key );
		if ( $key === null ) {
			return false;
		}

		$meta_key      = self::legacy_meta_key( $key );
		if ( self::is_db2_only_event( $key ) ) {
			if ( ! self::is_db2_sent_write_enabled() ) {
				return false;
			}

			$ok = self::try_db2_set_attempts( 'term', $term_id, $key, $attempts, $last_error );
			if ( $ok ) {
				self::delete_legacy_termmeta_raw( $term_id, $meta_key );
			}

			return $ok;
		}

		$legacy_result = update_term_meta( $term_id, $meta_key, $attempts );

		if ( self::is_db2_sent_write_enabled() ) {
			self::try_db2_set_attempts( 'term', $term_id, $key, $attempts, $last_error );
		}

		return $legacy_result !== false;
	}

	/**
	 * Deletes the legacy post meta and marks the row as sent in DB2.
	 */
	public static function delete_post_sent( int $post_id, string $event_key ): bool {
		$key = self::normalize_event_key( $event_key );
		if ( $key === null ) {
			return false;
		}

		$meta_key      = self::legacy_meta_key( $key );
		if ( $key === 'metadatapost' ) {
			if ( self::is_db2_sent_write_enabled() ) {
				self::try_db2_delete_status( 'post', $post_id, $key );
			}

			self::delete_legacy_postmeta_raw( $post_id, $meta_key );

			return true;
		}

		if ( self::is_db2_only_event( $key ) ) {
			if ( ! self::is_db2_sent_write_enabled() ) {
				return false;
			}

			$ok = self::try_db2_mark_sent_ok( 'post', $post_id, $key );
			if ( $ok ) {
				self::delete_legacy_postmeta_raw( $post_id, $meta_key );
			}

			return $ok;
		}

		$legacy_result = delete_post_meta( $post_id, $meta_key );

		if ( self::is_db2_sent_write_enabled() ) {
			self::try_db2_mark_sent_ok( 'post', $post_id, $key );
		}

		return $legacy_result;
	}

	/**
	 * Deletes the sent status row without marking the event as sent ok.
	 */
	public static function delete_post_sent_status( int $post_id, string $event_key ): bool {
		$key = self::normalize_event_key( $event_key );
		if ( $key === null ) {
			return false;
		}

		$meta_key = self::legacy_meta_key( $key );

		$ok = true;
		if ( self::is_db2_sent_write_enabled() ) {
			$ok = self::try_db2_delete_status( 'post', $post_id, $key );
		}

		self::delete_legacy_postmeta_raw( $post_id, $meta_key );

		return $ok;
	}

	/**
	 * Deletes the legacy term meta and marks the row as sent in DB2.
	 */
	public static function delete_term_sent( int $term_id, string $event_key ): bool {
		$key = self::normalize_event_key( $event_key );
		if ( $key === null ) {
			return false;
		}

		$meta_key      = self::legacy_meta_key( $key );
		if ( $key === 'metadataterm' ) {
			if ( self::is_db2_sent_write_enabled() ) {
				self::try_db2_delete_status( 'term', $term_id, $key );
			}

			self::delete_legacy_termmeta_raw( $term_id, $meta_key );

			return true;
		}

		if ( self::is_db2_only_event( $key ) ) {
			if ( ! self::is_db2_sent_write_enabled() ) {
				return false;
			}

			$ok = self::try_db2_mark_sent_ok( 'term', $term_id, $key );
			if ( $ok ) {
				self::delete_legacy_termmeta_raw( $term_id, $meta_key );
			}

			return $ok;
		}

		$legacy_result = delete_term_meta( $term_id, $meta_key );

		if ( self::is_db2_sent_write_enabled() ) {
			self::try_db2_mark_sent_ok( 'term', $term_id, $key );
		}

		return $legacy_result;
	}

	/**
	 * Returns the stored attempts count respecting the DB2 read flag.
	 */
	public static function get_post_sent_attempts( int $post_id, string $event_key ): ?int {
		$key = self::normalize_event_key( $event_key );
		if ( $key === null ) {
			return null;
		}

		$meta_key = self::legacy_meta_key( $key );

		if ( self::is_db2_only_event( $key ) ) {
			$status = self::try_db2_get_status( 'post', $post_id, $key, true );
			if ( $status === null ) {
				return self::legacy_get_post_attempts( $post_id, $meta_key );
			}
			if ( isset( $status['sent'] ) && (int) $status['sent'] === 1 ) {
				return null;
			}
			if ( isset( $status['attempts'] ) ) {
				return (int) $status['attempts'];
			}

			return null;
		}

		if ( self::is_db2_sent_read_enabled() ) {
			$status = self::try_db2_get_status( 'post', $post_id, $key );
			if ( $status !== null ) {
				if ( isset( $status['sent'] ) && (int) $status['sent'] === 1 ) {
					return null;
				}
				if ( isset( $status['attempts'] ) ) {
					return (int) $status['attempts'];
				}
			}
		}

		return self::legacy_get_post_attempts( $post_id, $meta_key );
	}

	/**
	 * Returns the stored attempts count respecting the DB2 read flag.
	 */
	public static function get_term_sent_attempts( int $term_id, string $event_key ): ?int {
		$key = self::normalize_event_key( $event_key );
		if ( $key === null ) {
			return null;
		}

		$meta_key = self::legacy_meta_key( $key );

		if ( self::is_db2_only_event( $key ) ) {
			$status = self::try_db2_get_status( 'term', $term_id, $key, true );
			if ( $status === null ) {
				return self::legacy_get_term_attempts( $term_id, $meta_key );
			}
			if ( isset( $status['sent'] ) && (int) $status['sent'] === 1 ) {
				return null;
			}
			if ( isset( $status['attempts'] ) ) {
				return (int) $status['attempts'];
			}

			return null;
		}

		if ( self::is_db2_sent_read_enabled() ) {
			$status = self::try_db2_get_status( 'term', $term_id, $key );
			if ( $status !== null ) {
				if ( isset( $status['sent'] ) && (int) $status['sent'] === 1 ) {
					return null;
				}
				if ( isset( $status['attempts'] ) ) {
					return (int) $status['attempts'];
				}
			}
		}

		return self::legacy_get_term_attempts( $term_id, $meta_key );
	}

	/**
	 * Normalizes and validates the incoming event key.
	 */
	private static function normalize_event_key( string $event_key ): ?string {
		$normalized = strtolower( trim( $event_key ) );
		if ( $normalized === '' ) {
			return null;
		}

		if ( preg_match( self::EVENT_KEY_PATTERN, $normalized ) !== 1 ) {
			return null;
		}

		return $normalized;
	}

	/**
	 * Builds the legacy meta key for the given event.
	 */
	private static function legacy_meta_key( string $normalized_event_key ): string {
		return 'fifu_' . $normalized_event_key . '_sent';
	}

	private static function delete_legacy_postmeta_raw( int $post_id, string $meta_key ): void {
		global $wpdb;

		$wpdb->delete(
			$wpdb->postmeta,
			[
				'post_id'  => $post_id,
				'meta_key' => $meta_key,
			],
			[
				'%d',
				'%s',
			]
		);

		clean_post_cache( $post_id );
		wp_cache_delete( $post_id, 'post_meta' );
	}

	private static function delete_legacy_termmeta_raw( int $term_id, string $meta_key ): void {
		global $wpdb;

		$wpdb->delete(
			$wpdb->termmeta,
			[
				'term_id'  => $term_id,
				'meta_key' => $meta_key,
			],
			[
				'%d',
				'%s',
			]
		);

		clean_term_cache( $term_id );
		wp_cache_delete( $term_id, 'term_meta' );
	}

	private static function is_db2_only_event( string $event_key ): bool {
		return in_array( $event_key, self::DB2_ONLY_EVENT_KEYS, true );
	}

	public static function is_db2_only_event_key( string $event_key ): bool {
		$key = self::normalize_event_key( $event_key );
		if ( $key === null ) {
			return false;
		}

		return self::is_db2_only_event( $key );
	}

	/**
	 * Safely retrieves the post meta attempts value.
	 */
	private static function legacy_get_post_attempts( int $post_id, string $meta_key ): ?int {
		if ( ! metadata_exists( 'post', $post_id, $meta_key ) ) {
			return null;
		}

		$value = get_post_meta( $post_id, $meta_key, true );

		return self::normalize_legacy_attempts( $value );
	}

	/**
	 * Safely retrieves the term meta attempts value.
	 */
	private static function legacy_get_term_attempts( int $term_id, string $meta_key ): ?int {
		if ( ! metadata_exists( 'term', $term_id, $meta_key ) ) {
			return null;
		}

		$value = get_term_meta( $term_id, $meta_key, true );

		return self::normalize_legacy_attempts( $value );
	}

	/**
	 * Converts legacy meta values into an attempt count when possible.
	 */
	private static function normalize_legacy_attempts( $value ): ?int {
		if ( $value === '' || $value === null || $value === false || is_array( $value ) ) {
			return null;
		}

		$attempts = filter_var( $value, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 0 ] ] );
		if ( $attempts === false ) {
			return null;
		}

		return $attempts;
	}

	/**
	 * Lazily retrieves the DB2 sent service if available.
	 */
	private static function db2_service(): ?Fifu_Db2_Sent_Service {
		if ( ! function_exists( 'fifu_db2_sent_service' ) ) {
			return null;
		}

		return fifu_db2_sent_service();
	}

	/**
	 * Attempts to set the attempts in DB2 without breaking legacy behavior.
	 */
	private static function try_db2_set_attempts( string $object_type, int $object_id, string $event_key, int $attempts, ?string $last_error ): bool {
		$service = self::db2_service();
		if ( $service === null ) {
			return false;
		}

		try {
			return $service->set_attempts( $object_type, $object_id, $event_key, $attempts, $last_error );
		} catch ( \Throwable $exception ) {
			// DB2 errors are ignored to keep legacy meta writes working.
			return false;
		}
	}

	/**
	 * Attempts to mark the object as sent in DB2 without breaking legacy behavior.
	 */
	private static function try_db2_mark_sent_ok( string $object_type, int $object_id, string $event_key ): bool {
		$service = self::db2_service();
		if ( $service === null ) {
			return false;
		}

		try {
			return $service->mark_sent_ok( $object_type, $object_id, $event_key );
		} catch ( \Throwable $exception ) {
			// Intentionally ignore DB2 write failures during deletes.
			return false;
		}
	}

	/**
	 * Attempts to delete the DB2 status without breaking legacy behavior.
	 */
	private static function try_db2_delete_status( string $object_type, int $object_id, string $event_key ): bool {
		$service = self::db2_service();
		if ( $service === null ) {
			return false;
		}

		try {
			return $service->delete_status( $object_type, $object_id, $event_key );
		} catch ( \Throwable $exception ) {
			// Intentionally ignore DB2 write failures during deletes.
			return false;
		}
	}

	/**
	 * Attempts to fetch the DB2 status without breaking legacy reads.
	 */
	private static function try_db2_get_status( string $object_type, int $object_id, string $event_key, bool $force = false ): ?array {
		if ( ! $force && ! self::is_db2_sent_read_enabled() ) {
			return null;
		}

		$service = self::db2_service();
		if ( $service === null ) {
			return null;
		}

		try {
			return $service->get_status( $object_type, $object_id, $event_key );
		} catch ( \Throwable $exception ) {
			return null;
		}
	}
}
