<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
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
use StoreAccountant\Export\Queue\BatchExportStore;
use StoreAccountant\Export\Queue\ExportDetailLogger;
use StoreAccountant\Export\Queue\Message\FinalizeExportAttachmentsMessage;
use StoreAccountant\Storage\Contract\ChunkedStorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;
use function __;
use function get_post_meta;
use function get_post_type;
use function is_wp_error;
use function max;
use function min;
use function sanitize_key;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appends export attachments to a storage artifact in queue-safe chunks.
 */
final readonly class FinalizeExportAttachmentsMessageHandler {
	public function __construct(
		private MessageBusInterface $message_bus,
		private StorageAdapterRegistry $storage_adapters,
		private ExportRepository $repository,
		private BatchExportStore $batch_store,
		private ExportDetailLogger $detail_logger
	) {}

	/**
	 * Handles one export attachment finalization chunk.
	 *
	 * @param FinalizeExportAttachmentsMessage $message Attachment finalization message.
	 */
	public function __invoke( FinalizeExportAttachmentsMessage $message ): true|WP_Error {
		if ( $message->export_id <= 0 || ExportPostType::POST_TYPE !== get_post_type( $message->export_id ) ) {
			return new WP_Error(
				'storeaccountant_export_finalize_message_invalid',
				__( 'The queued accounting export finalization is invalid.', 'storeaccountant' )
			);
		}

		if ( ExportStatus::COMPLETED === $this->repository->get_status( $message->export_id ) ) {
			return true;
		}

		$this->repository->mark_processing( $message->export_id, __( 'Finalizing export file.', 'storeaccountant' ) );
		$this->detail_logger->log(
			$message->export_id,
			'info',
			'export_attachment_chunk_started',
			[
				'message'           => FinalizeExportAttachmentsMessage::class,
				'export_id'         => $message->export_id,
				'renderer_id'       => $message->renderer_id,
				'storage_path'      => $message->storage_path,
				'offset'            => $message->offset,
				'limit'             => $message->limit,
				'total_attachments' => $message->total_attachments,
			]
		);

		try {
			$storage_adapter = $this->get_storage_adapter( $message->export_id );

			if ( is_wp_error( $storage_adapter ) ) {
				$this->repository->mark_failed_from_error(
					$message->export_id,
					$storage_adapter,
					[
						'message'     => FinalizeExportAttachmentsMessage::class,
						'export_id'   => $message->export_id,
						'renderer_id' => $message->renderer_id,
					]
				);

				return $storage_adapter;
			}

			$result = $storage_adapter->append_attachments(
				$message->storage_path,
				$this->batch_store->load_attachments( $message->export_id, $message->offset, $message->limit )
			);
		} catch ( Throwable $exception ) {
			$this->detail_logger->log(
				$message->export_id,
				'error',
				'export_attachment_chunk_failed',
				[
					'message'     => FinalizeExportAttachmentsMessage::class,
					'export_id'   => $message->export_id,
					'renderer_id' => $message->renderer_id,
					'offset'      => $message->offset,
					'limit'       => $message->limit,
				],
				$exception
			);
			$this->repository->mark_failed(
				$message->export_id,
				__( 'Unexpected export finalization error.', 'storeaccountant' ),
				$exception,
				[
					'message'     => FinalizeExportAttachmentsMessage::class,
					'export_id'   => $message->export_id,
					'renderer_id' => $message->renderer_id,
					'offset'      => $message->offset,
					'limit'       => $message->limit,
					'log_message' => 'Unexpected export attachment finalization error.',
				]
			);

			return new WP_Error(
				'storeaccountant_export_finalization_failed',
				__( 'The accounting export could not be finalized.', 'storeaccountant' )
			);
		}

		if ( is_wp_error( $result ) ) {
			$this->detail_logger->log(
				$message->export_id,
				'error',
				'export_attachment_chunk_failed',
				[
					'message'       => FinalizeExportAttachmentsMessage::class,
					'export_id'     => $message->export_id,
					'renderer_id'   => $message->renderer_id,
					'offset'        => $message->offset,
					'limit'         => $message->limit,
					'wp_error_code' => $result->get_error_code(),
				]
			);
			$this->repository->mark_failed_from_error(
				$message->export_id,
				$result,
				[
					'message'     => FinalizeExportAttachmentsMessage::class,
					'export_id'   => $message->export_id,
					'renderer_id' => $message->renderer_id,
					'offset'      => $message->offset,
					'limit'       => $message->limit,
				]
			);

			return $result;
		}

		$next_offset = min( $message->total_attachments, $message->offset + max( 1, $message->limit ) );
		$this->detail_logger->log(
			$message->export_id,
			'info',
			'export_attachment_chunk_processed',
			[
				'message'           => FinalizeExportAttachmentsMessage::class,
				'export_id'         => $message->export_id,
				'renderer_id'       => $message->renderer_id,
				'offset'            => $message->offset,
				'limit'             => $message->limit,
				'next_offset'       => $next_offset,
				'total_attachments' => $message->total_attachments,
			]
		);

		$this->repository->add_log_entry(
			$message->export_id,
			'info',
			'Export attachment finalization chunk processed.',
			[
				'message'           => FinalizeExportAttachmentsMessage::class,
				'export_id'         => $message->export_id,
				'renderer_id'       => $message->renderer_id,
				'offset'            => $message->offset,
				'limit'             => $message->limit,
				'next_offset'       => $next_offset,
				'total_attachments' => $message->total_attachments,
			]
		);

		if ( $next_offset < $message->total_attachments ) {
			$this->message_bus->dispatch(
				new FinalizeExportAttachmentsMessage(
					$message->export_id,
					$message->renderer_id,
					$message->storage_path,
					$next_offset,
					$message->limit,
					$message->total_attachments
				)
			);
			$this->detail_logger->log(
				$message->export_id,
				'info',
				'export_attachment_chunk_queued',
				[
					'message'           => FinalizeExportAttachmentsMessage::class,
					'export_id'         => $message->export_id,
					'renderer_id'       => $message->renderer_id,
					'next_offset'       => $next_offset,
					'limit'             => $message->limit,
					'total_attachments' => $message->total_attachments,
				]
			);

			return true;
		}

		$this->complete_export( $message );
		$this->detail_logger->log(
			$message->export_id,
			'info',
			'export_completed',
			[
				'message'      => FinalizeExportAttachmentsMessage::class,
				'export_id'    => $message->export_id,
				'renderer_id'  => $message->renderer_id,
				'storage_path' => $message->storage_path,
			]
		);

		return true;
	}

	/**
	 * Gets the configured storage adapter for the export.
	 *
	 * @param int $export_id Export post ID.
	 *
	 * @return ChunkedStorageAdapterInterface|WP_Error
	 */
	private function get_storage_adapter( int $export_id ): ChunkedStorageAdapterInterface|WP_Error {
		$storage_engine  = sanitize_key( (string) get_post_meta( $export_id, ExportPostType::META_STORAGE_ENGINE, true ) );
		$storage_adapter = '' !== $storage_engine ? $this->storage_adapters->get( $storage_engine ) : null;

		if ( ! $storage_adapter instanceof ChunkedStorageAdapterInterface ) {
			return new WP_Error(
				'storeaccountant_storage_engine_unavailable',
				__( 'The configured storage adapter is unavailable.', 'storeaccountant' )
			);
		}

		return $storage_adapter;
	}

	/**
	 * Completes an export after the final attachment chunk was written.
	 *
	 * @param FinalizeExportAttachmentsMessage $message Attachment finalization message.
	 */
	private function complete_export( FinalizeExportAttachmentsMessage $message ): void {
		$this->repository->update_path( $message->export_id, $message->storage_path );
		ExportEventDispatcher::dispatch(
			ExportEvents::ARTIFACT_PERSISTED,
			$message->export_id,
			[
				'export_id'      => $message->export_id,
				'storage_engine' => sanitize_key( (string) get_post_meta( $message->export_id, ExportPostType::META_STORAGE_ENGINE, true ) ),
				'storage_path'   => $message->storage_path,
			]
		);
		$this->batch_store->delete_export( $message->export_id );
		$this->repository->mark_completed( $message->export_id );

		ExportEventDispatcher::dispatch(
			ExportEvents::COMPLETED,
			$message->export_id,
			[
				'message'     => FinalizeExportAttachmentsMessage::class,
				'export_id'   => $message->export_id,
				'renderer_id' => $message->renderer_id,
			]
		);
	}
}
