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

namespace StoreAccountant\Invoice\Export\Order\Field;

use WC_Order;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Invoice\Export\Order\InvoiceExportAttachmentSettings;
use StoreAccountant\Invoice\InvoicePluginDetector;
use function array_filter;
use function array_map;
use function implode;
use function in_array;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves invoice field values for WooCommerce order exports.
 */
final readonly class InvoiceFieldValueProvider implements FieldValueProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'order_invoice_values';

	/**
	 * Initializes the provider.
	 *
	 * @param InvoicePluginDetector           $detector Invoice plugin detector.
	 * @param InvoiceExportAttachmentSettings $settings Invoice attachment settings.
	 */
	public function __construct(
		private InvoicePluginDetector $detector,
		private InvoiceExportAttachmentSettings $settings
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_field_value_provider',
			function ( array $providers ): array {
				$providers[ $this->get_id() ] = $this;

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
		return OrderExportAdapter::ADAPTER_ID === $context->export_type && in_array( $field->id, [ 'invoice_number', 'invoice_date', 'invoice_file_name' ], true ) && $this->detector->is_enabled();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_values( mixed $item, FieldCollection $fields, ExportContext $context ): array {
		$plugin = $this->detector->get_enabled();

		if ( OrderExportAdapter::ADAPTER_ID !== $context->export_type || ! $item instanceof WC_Order || null === $plugin ) {
			return [];
		}

		$values = [];

		if ( $fields->has( 'invoice_number' ) ) {
			$values['invoice_number'] = new FieldValue( 'invoice_number', $plugin->get_invoice_number( $item ) );
		}

		if ( $fields->has( 'invoice_date' ) ) {
			$values['invoice_date'] = new FieldValue( 'invoice_date', $plugin->get_invoice_date( $item ) );
		}

		if ( $fields->has( 'invoice_file_name' ) ) {
			$file_types = $this->settings->get_selected_file_types( $this->get_configuration_id( $context ), $plugin );

			$values['invoice_file_name'] = new FieldValue(
				'invoice_file_name',
				implode(
					', ',
					array_filter(
						array_map(
							fn ( string $file_type ): string => $plugin->get_invoice_file_name( $item, $file_type ),
							$file_types
						),
						static fn ( string $file_name ): bool => '' !== $file_name
					)
				)
			);
		}

		return $values;
	}

	/**
	 * Gets the export configuration ID from context.
	 *
	 * @param ExportContext $context Export context.
	 */
	private function get_configuration_id( ExportContext $context ): int {
		return $context->configuration_id;
	}
}
