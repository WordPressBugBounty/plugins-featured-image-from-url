<?php
declare( strict_types=1 );

/**
 * Orchestrates migration task execution in batches.
 */
class Fifu_Migration_Runner {
	/** @var Fifu_Migration_State */
	protected $state;

	/** @var Fifu_Migration_Logger */
	protected $logger;

	/** @var Fifu_Migration_Registry */
	protected $registry;

	/**
	 * @param Fifu_Migration_State    $state
	 * @param Fifu_Migration_Logger   $logger
	 * @param Fifu_Migration_Registry $registry
	 */
	public function __construct(
		?Fifu_Migration_State $state = null,
		?Fifu_Migration_Logger $logger = null,
		?Fifu_Migration_Registry $registry = null
	) {
		$this->state    = $state ?? new Fifu_Migration_State();
		$this->logger   = $logger ?? new Fifu_Migration_Logger();
		$this->registry = $registry ?? new Fifu_Migration_Registry();
	}

	/**
	 * Runs a single batch for the given task.
	 *
	 * @param string $task_name          Task internal name.
	 * @param int    $limit              Maximum records for this batch.
	 * @param int    $time_limit_seconds Approximate time limit.
	 */
	public function run_task_batch( string $task_name, int $limit, int $time_limit_seconds ): void {
		$task = $this->registry->get_task( $task_name );

		if ( null === $task ) {
			$this->logger->warning( sprintf( 'Task "%s" not found in registry.', $task_name ) );
			return;
		}

		$state = $this->state->get_task_state( $task_name );

		// Always allow the batch to run, even if the previous state was "finished".

		$this->logger->info( sprintf( 'Starting batch for task "%s".', $task_name ) );
		$this->state->update_task_state( $task_name, array( 'status' => 'running' ) );

		$task->run_batch( $limit, $time_limit_seconds );
		$all_state_after = $this->state->get_all_state();
		$task_state_after = isset( $all_state_after[ $task_name ] ) && is_array( $all_state_after[ $task_name ] )
			? $all_state_after[ $task_name ]
			: array();
		$error_count = (int) ( $task_state_after['error_count'] ?? 0 );
		$effectively_finished = $task->is_finished() && 0 === $error_count;

		if ( $effectively_finished ) {
			$this->logger->info( sprintf( 'Task "%s" finished.', $task_name ) );

			$this->state->update_task_state( $task_name, array( 'status' => 'finished' ) );
		} else {
			$this->logger->info( sprintf( 'Task "%s" still pending.', $task_name ) );

			$this->state->update_task_state( $task_name, array( 'status' => 'running' ) );
		}
	}

	/**
	 * Runs one batch for all pending tasks.
	 *
	 * @param int $limit
	 * @param int $time_limit_seconds
	 */
	public function run_all_pending( int $limit, int $time_limit_seconds ): void {
		$tasks = $this->registry->get_all_tasks();

		foreach ( $tasks as $task ) {
			$task_name = $task->get_name();

			// Always attempt to run a batch for each task; tasks are idempotent and process only new records.
			$this->run_task_batch( $task_name, $limit, $time_limit_seconds );
		}
	}
}
