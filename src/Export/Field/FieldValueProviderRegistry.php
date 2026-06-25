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

namespace StoreAccountant\Export\Field;

use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides registered export field value providers.
 */
final readonly class FieldValueProviderRegistry extends Registry {
	/**
	 * Gets all value providers that support at least one selected field.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param FieldCollection $fields  Selected fields.
	 * @param ExportContext   $context Export context.
	 *
	 * @return array<string, FieldValueProviderInterface>
	 */
	public function get_providers( FieldCollection $fields, ExportContext $context ): array {
		$providers = [];

		foreach ( $this->get_all() as $provider ) {
			foreach ( $fields as $field ) {
				if ( $provider->supports( $field, $context ) ) {
					$providers[ $provider->get_id() ] = $provider;
					break;
				}
			}
		}

		return $providers;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_export_field_value_provider';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return FieldValueProviderInterface::class;
	}
}
