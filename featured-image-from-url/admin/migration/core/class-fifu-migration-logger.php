<?php
declare( strict_types=1 );

/**
 * Simple logger used by the FIFU migration system.
 */
class Fifu_Migration_Logger {
	/**
	 * Logs an informational message.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'INFO', $message, $context );
	}

	/**
	 * Logs a warning message.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'WARNING', $message, $context );
	}

	/**
	 * Logs an error message.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'ERROR', $message, $context );
	}

	/**
	 * Writes a formatted message to error_log.
	 *
	 * @param string $level
	 * @param string $message
	 * @param array  $context
	 */
	protected function log( string $level, string $message, array $context = array() ): void {
		$parts = array( sprintf( '[FIFU MIGRATION][%s] %s', strtoupper( $level ), $message ) );

		if ( ! empty( $context ) ) {
			try {
				$json = json_encode( $context );
				if ( false !== $json ) {
					$parts[] = $json;
				} else {
					$parts[] = '[context encoding failed]';
				}
			} catch ( \Throwable $e ) {
				error_log( $e->getMessage() );
				$parts[] = '[context encoding failed]';
			}
		}

		error_log( implode( ' ', $parts ) );
	}
}
