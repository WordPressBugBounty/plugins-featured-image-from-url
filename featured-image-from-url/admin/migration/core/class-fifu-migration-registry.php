<?php
declare( strict_types=1 );

/**
 * Keeps track of all migration tasks and exposes them for execution.
 */
class Fifu_Migration_Registry {

	/**
	 * @var array<string, Fifu_Migration_Task_Interface>
	 */
	protected $tasks = array();

	/**
	 * @param array<string, Fifu_Migration_Task_Interface> $tasks
	 */
	public function __construct( array $tasks = array() ) {
		if ( ! empty( $tasks ) ) {
			foreach ( $tasks as $task ) {
				if ( $task instanceof Fifu_Migration_Task_Interface ) {
					$this->register_task( $task );
				}
			}
		} else {
			$this->load_default_tasks();
		}
	}

	/**
	 * Returns a task by its internal name, or null if not found.
	 *
	 * @param string $name
	 * @return Fifu_Migration_Task_Interface|null
	 */
	public function get_task( string $name ) {
		return $this->tasks[ $name ] ?? null;
	}

	/**
	 * Returns all registered tasks.
	 *
	 * @return Fifu_Migration_Task_Interface[]
	 */
	public function get_all_tasks(): array {
		return array_values( $this->tasks );
	}

	/**
	 * Registers a migration task in the registry.
	 *
	 * @param Fifu_Migration_Task_Interface $task
	 * @return void
	 */
	public function register_task( Fifu_Migration_Task_Interface $task ): void {
		$name = $task->get_name();

		// Avoid overwriting an existing task with the same name.
		if ( isset( $this->tasks[ $name ] ) ) {
			return;
		}

		$this->tasks[ $name ] = $task;
	}

	/**
	 * Loads and registers all default migration tasks.
	 *
	 * @return void
	 */
	protected function load_default_tasks(): void {
		$tasks_dir = FIFU_ADMIN_DIR . '/migration/tasks';

		$task_files = array(
			'Fifu_Mig_Featured'       => $tasks_dir . '/class-fifu-mig-featured.php',
			'Fifu_Mig_Category_Image' => $tasks_dir . '/class-fifu-mig-category-image.php',
			'Fifu_Mig_Category_Alt'   => $tasks_dir . '/class-fifu-mig-category-alt.php',
			'Fifu_Mig_Alt_Featured'   => $tasks_dir . '/class-fifu-mig-alt-featured.php',
		);

		foreach ( $task_files as $class_name => $file_path ) {
			if ( ! class_exists( $class_name ) && file_exists( $file_path ) ) {
				require_once $file_path;
			}

			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			$task = new $class_name();

			if ( ! $task instanceof Fifu_Migration_Task_Interface ) {
				continue;
			}

			// Use the internal name defined by the task.
			$name = $task->get_name();

			if ( isset( $this->tasks[ $name ] ) ) {
				continue;
			}

			$this->tasks[ $name ] = $task;
		}
	}
}
