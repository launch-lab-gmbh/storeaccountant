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

namespace StoreAccountant\Export\Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries one configured export filter and its settings.
 */
final readonly class ExportFilterSelection {
	/**
	 * Initializes the filter selection.
	 *
	 * @param string               $filter_id Filter identifier.
	 * @param array<string, mixed> $settings  Filter settings.
	 */
	public function __construct(
		public string $filter_id,
		public array $settings = []
	) {}
}
