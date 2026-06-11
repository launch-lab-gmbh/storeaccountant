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

use StoreAccountant\Export\Contract\ExportReadTabProviderInterface;
use StoreAccountant\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides registered saved export read-view tab providers.
 */
final readonly class ExportReadTabProviderRegistry extends Registry {
	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_export_read_tab_provider';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return ExportReadTabProviderInterface::class;
	}
}
