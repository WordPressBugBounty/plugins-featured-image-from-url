<?php

declare(strict_types=1);

/**
 * Registers WP-CLI commands for FIFU migrations.
 */
class Fifu_Migration_CLI {
	/** @var Fifu_Migration_Runner */
	protected $runner;

	/** @var Fifu_Migration_Registry */
	protected $registry;

	/** @var Fifu_Migration_State */
	protected $state;

	/** @var Fifu_Migration_Logger */
	protected $logger;

	/**
	 * @param Fifu_Migration_Runner   $runner
	 * @param Fifu_Migration_Registry $registry
	 * @param Fifu_Migration_State    $state
	 * @param Fifu_Migration_Logger   $logger
	 */
	public function __construct(
		?Fifu_Migration_Runner $runner = null,
		?Fifu_Migration_Registry $registry = null,
		?Fifu_Migration_State $state = null,
		?Fifu_Migration_Logger $logger = null
	) {
		$this->state    = $state    ?: new Fifu_Migration_State();
		$this->logger   = $logger   ?: new Fifu_Migration_Logger();
		$this->registry = $registry ?: new Fifu_Migration_Registry();
		$this->runner   = $runner   ?: new Fifu_Migration_Runner( $this->state, $this->logger, $this->registry );
	}

	/**
	 * Registers WP-CLI commands for FIFU migrations.
	 *
	 * This method SHOULD be called from plugin bootstrap code, guarded by WP_CLI checks.
	 *
	 * @return void
	 */
	public static function register_commands(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'fifu-migrate', 'Fifu_Migration_CLI' );
	}

	/**
	 * Runs a single migration batch for a specific task.
	 *
	 * ## OPTIONS
	 *
	 * <task>
	 * : Task internal name (e.g. "featured", "category_image", "category_alt", "alt_featured").
	 *
	 * [--limit=<limit>]
	 * : Maximum number of records to process in this batch. Default: 500
	 *
	 * [--time=<seconds>]
	 * : Approximate time limit (in seconds) for this batch. Default: 20
	 *
	 * ## EXAMPLES
	 *
	 *   wp fifu-migrate run_task featured
	 *   wp fifu-migrate run_task category_image --limit=1000 --time=30
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function run_task( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Task name is required.' );
			return;
		}

		$task_name = $args[0];
		$limit     = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 500;
		$time      = isset( $assoc_args['time'] ) ? (int) $assoc_args['time'] : 20;

		$task = $this->registry->get_task( $task_name );

		if ( null === $task ) {
			\WP_CLI::error( "Unknown task: {$task_name}" );
			return;
		}

		$this->runner->run_task_batch( $task_name, $limit, $time );

		$state = $this->state->get_task_state( $task_name );

		$status          = isset( $state['status'] ) ? (string) $state['status'] : 'pending';
		$last_id         = isset( $state['last_id'] ) ? (int) $state['last_id'] : 0;
		$processed_count = isset( $state['processed_count'] ) ? (int) $state['processed_count'] : 0;
		$error_count     = isset( $state['error_count'] ) ? (int) $state['error_count'] : 0;

		$message = sprintf(
			'%s: status=%s last_id=%d processed=%d errors=%d',
			$task_name,
			$status,
			$last_id,
			$processed_count,
			$error_count
		);

		\WP_CLI::success( $message );
	}

	/**
	 * Runs a single batch for all tasks that are not finished yet.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Maximum number of records per task in this batch. Default: 500
	 *
	 * [--time=<seconds>]
	 * : Approximate time limit (in seconds) per task. Default: 20
	 *
	 * ## EXAMPLES
	 *
	 *   wp fifu-migrate run-all
	 *   wp fifu-migrate run-all --limit=1000 --time=30
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function run_all( array $args, array $assoc_args ): void {
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 500;
		$time  = isset( $assoc_args['time'] ) ? (int) $assoc_args['time'] : 20;

		$this->runner->run_all_pending( $limit, $time );

		$tasks = $this->registry->get_all_tasks();

		foreach ( $tasks as $task ) {
			$task_name = $task->get_name();
			$state     = $this->state->get_task_state( $task_name );

			$status          = isset( $state['status'] ) ? (string) $state['status'] : 'pending';
			$last_id         = isset( $state['last_id'] ) ? (int) $state['last_id'] : 0;
			$processed_count = isset( $state['processed_count'] ) ? (int) $state['processed_count'] : 0;
			$error_count     = isset( $state['error_count'] ) ? (int) $state['error_count'] : 0;

			$message = sprintf(
				'%s: status=%s last_id=%d processed=%d errors=%d',
				$task_name,
				$status,
				$last_id,
				$processed_count,
				$error_count
			);

			\WP_CLI::log( $message );
		}

		\WP_CLI::success( 'Migration batch finished.' );
	}

	/**
	 * Resets the state of a specific migration task.
	 *
	 * ## OPTIONS
	 *
	 * <task>
	 * : Task internal name (e.g. "featured", "category_image", "category_alt", "alt_featured").
	 *
	 * ## EXAMPLES
	 *
	 *   wp fifu-migrate reset_task alt_featured
	 *
	 * @param array $args
	 * @return void
	 */
	public function reset_task( array $args ): void {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Task name is required.' );
			return;
		}

		$task_name = $args[0];
		$task      = $this->registry->get_task( $task_name );

		if ( null === $task ) {
			\WP_CLI::error( "Unknown task: {$task_name}" );
			return;
		}

		$this->state->reset_task_state( $task_name );
		\WP_CLI::success( "Task '{$task_name}' state has been reset." );
	}
}
