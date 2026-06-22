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

use Throwable;
use WC_Order;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Invoice\Contract\InvoicePluginInterface;
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
			try {
				$values['invoice_number'] = new FieldValue( 'invoice_number', $plugin->get_invoice_number( $item ) );
			} catch ( Throwable $exception ) {
				$this->log_invoice_plugin_warning( $context, $item, $plugin->get_id(), 'invoice_number', '', $exception );
				$values['invoice_number'] = new FieldValue( 'invoice_number', '' );
			}
		}

		if ( $fields->has( 'invoice_date' ) ) {
			try {
				$values['invoice_date'] = new FieldValue( 'invoice_date', $plugin->get_invoice_date( $item ) );
			} catch ( Throwable $exception ) {
				$this->log_invoice_plugin_warning( $context, $item, $plugin->get_id(), 'invoice_date', '', $exception );
				$values['invoice_date'] = new FieldValue( 'invoice_date', '' );
			}
		}

		if ( $fields->has( 'invoice_file_name' ) ) {
			try {
				$file_types = $this->settings->get_selected_file_types( $this->get_configuration_id( $context ), $plugin, $this->get_export_id( $context ) );
			} catch ( Throwable $exception ) {
				$this->log_invoice_plugin_warning( $context, $item, $plugin->get_id(), 'invoice_file_name', '', $exception );
				$file_types = [];
			}

			$values['invoice_file_name'] = new FieldValue(
				'invoice_file_name',
				implode(
					', ',
					array_filter(
						array_map(
							fn ( string $file_type ): string => $this->get_invoice_file_name( $item, $context, $plugin, $file_type ),
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
	 * Gets one invoice file name without failing the export on plugin errors.
	 *
	 * @param WC_Order      $order     WooCommerce order.
	 * @param ExportContext $context   Export context.
	 * @param InvoicePluginInterface $plugin Invoice plugin.
	 * @param string        $file_type Invoice file type.
	 */
	private function get_invoice_file_name( WC_Order $order, ExportContext $context, InvoicePluginInterface $plugin, string $file_type ): string {
		try {
			return $plugin->get_invoice_file_name( $order, $file_type );
		} catch ( Throwable $exception ) {
			$this->log_invoice_plugin_warning( $context, $order, $plugin->get_id(), 'invoice_file_name', $file_type, $exception );

			return '';
		}
	}

	/**
	 * Logs a non-fatal invoice plugin field error.
	 *
	 * @param ExportContext $context   Export context.
	 * @param WC_Order      $order     WooCommerce order.
	 * @param string        $plugin_id Invoice plugin ID.
	 * @param string        $field_id  Export field ID.
	 * @param string        $file_type Invoice file type, if known.
	 * @param Throwable     $exception Plugin exception.
	 */
	private function log_invoice_plugin_warning( ExportContext $context, WC_Order $order, string $plugin_id, string $field_id, string $file_type, Throwable $exception ): void {
		$export_id = $this->get_export_id( $context );

		if ( $export_id <= 0 ) {
			return;
		}

		ExportEventDispatcher::dispatch(
			ExportEvents::LOG_ENTRY,
			$export_id,
			'warning',
			'Invoice plugin error while resolving invoice export data.',
			[
				'export_id'         => $export_id,
				'order_id'          => $order->get_id(),
				'invoice_plugin_id' => $plugin_id,
				'field_id'          => $field_id,
				'invoice_file_type' => $file_type,
				'exception_message' => $exception->getMessage(),
				'value_skipped'     => true,
			],
			$exception
		);
	}

	/**
	 * Gets the export configuration ID from context.
	 *
	 * @param ExportContext $context Export context.
	 */
	private function get_configuration_id( ExportContext $context ): int {
		return $context->configuration_id;
	}

	/**
	 * Gets the export ID from context.
	 *
	 * @param ExportContext $context Export context.
	 */
	private function get_export_id( ExportContext $context ): int {
		return (int) $context->get( 'export_id', 0 );
	}
}
