<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author      thomas.baier@launch-lab.de
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Export\Event;

use Throwable;
use StoreAccountant\Event\Contract\EventSubscriberInterface;
use StoreAccountant\Export\ExportRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes export event logs.
 */
final readonly class ExportEventLogSubscriber implements EventSubscriberInterface {

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private ExportRepository $repository
	) {
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public static function get_subscribed_events(): array {
		return [
			ExportEvents::LOG_ENTRY->value            => [
				[ 'log_entry', 10, 5 ],
			],
			ExportEvents::QUEUED->value               => [
				[ 'log_queued', 10, 2 ],
			],
			ExportEvents::STARTED->value              => [
				[ 'log_started', 10, 2 ],
			],
			ExportEvents::BATCHES_CALCULATED->value   => [
				[ 'log_batches_calculated', 10, 2 ],
			],
			ExportEvents::BATCH_PROCESSED->value      => [
				[ 'log_batch_processed', 10, 2 ],
			],
			ExportEvents::BATCH_JOBS_QUEUED->value    => [
				[ 'log_batch_jobs_queued', 10, 2 ],
			],
			ExportEvents::FINALIZATION_QUEUED->value  => [
				[ 'log_finalization_queued', 10, 2 ],
			],
			ExportEvents::FINALIZATION_STARTED->value => [
				[ 'log_finalization_started', 10, 2 ],
			],
			ExportEvents::DATASET_LOADED->value       => [
				[ 'log_dataset_loaded', 10, 2 ],
			],
			ExportEvents::ARTIFACT_RENDERED->value    => [
				[ 'log_artifact_rendered', 10, 2 ],
			],
			ExportEvents::ARTIFACT_PERSISTED->value   => [
				[ 'log_artifact_persisted', 10, 2 ],
			],
			ExportEvents::COMPLETED->value            => [
				[ 'log_completed', 10, 2 ],
			],
			ExportEvents::FAILED->value               => [
				[ 'log_failed', 10, 5 ],
			],
		];
	}

	/**
	 * Logs a generic export log entry event.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_entry( int $export_id, string $level, string $message, array $context = [], ?Throwable $exception = null ): void {
		$this->repository->add_log_entry( $export_id, $level, $message, $context, $exception );
	}

	/**
	 * Logs an export queued event.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_queued( int $export_id, array $context = [] ): void {
		$message = isset( $context['retry'] ) && true === $context['retry']
			? 'Export retry queued for background processing.'
			: 'Export queued for background processing.';

		$this->repository->add_log_entry( $export_id, 'info', $message, $context );
	}

	/**
	 * Logs an export worker start event.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_started( int $export_id, array $context = [] ): void {
		$this->repository->add_log_entry( $export_id, 'info', 'Export queue worker started.', $context );
	}

	/**
	 * Logs calculated export batches.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_batches_calculated( int $export_id, array $context = [] ): void {
		$this->repository->add_log_entry( $export_id, 'info', 'Export batches calculated.', $context );
	}

	/**
	 * Logs a processed export batch.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_batch_processed( int $export_id, array $context = [] ): void {
		$message = isset( $context['processed_items'] ) && 0 === (int) $context['processed_items']
			? 'No export items found; empty batch stored.'
			: 'Export batch processed.';

		$this->repository->add_log_entry( $export_id, 'info', $message, $context );
	}

	/**
	 * Logs queued export batch jobs.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_batch_jobs_queued( int $export_id, array $context = [] ): void {
		$this->repository->add_log_entry( $export_id, 'info', 'Export batch jobs queued.', $context );
	}

	/**
	 * Logs queued export finalization.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_finalization_queued( int $export_id, array $context = [] ): void {
		$message = isset( $context['all_batches_processed'] ) && true === $context['all_batches_processed']
			? 'All export batches processed; finalization queued.'
			: 'Export finalization queued.';

		$this->repository->add_log_entry( $export_id, 'info', $message, $context );
	}

	/**
	 * Logs export finalization start.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_finalization_started( int $export_id, array $context = [] ): void {
		$this->repository->add_log_entry( $export_id, 'info', 'Export finalization started.', $context );
	}

	/**
	 * Logs loaded export dataset.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_dataset_loaded( int $export_id, array $context = [] ): void {
		$this->repository->add_log_entry( $export_id, 'info', 'Export dataset loaded from batch files.', $context );
	}

	/**
	 * Logs rendered export artifact.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_artifact_rendered( int $export_id, array $context = [] ): void {
		$this->repository->add_log_entry( $export_id, 'info', 'Export artifact rendered.', $context );
	}

	/**
	 * Logs persisted export artifact.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_artifact_persisted( int $export_id, array $context = [] ): void {
		$this->repository->add_log_entry( $export_id, 'info', 'Export artifact persisted.', $context );
	}

	/**
	 * Logs completed export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_completed( int $export_id, array $context = [] ): void {
		$this->repository->add_log_entry( $export_id, 'success', 'Export completed.', $context );
	}

	/**
	 * Logs failed export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $context Additional context.
	 */
	public function log_failed( int $export_id, string $error_message, string $log_message = '', array $context = [], ?Throwable $exception = null ): void {
		$this->repository->add_log_entry( $export_id, 'error', '' !== $log_message ? $log_message : $error_message, $context, $exception );
	}
}
