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

namespace StoreAccountant\Export\Template;

use StoreAccountant\Export\Contract\ExportTemplateNormalizerInterface;
use StoreAccountant\Export\Contract\StreamingExportTemplateNormalizerInterface;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\ExportPayload;
use function iterator_to_array;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the default flat template structure for export datasets.
 */
final readonly class DefaultExportTemplateNormalizer implements
	ExportTemplateNormalizerInterface,
	StreamingExportTemplateNormalizerInterface {
	/**
	 * {@inheritDoc}
	 */
	public function normalize( ExportDataset $dataset, ExportPayload $payload ): array {
		return iterator_to_array( $this->normalize_iterable( $dataset, $payload ), false );
	}

	/**
	 * {@inheritDoc}
	 */
	public function normalize_iterable( ExportDataset $dataset, ExportPayload $payload ): iterable {
		$field_definitions = $dataset->fields->all();

		foreach ( $dataset->records as $record ) {
			$values = [];

			foreach ( $dataset->fields->ids() as $field_id ) {
				$field = $field_definitions[ $field_id ] ?? null;

				if ( null === $field ) {
					continue;
				}

				$values[ $field->label ] = $record->get_value( $field_id ) ?? '';
			}

			yield $values;
		}
	}
}
