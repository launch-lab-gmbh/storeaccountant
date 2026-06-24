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

namespace StoreAccountant\Export\Queue;

use WP_Error;
use StoreAccountant\Export\Contract\ExportRendererSupportsAttachmentsInterface;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStoragePathGenerator;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Renderer\CsvExportRenderer;
use StoreAccountant\Storage\Contract\ChunkedStorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;
use StoreAccountant\Storage\StorageFileConfiguration;
use function function_exists;
use function is_file;
use function is_string;
use function max;
use function sanitize_key;
use function wp_delete_file;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and persists final artifacts from queued batch fragments.
 */
final readonly class QueuedExportFinalizer {
	/**
	 * Initializes the finalizer.
	 *
	 * @param BatchExportStore                $batch_store            Temporary batch store.
	 * @param StorageAdapterRegistry          $storage_adapters       Storage adapter registry.
	 * @param ExportAdapterRegistry           $export_adapters        Export adapter registry.
	 * @param ExportRendererRegistry          $renderer_registry      Export renderer registry.
	 * @param ExportRepository                $repository             Export repository.
	 * @param ExportStoragePathGenerator      $storage_path_generator Storage path generator.
	 * @param ExportFilterSelectionSerializer $filter_serializer      Filter selection serializer.
	 */
	public function __construct(
		private BatchExportStore $batch_store,
		private StorageAdapterRegistry $storage_adapters,
		private ExportAdapterRegistry $export_adapters,
		private ExportRendererRegistry $renderer_registry,
		private ExportRepository $repository,
		private ExportStoragePathGenerator $storage_path_generator,
		private ExportFilterSelectionSerializer $filter_serializer
	) {}

	/**
	 * Finalizes a queued export.
	 *
	 * @param int         $post_id     Export post ID.
	 * @param string|null $renderer_id Renderer ID.
	 *
	 * @return QueuedExportFinalizationResult|WP_Error
	 */
	public function finalize( int $post_id, ?string $renderer_id = null ): QueuedExportFinalizationResult|WP_Error {
		$export_adapter = $this->export_adapters->get( $this->get_adapter_id( $post_id ) );

		if ( null === $export_adapter ) {
			return new WP_Error(
				'storeaccountant_export_adapter_unavailable',
				__( 'The configured export adapter is unavailable.', 'storeaccountant' )
			);
		}

		$renderer = $this->renderer_registry->get( $this->get_renderer_id( $post_id, $renderer_id ) );

		if ( null === $renderer ) {
			return new WP_Error(
				'storeaccountant_export_renderer_unavailable',
				__( 'The configured export renderer is unavailable.', 'storeaccountant' )
			);
		}

		$storage_engine  = (string) get_post_meta( $post_id, ExportPostType::META_STORAGE_ENGINE, true );
		$storage_adapter = '' !== $storage_engine ? $this->storage_adapters->get( sanitize_key( $storage_engine ) ) : null;

		if ( null === $storage_adapter ) {
			return new WP_Error(
				'storeaccountant_storage_engine_unavailable',
				__( 'The configured storage adapter is unavailable.', 'storeaccountant' )
			);
		}

		$dataset = $this->batch_store->load_dataset( $post_id );

		if ( is_wp_error( $dataset ) ) {
			return $dataset;
		}

		ExportEventDispatcher::dispatch(
			ExportEvents::DATASET_LOADED,
			$post_id,
			[
				'export_id'   => $post_id,
				'adapter_id'  => $export_adapter->get_id(),
				'renderer_id' => $renderer->get_id(),
			]
		);

		$payload = new ExportPayload(
			$post_id,
			$export_adapter->get_id(),
			$this->filter_serializer->decode( (string) get_post_meta( $post_id, ExportPostType::META_FILTERS, true ) ),
			[
				'configuration_id'                        => (int) get_post_meta( $post_id, ExportPostType::META_CONFIGURATION_ID, true ),
				ExportPayload::OPTION_INCLUDE_ATTACHMENTS => $renderer instanceof ExportRendererSupportsAttachmentsInterface,
			]
		);

		$artifact = $renderer->render( $dataset, $payload );

		if ( is_wp_error( $artifact ) ) {
			return $artifact;
		}

		ExportEventDispatcher::dispatch(
			ExportEvents::ARTIFACT_RENDERED,
			$post_id,
			[
				'export_id'   => $post_id,
				'renderer_id' => $renderer->get_id(),
				'format'      => $artifact->file_extension,
				'mime_type'   => $artifact->mime_type,
			]
		);

		try {
			$storage_file_configuration = $this->storage_path_generator->generate( $post_id, $storage_adapter->get_id(), $artifact );
			$result                     = $storage_adapter instanceof ChunkedStorageAdapterInterface
				? $this->start_chunked_persist( $post_id, $storage_adapter, $storage_file_configuration )
				: $this->persist_complete_export( $post_id, $storage_adapter->persist( $storage_file_configuration ), $storage_adapter->get_id() );
		} finally {
			if ( is_file( $artifact->source_path ) ) {
				$this->delete_file( $artifact->source_path );
			}
		}

		return $result;
	}

	/**
	 * Starts a chunked persist operation for storage adapters that can append attachments later.
	 *
	 * @param int                            $post_id       Export post ID.
	 * @param ChunkedStorageAdapterInterface $storage       Chunked storage adapter.
	 * @param StorageFileConfiguration       $configuration Storage file configuration.
	 *
	 * @return QueuedExportFinalizationResult|WP_Error
	 */
	private function start_chunked_persist(
		int $post_id,
		ChunkedStorageAdapterInterface $storage,
		StorageFileConfiguration $configuration
	): QueuedExportFinalizationResult|WP_Error {
		$storage_path = $storage->start_persist( $configuration );

		if ( is_wp_error( $storage_path ) ) {
			return $storage_path;
		}

		$total_attachments = $this->batch_store->count_attachments( $post_id );

		if ( $total_attachments > 0 ) {
			return QueuedExportFinalizationResult::pending(
				$storage_path,
				$total_attachments,
				$this->get_attachment_batch_size( $post_id )
			);
		}

		return $this->persist_complete_export( $post_id, $storage_path, $this->get_storage_adapter_id( $post_id ) );
	}

	/**
	 * Persists final export metadata and removes temporary batch state.
	 *
	 * @param int             $post_id        Export post ID.
	 * @param string|WP_Error $storage_path   Stored export path.
	 * @param string          $storage_engine Storage engine ID.
	 *
	 * @return QueuedExportFinalizationResult|WP_Error
	 */
	private function persist_complete_export( int $post_id, string|WP_Error $storage_path, string $storage_engine ): QueuedExportFinalizationResult|WP_Error {
		if ( is_wp_error( $storage_path ) ) {
			return $storage_path;
		}

		$this->repository->update_path( $post_id, $storage_path );
		ExportEventDispatcher::dispatch(
			ExportEvents::ARTIFACT_PERSISTED,
			$post_id,
			[
				'export_id'      => $post_id,
				'storage_engine' => $storage_engine,
				'storage_path'   => $storage_path,
			]
		);
		$this->batch_store->delete_export( $post_id );

		return QueuedExportFinalizationResult::complete( $storage_path );
	}

	/**
	 * Gets the attachment chunk size from the export's configured batch size.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function get_attachment_batch_size( int $post_id ): int {
		$batch_size = (int) get_post_meta( $post_id, ExportPostType::META_BATCH_SIZE, true );

		return max( ExportPostType::MIN_BATCH_SIZE, $batch_size > 0 ? $batch_size : ExportPostType::DEFAULT_BATCH_SIZE );
	}

	/**
	 * Gets the configured export adapter identifier.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function get_adapter_id( int $post_id ): string {
		$adapter_id = (string) get_post_meta( $post_id, ExportPostType::META_EXPORT_ADAPTER, true );

		if ( '' !== $adapter_id && null !== $this->export_adapters->get( $adapter_id ) ) {
			return sanitize_key( $adapter_id );
		}

		return '';
	}

	/**
	 * Gets the configured storage adapter identifier.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function get_storage_adapter_id( int $post_id ): string {
		return sanitize_key( (string) get_post_meta( $post_id, ExportPostType::META_STORAGE_ENGINE, true ) );
	}

	/**
	 * Gets the configured export renderer identifier.
	 *
	 * @param int         $post_id     Export post ID.
	 * @param string|null $renderer_id Export renderer identifier.
	 */
	private function get_renderer_id( int $post_id, ?string $renderer_id = null ): string {
		if ( null === $renderer_id || '' === $renderer_id ) {
			$renderer_id = (string) get_post_meta( $post_id, ExportPostType::META_EXPORT_WRITER, true );
		}

		if ( '' === $renderer_id ) {
			$option_renderer_id = get_option( 'storeaccountant_export_writer', CsvExportRenderer::RENDERER_ID );
			$renderer_id        = is_string( $option_renderer_id ) ? $option_renderer_id : CsvExportRenderer::RENDERER_ID;
		}

		return '' !== $renderer_id ? sanitize_key( $renderer_id ) : CsvExportRenderer::RENDERER_ID;
	}

	/**
	 * Deletes a file.
	 *
	 * @param string $path File path.
	 */
	private function delete_file( string $path ): void {
		if ( ! function_exists( 'wp_delete_file' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		wp_delete_file( $path );
	}
}
