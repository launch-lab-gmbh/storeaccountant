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

use Throwable;
use WP_Error;
use Symfony\Component\Messenger\MessageBusInterface;
use StoreAccountant\Export\Contract\BatchExportAdapterInterface;
use StoreAccountant\Export\Contract\ExportRendererSupportsAttachmentsInterface;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportDatasetBuilder;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Queue\BatchExportStore;
use StoreAccountant\Export\Queue\Message\FinalizeExportMessage;
use StoreAccountant\Export\Queue\Message\ProcessExportBatchMessage;
use function count;
use function is_countable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes one export source batch.
 */
final readonly class ProcessExportBatchMessageHandler {
	public function __construct(
		private MessageBusInterface $message_bus,
		private ExportAdapterRegistry $export_adapters,
		private ExportRepository $repository,
		private ExportFilterSelectionSerializer $filter_serializer,
		private ExportRendererRegistry $renderers,
		private ExportDatasetBuilder $dataset_builder,
		private BatchExportStore $batch_store
	) {}

	/**
	 * Handles the process batch message.
	 *
	 * @param ProcessExportBatchMessage $message Process batch message.
	 */
	public function __invoke( ProcessExportBatchMessage $message ): true|WP_Error {
		if ( $message->export_id <= 0 || ExportPostType::POST_TYPE !== get_post_type( $message->export_id ) ) {
			return new WP_Error(
				'storeaccountant_export_batch_message_invalid',
				__( 'The queued accounting export batch is invalid.', 'storeaccountant' )
			);
		}

		try {
			ExportEventDispatcher::dispatch(
				ExportEvents::LOG_ENTRY,
				$message->export_id,
				'info',
				'Export batch processing started.',
				[
					'message'      => ProcessExportBatchMessage::class,
					'export_id'    => $message->export_id,
					'batch_number' => $message->batch_number,
					'offset'       => $message->offset,
					'limit'        => $message->limit,
				]
			);

			$this->maybe_apply_debug_delay();

			$adapter = $this->export_adapters->get( $this->get_adapter_id( $message->export_id ) );

			if ( ! $adapter instanceof BatchExportAdapterInterface ) {
				return new WP_Error(
					'storeaccountant_export_batch_adapter_unavailable',
					__( 'The configured export adapter cannot process batches.', 'storeaccountant' )
				);
			}

			$items = $adapter->get_batch_items(
				$this->get_payload( $message->export_id, $adapter->get_id() ),
				$message->offset,
				$message->limit
			);

			if ( is_wp_error( $items ) ) {
				$this->repository->mark_failed_from_error(
					$message->export_id,
					$items,
					[
						'message'      => ProcessExportBatchMessage::class,
						'export_id'    => $message->export_id,
						'batch_number' => $message->batch_number,
					]
				);

				return $items;
			}

			$dataset = $this->dataset_builder->build_from_items(
				$adapter,
				$this->get_payload( $message->export_id, $adapter->get_id() ),
				$items
			);

			if ( is_wp_error( $dataset ) ) {
				$this->repository->mark_failed_from_error(
					$message->export_id,
					$dataset,
					[
						'message'      => ProcessExportBatchMessage::class,
						'export_id'    => $message->export_id,
						'batch_number' => $message->batch_number,
					]
				);

				return $dataset;
			}

			$stored = $this->batch_store->save_batch( $message->export_id, $message->batch_number, $dataset );

			if ( is_wp_error( $stored ) ) {
				$this->repository->mark_failed_from_error(
					$message->export_id,
					$stored,
					[
						'message'      => ProcessExportBatchMessage::class,
						'export_id'    => $message->export_id,
						'batch_number' => $message->batch_number,
					]
				);

				return $stored;
			}

			$this->repository->mark_batch_processed(
				$message->export_id,
				is_countable( $items ) ? count( $items ) : $message->limit
			);
			ExportEventDispatcher::dispatch(
				ExportEvents::BATCH_PROCESSED,
				$message->export_id,
				[
					'message'         => ProcessExportBatchMessage::class,
					'export_id'       => $message->export_id,
					'batch_number'    => $message->batch_number,
					'offset'          => $message->offset,
					'limit'           => $message->limit,
					'processed_items' => is_countable( $items ) ? count( $items ) : $message->limit,
				]
			);

			if ( $this->repository->all_batches_processed( $message->export_id ) ) {
				$renderer_id = sanitize_key( (string) get_post_meta( $message->export_id, ExportPostType::META_EXPORT_WRITER, true ) );
				$this->message_bus->dispatch( new FinalizeExportMessage( $message->export_id, '' !== $renderer_id ? $renderer_id : null ) );
				ExportEventDispatcher::dispatch(
					ExportEvents::FINALIZATION_QUEUED,
					$message->export_id,
					[
						'message'               => FinalizeExportMessage::class,
						'export_id'             => $message->export_id,
						'renderer_id'           => '' !== $renderer_id ? $renderer_id : null,
						'all_batches_processed' => true,
					]
				);
			}
		} catch ( Throwable $exception ) {
			$this->repository->mark_failed(
				$message->export_id,
				__( 'Unexpected export batch error.', 'storeaccountant' ),
				$exception,
				[
					'message'      => ProcessExportBatchMessage::class,
					'export_id'    => $message->export_id,
					'batch_number' => $message->batch_number,
					'offset'       => $message->offset,
					'limit'        => $message->limit,
					'log_message'  => 'Unexpected export batch error.',
				]
			);

			return new WP_Error(
				'storeaccountant_export_batch_failed',
				__( 'The accounting export batch could not be processed.', 'storeaccountant' )
			);
		}

		return true;
	}

	private function maybe_apply_debug_delay(): void {
		$delay_seconds = absint( apply_filters( 'storeaccountant_export_queue_debug_delay_seconds', 0 ) );

		if ( $delay_seconds > 0 ) {
			sleep( $delay_seconds );
		}
	}

	private function get_adapter_id( int $export_id ): string {
		return sanitize_key( (string) get_post_meta( $export_id, ExportPostType::META_EXPORT_ADAPTER, true ) );
	}

	private function get_payload( int $export_id, string $adapter_id ): ExportPayload {
		return new ExportPayload(
			$export_id,
			$adapter_id,
			$this->filter_serializer->decode( (string) get_post_meta( $export_id, ExportPostType::META_FILTERS, true ) ),
			[
				'configuration_id'                        => (int) get_post_meta( $export_id, ExportPostType::META_CONFIGURATION_ID, true ),
				ExportPayload::OPTION_INCLUDE_ATTACHMENTS => $this->renderer_supports_attachments( $export_id ),
			]
		);
	}

	/**
	 * Checks whether the configured renderer stores attachments.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function renderer_supports_attachments( int $export_id ): bool {
		$renderer_id = sanitize_key( (string) get_post_meta( $export_id, ExportPostType::META_EXPORT_WRITER, true ) );
		$renderer    = '' !== $renderer_id ? $this->renderers->get( $renderer_id ) : null;

		return $renderer instanceof ExportRendererSupportsAttachmentsInterface;
	}
}
