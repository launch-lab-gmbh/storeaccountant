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
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportDatasetBuilder;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Contract\ExportRendererSupportsAttachmentsInterface;
use StoreAccountant\Export\Queue\BatchExportStore;
use StoreAccountant\Export\Queue\Message\FinalizeExportMessage;
use StoreAccountant\Export\Queue\Message\ProcessExportBatchMessage;
use StoreAccountant\Export\Queue\Message\StartExportMessage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts background generation for a saved export by scheduling batches.
 */
final readonly class StartExportMessageHandler {
	/**
	 * Initializes the handler.
	 *
	 * @param MessageBusInterface             $message_bus       Message bus.
	 * @param ExportAdapterRegistry           $export_adapters   Export adapter registry.
	 * @param ExportRepository                $repository        Export repository.
	 * @param ExportFilterSelectionSerializer $filter_serializer Filter serializer.
	 * @param ExportRendererRegistry          $renderers         Export renderer registry.
	 * @param ExportDatasetBuilder            $dataset_builder   Export dataset builder.
	 * @param BatchExportStore                $batch_store       Batch export store.
	 */
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
	 * Handles the start export message.
	 *
	 * @param StartExportMessage $message Start export message.
	 */
	public function __invoke( StartExportMessage $message ): true|WP_Error {
		if ( $message->export_id <= 0 || ExportPostType::POST_TYPE !== get_post_type( $message->export_id ) ) {
			return new WP_Error(
				'storeaccountant_export_message_invalid',
				__( 'The queued accounting export is invalid.', 'storeaccountant' )
			);
		}

		if ( ExportStatus::COMPLETED === $this->repository->get_status( $message->export_id ) ) {
			return true;
		}

		ExportEventDispatcher::dispatch(
			ExportEvents::STARTED,
			$message->export_id,
			[
				'message'     => StartExportMessage::class,
				'export_id'   => $message->export_id,
				'renderer_id' => $message->renderer_id,
			]
		);
		$this->repository->mark_processing( $message->export_id, __( 'Preparing export batches.', 'storeaccountant' ) );

		try {
			$adapter = $this->export_adapters->get( $this->get_adapter_id( $message->export_id ) );

			if ( ! $adapter instanceof BatchExportAdapterInterface ) {
				return new WP_Error(
					'storeaccountant_export_batch_adapter_unavailable',
					__( 'The configured export adapter cannot process batches.', 'storeaccountant' )
				);
			}

			$payload     = $this->get_payload( $message->export_id, $adapter->get_id() );
			$total_items = $adapter->count_items( $payload );

			if ( is_wp_error( $total_items ) ) {
				$this->repository->mark_failed_from_error(
					$message->export_id,
					$total_items,
					[
						'message'   => StartExportMessage::class,
						'export_id' => $message->export_id,
					]
				);

				return $total_items;
			}

			$batch_size        = $this->get_batch_size( $message->export_id );
				$total_batches = max( 1, (int) ceil( $total_items / $batch_size ) );

				$this->repository->initialize_progress( $message->export_id, $total_items, $total_batches );
					ExportEventDispatcher::dispatch(
						ExportEvents::BATCHES_CALCULATED,
						$message->export_id,
						[
							'message'       => StartExportMessage::class,
							'export_id'     => $message->export_id,
							'adapter_id'    => $adapter->get_id(),
							'total_items'   => $total_items,
							'total_batches' => $total_batches,
							'batch_size'    => $batch_size,
						]
					);

			if ( 0 === $total_items ) {
				$empty_dataset = $this->dataset_builder->build_from_items( $adapter, $payload, [] );

				if ( is_wp_error( $empty_dataset ) ) {
					$this->repository->mark_failed_from_error(
						$message->export_id,
						$empty_dataset,
						[
							'message'   => StartExportMessage::class,
							'export_id' => $message->export_id,
						]
					);

					return $empty_dataset;
				}

				$stored = $this->batch_store->save_batch( $message->export_id, 1, $empty_dataset );

				if ( is_wp_error( $stored ) ) {
					$this->repository->mark_failed_from_error(
						$message->export_id,
						$stored,
						[
							'message'   => StartExportMessage::class,
							'export_id' => $message->export_id,
						]
					);

					return $stored;
				}

					$this->repository->mark_batch_processed( $message->export_id, 0 );
						ExportEventDispatcher::dispatch(
							ExportEvents::BATCH_PROCESSED,
							$message->export_id,
							[
								'message'         => StartExportMessage::class,
								'export_id'       => $message->export_id,
								'batch_number'    => 1,
								'processed_items' => 0,
							]
						);
					$this->message_bus->dispatch( new FinalizeExportMessage( $message->export_id, $message->renderer_id ) );
						ExportEventDispatcher::dispatch(
							ExportEvents::FINALIZATION_QUEUED,
							$message->export_id,
							[
								'message'     => FinalizeExportMessage::class,
								'export_id'   => $message->export_id,
								'renderer_id' => $message->renderer_id,
							]
						);

				return true;
			}

			for ( $batch_number = 1; $batch_number <= $total_batches; ++$batch_number ) {
				$this->message_bus->dispatch(
					new ProcessExportBatchMessage(
						$message->export_id,
						$batch_number,
						( $batch_number - 1 ) * $batch_size,
						$batch_size
					)
				);
			}

					ExportEventDispatcher::dispatch(
						ExportEvents::BATCH_JOBS_QUEUED,
						$message->export_id,
						[
							'message'       => StartExportMessage::class,
							'export_id'     => $message->export_id,
							'total_batches' => $total_batches,
						]
					);
		} catch ( Throwable $exception ) {
			$this->repository->mark_failed(
				$message->export_id,
				__( 'Unexpected export generation error.', 'storeaccountant' ),
				$exception,
				[
					'message'     => StartExportMessage::class,
					'export_id'   => $message->export_id,
					'renderer_id' => $message->renderer_id,
					'log_message' => 'Unexpected export generation error.',
				]
			);

			return new WP_Error(
				'storeaccountant_export_generation_failed',
				__( 'The accounting export could not be generated.', 'storeaccountant' )
			);
		}

		return true;
	}

	/**
	 * Gets the configured export adapter identifier.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function get_adapter_id( int $export_id ): string {
		return sanitize_key( (string) get_post_meta( $export_id, ExportPostType::META_EXPORT_ADAPTER, true ) );
	}

	/**
	 * Builds the export payload for a saved export.
	 *
	 * @param int    $export_id  Export post ID.
	 * @param string $adapter_id Export adapter ID.
	 */
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

	/**
	 * Gets the configured batch size.
	 */
	private function get_batch_size( int $export_id ): int {
		$batch_size = (int) get_post_meta( $export_id, ExportPostType::META_BATCH_SIZE, true );
		$batch_size = $batch_size > 0 ? $batch_size : ExportPostType::DEFAULT_BATCH_SIZE;
		$batch_size = (int) apply_filters( 'storeaccountant_export_batch_size', $batch_size, $export_id );

		return max( ExportPostType::MIN_BATCH_SIZE, $batch_size );
	}
}
