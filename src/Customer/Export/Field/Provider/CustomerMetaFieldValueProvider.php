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

namespace StoreAccountant\Customer\Export\Field\Provider;

use WC_Customer;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Meta\MetaFieldValueFormatter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides values for custom WooCommerce customer meta export fields.
 */
final readonly class CustomerMetaFieldValueProvider implements FieldValueProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'customer_meta';

	/**
	 * Initializes the customer meta field value provider.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param MetaFieldValueFormatter $formatter Metadata value formatter.
	 */
	public function __construct(
		private MetaFieldValueFormatter $formatter
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_field_value_provider',
			function ( array $providers ): array {
				$providers[ self::PROVIDER_ID ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports( Field $field, ExportContext $context ): bool {
		return CustomerExportAdapter::ADAPTER_ID === $context->export_type
			&& MetaField::is_meta_field( $field, CustomerMetaFieldProvider::FIELD_ID_PREFIX );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_values( mixed $item, FieldCollection $fields, ExportContext $context ): array {
		if ( CustomerExportAdapter::ADAPTER_ID !== $context->export_type || ! $item instanceof WC_Customer ) {
			return [];
		}

		$values = [];

		foreach ( $fields as $field ) {
			$meta_key = (string) ( $field->options[ CustomerMetaFieldProvider::OPTION_META_KEY ] ?? '' );

			if ( '' === $meta_key ) {
				continue;
			}

			$values[ $field->id ] = new FieldValue( $field->id, $this->formatter->format( $item->get_meta( $meta_key, true ) ) );
		}

		return $values;
	}
}
