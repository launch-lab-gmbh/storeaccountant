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

use StoreAccountant\Contract\RegistryItemInterface;
use StoreAccountant\Export\Filter\Contract\ExportFilterInterface;
use StoreAccountant\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects export query filters.
 */
final readonly class ExportFilterRegistry extends Registry {
	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_export_filter';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return class-string<RegistryItemInterface>
	 */
	protected function get_type(): string {
		return ExportFilterInterface::class;
	}
}
