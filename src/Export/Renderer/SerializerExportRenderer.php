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

namespace StoreAccountant\Export\Renderer;

use Throwable;
use WP_Error;
use Symfony\Component\Serializer\SerializerInterface;
use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\Contract\ExportTemplateNormalizerInterface;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\ExportArtifact;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Contract\WordPress\WordPressFilesystem;
use function function_exists;
use function is_file;
use function is_string;
use function wp_delete_file;
use function wp_tempnam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders normalized export data through a serializer format.
 */
final readonly class SerializerExportRenderer implements ExportRendererInterface {
	public const RENDERER_ID_JSON = 'json';

	public const FORMAT_JSON = 'json';

	/**
	 * Initializes the serializer export renderer.
	 *
	 * @param ExportTemplateNormalizerInterface $template_normalizer Template normalizer.
	 * @param SerializerInterface               $serializer          Serializer.
	 * @param string                            $renderer_id         Export renderer identifier.
	 * @param string                            $format              Serializer format.
	 * @param string                            $file_extension      Generated file extension.
	 * @param string                            $mime_type           Generated file MIME type.
	 */
	public function __construct(
		private ExportTemplateNormalizerInterface $template_normalizer,
		private SerializerInterface $serializer,
		private string $renderer_id,
		private string $format,
		private string $file_extension,
		private string $mime_type
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return $this->renderer_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_file_extension(): string {
		return $this->file_extension;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_mime_type(): string {
		return $this->mime_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( ExportDataset $dataset, ExportPayload $payload ): ExportArtifact|WP_Error {
		$this->load_file_helpers();

		$file_path = wp_tempnam( 'storeaccountant-export-' . $payload->export_id . '.' . $this->file_extension );

		if ( ! is_string( $file_path ) || '' === $file_path ) {
			return new WP_Error(
				'storeaccountant_serializer_temporary_file_failed',
				__( 'StoreAccountant could not create a temporary serialized export file.', 'storeaccountant' )
			);
		}

		$data = $this->template_normalizer->normalize( $dataset, $payload );

		if ( is_wp_error( $data ) ) {
			$this->delete_temporary_file( $file_path );

			return $data;
		}

		try {
			$serialized = $this->serializer->serialize( $data, $this->format );
		} catch ( Throwable $exception ) {
			$this->delete_temporary_file( $file_path );

			return new WP_Error(
				'storeaccountant_serializer_export_failed',
				__( 'StoreAccountant could not serialize the export data.', 'storeaccountant' ),
				[
					'exception' => [
						'class'   => $exception::class,
						'message' => $exception->getMessage(),
						'file'    => $exception->getFile(),
						'line'    => $exception->getLine(),
						'trace'   => $exception->getTraceAsString(),
					],
				]
			);
		}

		if ( ! WordPressFilesystem::put_contents( $file_path, $serialized ) ) {
			$this->delete_temporary_file( $file_path );

			return new WP_Error(
				'storeaccountant_serializer_export_write_failed',
				__( 'StoreAccountant could not write the serialized export.', 'storeaccountant' )
			);
		}

		return new ExportArtifact(
			$file_path,
			$this->get_file_extension(),
			$this->get_mime_type()
		);
	}

	/**
	 * Deletes the generated temporary file if it exists.
	 *
	 * @param string $file_path Temporary file path.
	 */
	private function delete_temporary_file( string $file_path ): void {
		if ( is_file( $file_path ) ) {
			$this->load_file_helpers();
			wp_delete_file( $file_path );
		}
	}

	/**
	 * Loads WordPress file helpers when the renderer runs outside admin bootstrap.
	 */
	private function load_file_helpers(): void {
		if ( function_exists( 'wp_tempnam' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
}
