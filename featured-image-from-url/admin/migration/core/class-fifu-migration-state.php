<?php
declare( strict_types=1 );

/**
 * Manages persistent migration state across FIFU tasks.
 */
class Fifu_Migration_State {
	/** @var string */
	private $option_name;

	/**
	 * @param string $option_name Name of the option used to store migration state.
	 */
	public function __construct( string $option_name = 'fifu_migration_state' ) {
		$this->option_name = $option_name;
	}

	/**
	 * Returns the full state array for all tasks.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_all_state(): array {
		$state = get_option( $this->option_name, array() );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Returns the state array for a single task with defaults if missing.
	 *
	 * @param string $task_name
	 * @return array<string, mixed>
	 */
	public function get_task_state( string $task_name ): array {
		$all_state = $this->get_all_state();

		if ( ! isset( $all_state[ $task_name ] ) || ! is_array( $all_state[ $task_name ] ) ) {
			return $this->get_default_task_state( $task_name );
		}

		return $all_state[ $task_name ];
	}

	/**
	 * Updates and persists the state data for a single task.
	 *
	 * @param string               $task_name
	 * @param array<string, mixed> $data
	 */
	public function update_task_state( string $task_name, array $data ): void {
		$all_state          = $this->get_all_state();
		$current_task_state = $this->get_task_state( $task_name );

		$merged = array_merge( $current_task_state, $data );
		$merged['updated_at'] = date( 'c' );

		$all_state[ $task_name ] = $merged;

		update_option( $this->option_name, $all_state );
	}

	/**
	 * Resets a task state back to default values and persists it.
	 *
	 * @param string $task_name
	 */
	public function reset_task_state( string $task_name ): void {
		$all_state                       = $this->get_all_state();
		$all_state[ $task_name ]         = $this->get_default_task_state( $task_name );
		$all_state[ $task_name ]['updated_at'] = date( 'c' );

		update_option( $this->option_name, $all_state );
	}

	/**
	 * Returns the default state structure for the provided task.
	 *
	 * @param string $task_name
	 * @return array<string, mixed>
	 */
	protected function get_default_task_state( string $task_name ): array {
		return array(
			'status'          => 'pending',
			'last_id'         => 0,
			'processed_count' => 0,
			'error_count'     => 0,
			'updated_at'      => date( 'c' ),
			'task_name'       => $task_name,
		);
	}
}
