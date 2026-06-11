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

use StoreAccountant\Export\Contract\FieldProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides registered export field providers.
 */
final readonly class FieldProviderRegistry extends Registry {
	/**
	 * Gets all fields for an export type.
	 *
	 * @param ExportContext $context Export context.
	 */
	public function get_fields( ExportContext $context ): FieldCollection {
		$fields = [];

		foreach ( $this->get_all() as $provider ) {
			if ( ! $provider->supports( $context ) ) {
				continue;
			}

			foreach ( $provider->get_fields( $context ) as $field ) {
				$fields[ $field->id ] = $field;
			}
		}

		return new FieldCollection( $fields );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_export_field_provider';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return FieldProviderInterface::class;
	}
}
