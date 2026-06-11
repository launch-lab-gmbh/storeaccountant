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

use StoreAccountant\Export\Filter\ExportFilterSelection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries the context needed to generate and write an export.
 */
final readonly class ExportPayload {
	public const OPTION_INCLUDE_ATTACHMENTS = 'include_attachments';

	/**
	 * Initializes the export payload.
	 *
	 * @param int                               $export_id   Export post ID.
	 * @param string                            $export_type Export adapter identifier.
	 * @param array<int, ExportFilterSelection> $filters    Configured export filters.
	 * @param array<string, mixed>              $options     Additional export options.
	 */
	public function __construct(
		public int $export_id,
		public string $export_type,
		public array $filters = [],
		public array $options = []
	) {}
}
