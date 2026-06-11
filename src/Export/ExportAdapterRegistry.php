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

use StoreAccountant\Registry;
use StoreAccountant\Export\Contract\ExportAdapterInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides registered export adapters.
 */
final readonly class ExportAdapterRegistry extends Registry {
	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_export_adapter';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return ExportAdapterInterface::class;
	}
}
