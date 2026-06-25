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

namespace StoreAccountant\Queue\Loopback;

use Throwable;
use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Queue\Transport\ActionSchedulerTransport;
use function absint;
use function class_exists;
use function function_exists;
use function is_array;
use function microtime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes StoreAccountant Action Scheduler jobs through an async HTTP loopback.
 */
final readonly class ActionSchedulerLoopbackRunner {
	private const MAX_ACTIONS = 10;
	private const MAX_SECONDS = 20;

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private ExportRepository $exports
	) {}

	/**
	 * Runs due queued actions for one export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int $export_id Export post ID.
	 *
	 * @return bool Whether another loopback run should be dispatched.
	 */
	public function run( int $export_id ): bool {
		if ( ! function_exists( 'as_get_scheduled_actions' ) || ! class_exists( '\ActionScheduler' ) || ! class_exists( '\ActionScheduler_Store' ) ) {
			ExportEventDispatcher::dispatch(
				ExportEvents::LOG_ENTRY,
				$export_id,
				'error',
				'Loopback runner could not find Action Scheduler.',
				[
					'export_id' => $export_id,
				]
			);

			return false;
		}

		$started_at = microtime( true );
		$processed  = 0;

		ExportEventDispatcher::dispatch(
			ExportEvents::LOG_ENTRY,
			$export_id,
			'info',
			'Loopback runner started.',
			[
				'export_id' => $export_id,
			]
		);

		while ( $processed < self::MAX_ACTIONS && ( microtime( true ) - $started_at ) < self::MAX_SECONDS ) {
			if ( $this->is_export_finished( $export_id ) ) {
				ExportEventDispatcher::dispatch(
					ExportEvents::LOG_ENTRY,
					$export_id,
					'info',
					'Loopback runner finished export.',
					[
						'export_id' => $export_id,
						'processed' => $processed,
					]
				);

				return false;
			}

			$action_id = $this->get_next_action_id( $export_id );

			if ( null === $action_id ) {
				ExportEventDispatcher::dispatch(
					ExportEvents::LOG_ENTRY,
					$export_id,
					'info',
					'Loopback runner found no pending export actions.',
					[
						'export_id' => $export_id,
						'processed' => $processed,
					]
				);

				return false;
			}

			try {
				\ActionScheduler::runner()->process_action( $action_id, 'StoreAccountant Loopback' );
			} catch ( Throwable $exception ) {
				$this->exports->mark_failed(
					$export_id,
					__( 'The accounting export queue runner failed.', 'storeaccountant' ),
					$exception,
					[
						'export_id'   => $export_id,
						'action_id'   => $action_id,
						'log_message' => 'Loopback runner failed while processing an action.',
					]
				);

				return false;
			}

				++$processed;

			if ( $this->is_export_finished( $export_id ) ) {
				return false;
			}

				ExportEventDispatcher::dispatch(
					ExportEvents::LOG_ENTRY,
					$export_id,
					'info',
					'Loopback runner processed action.',
					[
						'export_id' => $export_id,
						'action_id' => $action_id,
					]
				);
		}

		if ( $this->is_export_finished( $export_id ) ) {
			return false;
		}

		$has_pending = null !== $this->get_next_action_id( $export_id );

		if ( $has_pending ) {
			ExportEventDispatcher::dispatch(
				ExportEvents::LOG_ENTRY,
				$export_id,
				'info',
				'Loopback runner stopped because limits were reached.',
				[
					'export_id'    => $export_id,
					'processed'    => $processed,
					'max_actions'  => self::MAX_ACTIONS,
					'max_seconds'  => self::MAX_SECONDS,
					'elapsed_time' => microtime( true ) - $started_at,
				]
			);
		}

		return $has_pending;
	}

	/**
	 * Gets the next pending Action Scheduler action ID for the export.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function get_next_action_id( int $export_id ): ?int {
		foreach ( ActionSchedulerTransport::get_hooks() as $hook ) {
			$actions = as_get_scheduled_actions(
				[
					'hook'     => $hook,
					'group'    => 'storeaccountant',
					'status'   => \ActionScheduler_Store::STATUS_PENDING,
					'claimed'  => false,
					'per_page' => 10,
					'orderby'  => 'date',
					'order'    => 'ASC',
				]
			);

			foreach ( $actions as $action_id => $action ) {
				$args = $action->get_args();

				if ( ! is_array( $args ) || ! isset( $args['export_id'] ) || absint( $args['export_id'] ) !== $export_id ) {
					continue;
				}

				return (int) $action_id;
			}
		}

		return null;
	}

	/**
	 * Checks whether the export reached a terminal status.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function is_export_finished( int $export_id ): bool {
		$status = $this->exports->get_status( $export_id );

		return ExportStatus::COMPLETED === $status || ExportStatus::FAILED === $status;
	}
}
