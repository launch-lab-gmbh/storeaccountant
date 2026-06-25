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

namespace StoreAccountant\Export\Dataset;

use StoreAccountant\Export\Field\FieldCollection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds normalized export data independent of the final file format.
 */
final readonly class ExportDataset {
	/**
	 * Initializes the export dataset.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param FieldCollection        $fields  Dataset fields keyed by identifier.
	 * @param iterable<ExportRecord> $records Dataset records.
	 * @param iterable<mixed>        $attachments Additional export attachments.
	 * @param array<string, mixed>   $options     Additional dataset options.
	 */
	public function __construct(
		public FieldCollection $fields,
		public iterable $records,
		public iterable $attachments = [],
		public array $options = []
	) {}
}
