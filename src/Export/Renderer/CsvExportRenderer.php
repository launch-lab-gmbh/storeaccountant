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
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\Contract\ExportRendererSupportsAttachmentsInterface;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\Dataset\ExportRecord;
use StoreAccountant\Export\ExportArtifact;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\Field;
use function array_map;
use function array_values;
use function function_exists;
use function is_file;
use function is_string;
use function wp_delete_file;
use function wp_tempnam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders normalized export data to a CSV artifact.
 */
final readonly class CsvExportRenderer implements ExportRendererInterface, ExportRendererSupportsAttachmentsInterface, HookRegistrarInterface {
	public const RENDERER_ID = 'csv';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_renderer',
			function ( array $renderers ): array {
				$renderers[ self::RENDERER_ID ] = $this;

				return $renderers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::RENDERER_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_file_extension(): string {
		return 'csv';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_mime_type(): string {
		return 'text/csv';
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( ExportDataset $dataset, ExportPayload $payload ): ExportArtifact|WP_Error {
		$this->load_file_helpers();

		$file_path = wp_tempnam( 'storeaccountant-export-' . $payload->export_id . '.csv' );

		if ( ! is_string( $file_path ) || '' === $file_path ) {
			return new WP_Error(
				'storeaccountant_csv_temporary_file_failed',
				__( 'StoreAccountant could not create a temporary CSV export file.', 'storeaccountant' )
			);
		}

		$generated = false;
		$writer    = null;

		try {
			$writer = new Writer();
			$writer->openToFile( $file_path );
			$writer->addRow( Row::fromValues( $this->get_header( $dataset ) ) );

			foreach ( $dataset->records as $record ) {
				$writer->addRow( Row::fromValues( $this->get_row( $dataset, $record ) ) );
			}

			$writer->close();
			$writer    = null;
			$generated = true;
		} catch ( Throwable $exception ) {
			return new WP_Error(
				'storeaccountant_csv_export_failed',
				__( 'StoreAccountant could not write the CSV export.', 'storeaccountant' ),
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
		} finally {
			if ( $writer instanceof Writer ) {
				try {
					$writer->close();
				} catch ( Throwable $close_exception ) {
					unset( $close_exception );
					// The original export error is returned from the catch block above.
				}
			}

			if ( ! $generated && is_file( $file_path ) ) {
				$this->load_file_helpers();
				wp_delete_file( $file_path );
			}
		}

		return new ExportArtifact(
			$file_path,
			$this->get_file_extension(),
			$this->get_mime_type(),
			$dataset->attachments
		);
	}

	/**
	 * Gets the CSV header row.
	 *
	 * @param ExportDataset $dataset Export dataset.
	 *
	 * @return array<int, string>
	 */
	private function get_header( ExportDataset $dataset ): array {
		return array_values(
			array_map(
				static fn ( Field $field ): string => $field->label,
				$dataset->fields->all()
			)
		);
	}

	/**
	 * Gets a CSV row.
	 *
	 * @param ExportDataset $dataset Export dataset.
	 * @param ExportRecord  $record  Export record.
	 *
	 * @return array<int, mixed>
	 */
	private function get_row( ExportDataset $dataset, ExportRecord $record ): array {
		$row = [];

		foreach ( $dataset->fields->ids() as $field_id ) {
			$row[] = $record->get_value( $field_id ) ?? '';
		}

		return $row;
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
