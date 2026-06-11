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

namespace StoreAccountant\Order\Export\Field\Provider;

use WC_Order;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Meta\MetaFieldValueFormatter;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides values for custom WooCommerce order meta export fields.
 */
final readonly class OrderMetaFieldValueProvider implements FieldValueProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'order_meta';

	/**
	 * Initializes the order meta field value provider.
	 *
	 * @param MetaFieldValueFormatter $formatter Metadata value formatter.
	 */
	public function __construct(
		private MetaFieldValueFormatter $formatter
	) {}

	/**
	 * {@inheritDoc}
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
	 */
	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( Field $field, ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type
			&& MetaField::is_meta_field( $field, OrderMetaFieldProvider::FIELD_ID_PREFIX );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_values( mixed $item, FieldCollection $fields, ExportContext $context ): array {
		if ( OrderExportAdapter::ADAPTER_ID !== $context->export_type || ! $item instanceof WC_Order ) {
			return [];
		}

		$values = [];

		foreach ( $fields as $field ) {
			$meta_key = (string) ( $field->options[ OrderMetaFieldProvider::OPTION_META_KEY ] ?? '' );

			if ( '' === $meta_key ) {
				continue;
			}

			$values[ $field->id ] = new FieldValue( $field->id, $this->formatter->format( $item->get_meta( $meta_key, true ) ) );
		}

		return $values;
	}
}
