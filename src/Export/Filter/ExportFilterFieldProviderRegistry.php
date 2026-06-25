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
use StoreAccountant\Export\Filter\Contract\ExportFilterFieldProviderInterface;
use StoreAccountant\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects export filter field providers.
 */
final readonly class ExportFilterFieldProviderRegistry extends Registry {
	/**
	 * Gets field providers for an export type.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $export_type Export adapter identifier.
	 *
	 * @return array<string, ExportFilterFieldProviderInterface>
	 */
	public function get_providers( string $export_type ): array {
		$providers = [];

		foreach ( $this->get_all() as $provider ) {
			if ( $provider->supports( $export_type ) ) {
				$providers[ $provider->get_id() ] = $provider;
			}
		}

		return $providers;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_export_filter_field_provider';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return class-string<RegistryItemInterface>
	 */
	protected function get_type(): string {
		return ExportFilterFieldProviderInterface::class;
	}
}
