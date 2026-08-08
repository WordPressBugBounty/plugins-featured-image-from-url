<?php
/**
 * Base interface for FIFU migration tasks.
 *
 * Each task represents a specific migration type (featured, category, sent, etc.)
 * and must support batched execution for large data sets.
 */
interface Fifu_Migration_Task_Interface {

	/**
	 * Internal task name (no spaces), e.g.: "featured", "category_image".
	 */
	public function get_name(): string;

	/**
	 * Friendly label for UI/admin display, e.g.: "Featured Images Migration".
	 */
	public function get_label(): string;

	/**
	 * Executes a migration batch.
	 *
	 * @param int $limit                Maximum number of records for this batch.
	 * @param int $time_limit_seconds   Approximate time limit (in seconds) for the batch.
	 */
	public function run_batch( int $limit, int $time_limit_seconds ): void;

	/**
	 * Indicates if the task has completed its planned migration.
	 *
	 * @return bool true when no more items remain to migrate.
	 */
	public function is_finished(): bool;
}
