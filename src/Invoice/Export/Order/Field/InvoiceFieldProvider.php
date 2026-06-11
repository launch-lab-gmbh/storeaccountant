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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides invoice fields for WooCommerce order exports.
 */
final readonly class InvoiceFieldProvider implements FieldProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'order_invoices';

	/**
	 * Initializes the provider.
	 *
	 * @param InvoicePluginDetector $detector Invoice plugin detector.
	 */
	public function __construct(
		private InvoicePluginDetector $detector
	) {}

	/**
	 * {@inheritDoc}
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
	 */
	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type && $this->detector->is_enabled();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_fields( ExportContext $context ): array {
		return [
			'invoice_number'    => new Field( 'invoice_number', 'invoice_number' ),
			'invoice_date'      => new Field( 'invoice_date', 'invoice_date', new DateTimeFieldType() ),
			'invoice_file_name' => new Field( 'invoice_file_name', 'invoice_file_name' ),
		];
	}
}
