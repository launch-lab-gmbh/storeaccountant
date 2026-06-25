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

namespace StoreAccountant\Export;

use WP_Error;
use StoreAccountant\Export\Contract\ExportRendererSupportsAttachmentsInterface;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Renderer\CsvExportRenderer;
use StoreAccountant\Storage\StorageAdapterRegistry;
use function function_exists;
use function is_file;
use function is_string;
use function wp_delete_file;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates export generation.
 */
final readonly class Exporter {
	private const OPTION_RENDERER_ID = 'storeaccountant_export_writer';

	/**
	 * Initializes the exporter.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param StorageAdapterRegistry          $storage_adapters  storage adapter registry.
	 * @param ExportAdapterRegistry           $export_adapters   Export adapter registry.
	 * @param ExportRendererRegistry          $renderer_registry Export renderer registry.
	 * @param ExportDatasetBuilder            $dataset_builder   Export dataset builder.
	 * @param ExportRepository                $repository         Export repository.
	 * @param ExportStoragePathGenerator      $storage_path_generator Storage path generator.
	 * @param ExportFilterSelectionSerializer $filter_serializer Filter selection serializer.
	 */
	public function __construct(
		private StorageAdapterRegistry $storage_adapters,
		private ExportAdapterRegistry $export_adapters,
		private ExportRendererRegistry $renderer_registry,
		private ExportDatasetBuilder $dataset_builder,
		private ExportRepository $repository,
		private ExportStoragePathGenerator $storage_path_generator,
		private ExportFilterSelectionSerializer $filter_serializer
	) {}

	/**
	 * Generates the export file for a saved export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int         $post_id          Export post ID.
	 * @param string|null $renderer_id      Export renderer identifier.
	 *
	 * @return true|WP_Error
	 */
	public function export( int $post_id, ?string $renderer_id = null ): true|WP_Error {
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

		$include_attachments = $renderer instanceof ExportRendererSupportsAttachmentsInterface;

		$payload = new ExportPayload(
			$post_id,
			$export_adapter->get_id(),
			$this->filter_serializer->decode( (string) get_post_meta( $post_id, ExportPostType::META_FILTERS, true ) ),
			[
				'configuration_id'                        => (int) get_post_meta( $post_id, ExportPostType::META_CONFIGURATION_ID, true ),
				ExportPayload::OPTION_INCLUDE_ATTACHMENTS => $include_attachments,
			]
		);

		$dataset = $this->dataset_builder->build( $export_adapter, $payload );

		if ( is_wp_error( $dataset ) ) {
			return $dataset;
		}

		$artifact = $renderer->render( $dataset, $payload );

		if ( is_wp_error( $artifact ) ) {
			return $artifact;
		}

		try {
			$storage_file_configuration = $this->storage_path_generator->generate( $post_id, $storage_adapter->get_id(), $artifact );
			$storage_path               = $storage_adapter->persist( $storage_file_configuration );
		} finally {
			if ( is_file( $artifact->source_path ) ) {
				$this->delete_file( $artifact->source_path );
			}
		}

		if ( is_wp_error( $storage_path ) ) {
			return $storage_path;
		}

		$this->repository->update_path( $post_id, $storage_path );

		return true;
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
			$option_renderer_id = get_option( self::OPTION_RENDERER_ID, CsvExportRenderer::RENDERER_ID );
			$renderer_id        = is_string( $option_renderer_id ) ? $option_renderer_id : CsvExportRenderer::RENDERER_ID;
		}

		if ( '' === $renderer_id ) {
			$renderer_id = CsvExportRenderer::RENDERER_ID;
		}

		return sanitize_key( $renderer_id );
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
