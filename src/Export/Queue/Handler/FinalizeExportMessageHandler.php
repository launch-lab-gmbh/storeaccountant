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

namespace StoreAccountant\Export\Queue\Handler;

use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;
use WP_Error;
use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Export\Queue\QueuedExportFinalizer;
use StoreAccountant\Export\Queue\Message\FinalizeExportAttachmentsMessage;
use StoreAccountant\Export\Queue\Message\FinalizeExportMessage;
use function __;
use function absint;
use function apply_filters;
use function get_post_type;
use function is_wp_error;
use function sleep;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finalizes an export after all batches have finished.
 */
final readonly class FinalizeExportMessageHandler {
	public function __construct(
		private MessageBusInterface $message_bus,
		private QueuedExportFinalizer $finalizer,
		private ExportRepository $repository
	) {}

	/**
	 * Handles the finalize export message.
	 *
	 * @param FinalizeExportMessage $message Finalize export message.
	 */
	public function __invoke( FinalizeExportMessage $message ): true|WP_Error {
		if ( $message->export_id <= 0 || ExportPostType::POST_TYPE !== get_post_type( $message->export_id ) ) {
			return new WP_Error(
				'storeaccountant_export_finalize_message_invalid',
				__( 'The queued accounting export finalization is invalid.', 'storeaccountant' )
			);
		}

		if ( ExportStatus::COMPLETED === $this->repository->get_status( $message->export_id ) ) {
			return true;
		}

		if ( ! $this->repository->all_batches_processed( $message->export_id ) ) {
			return true;
		}

		$this->repository->mark_processing( $message->export_id, __( 'Finalizing export file.', 'storeaccountant' ) );
		ExportEventDispatcher::dispatch(
			ExportEvents::FINALIZATION_STARTED,
			$message->export_id,
			[
				'message'     => FinalizeExportMessage::class,
				'export_id'   => $message->export_id,
				'renderer_id' => $message->renderer_id,
			]
		);

		$this->maybe_apply_debug_delay();

		try {
			$result = $this->finalizer->finalize( $message->export_id, $message->renderer_id );
		} catch ( Throwable $exception ) {
			$this->repository->mark_failed(
				$message->export_id,
				__( 'Unexpected export finalization error.', 'storeaccountant' ),
				$exception,
				[
					'message'     => FinalizeExportMessage::class,
					'export_id'   => $message->export_id,
					'renderer_id' => $message->renderer_id,
					'log_message' => 'Unexpected export finalization error.',
				]
			);

			return new WP_Error(
				'storeaccountant_export_finalization_failed',
				__( 'The accounting export could not be finalized.', 'storeaccountant' )
			);
		}

		if ( is_wp_error( $result ) ) {
			$this->repository->mark_failed_from_error(
				$message->export_id,
				$result,
				[
					'message'     => FinalizeExportMessage::class,
					'export_id'   => $message->export_id,
					'renderer_id' => $message->renderer_id,
				]
			);

			return $result;
		}

		if ( ! $result->complete ) {
			$this->message_bus->dispatch(
				new FinalizeExportAttachmentsMessage(
					$message->export_id,
					$message->renderer_id,
					$result->storage_path,
					0,
					$result->attachment_batch_size,
					$result->total_attachments
				)
			);

			ExportEventDispatcher::dispatch(
				ExportEvents::FINALIZATION_QUEUED,
				$message->export_id,
				[
					'message'               => FinalizeExportAttachmentsMessage::class,
					'export_id'             => $message->export_id,
					'renderer_id'           => $message->renderer_id,
					'total_attachments'     => $result->total_attachments,
					'attachment_batch_size' => $result->attachment_batch_size,
				]
			);

			return true;
		}

		$this->complete_export( $message->export_id, $message->renderer_id, FinalizeExportMessage::class );

		return true;
	}

	/**
	 * Marks an export completed and dispatches the completed event.
	 *
	 * @param int         $export_id     Export post ID.
	 * @param string|null $renderer_id   Renderer ID.
	 * @param string      $message_class Queue message class.
	 */
	private function complete_export( int $export_id, ?string $renderer_id, string $message_class ): void {
		$this->repository->mark_completed( $export_id );

		/**
		 * Fires after an export has been successfully finalized and marked completed.
		 *
		 * This event also fires for successful exports that contain no items.
		 *
		 * @param int                  $export_id Completed export post ID.
		 * @param array<string, mixed> $context   Event context.
		 */
		ExportEventDispatcher::dispatch(
			ExportEvents::COMPLETED,
			$export_id,
			[
				'message'     => $message_class,
				'export_id'   => $export_id,
				'renderer_id' => $renderer_id,
			]
		);
	}

	private function maybe_apply_debug_delay(): void {
		$delay_seconds = absint( apply_filters( 'storeaccountant_export_queue_debug_delay_seconds', 0 ) );

		if ( $delay_seconds > 0 ) {
			sleep( $delay_seconds );
		}
	}
}
