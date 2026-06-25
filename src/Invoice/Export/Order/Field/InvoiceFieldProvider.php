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

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Contract\FieldProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Invoice\InvoicePluginDetector;
use function sanitize_key;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides invoice fields for WooCommerce order exports.
 */
final readonly class InvoiceFieldProvider implements FieldProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID              = 'order_invoices';
	public const OPTION_INVOICE_FILE_TYPE = 'invoice_file_type';

	/**
	 * Initializes the provider.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param InvoicePluginDetector $detector Invoice plugin detector.
	 */
	public function __construct(
		private InvoicePluginDetector $detector
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_field_provider',
			function ( array $providers ): array {
				$providers[ $this->get_id() ] = $this;

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
	public function supports( ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type && $this->detector->is_enabled();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_fields( ExportContext $context ): array {
		$fields = [
			'invoice_number' => new Field( 'invoice_number', 'invoice_number' ),
			'invoice_date'   => new Field( 'invoice_date', 'invoice_date', new DateTimeFieldType() ),
		];

		$plugin = $this->detector->get_enabled();

		if ( null === $plugin ) {
			return $fields;
		}

		foreach ( $plugin->get_invoice_file_types() as $file_type ) {
			$type_id = sanitize_key( $file_type->id );

			if ( '' === $type_id ) {
				continue;
			}

			$field_id            = 'invoice_file_name_' . $type_id;
			$fields[ $field_id ] = new Field(
				$field_id,
				$field_id,
				options: [
					self::OPTION_INVOICE_FILE_TYPE => $file_type->id,
				]
			);
		}

		return $fields;
	}
}
