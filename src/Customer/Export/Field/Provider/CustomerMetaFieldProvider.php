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

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Export\Contract\FieldProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Meta\MetaFieldCollector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides export fields for custom WooCommerce customer meta.
 */
final readonly class CustomerMetaFieldProvider implements FieldProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID     = 'customer_meta';
	public const FIELD_ID_PREFIX = 'customer_meta_';
	public const OPTION_META_KEY = MetaField::OPTION_META_KEY;

	/**
	 * Customer meta keys already represented by dedicated export fields.
	 *
	 * @var array<int, string>
	 */
	private const RESERVED_META_KEYS = [
		'billing_first_name',
		'billing_last_name',
		'billing_company',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_state',
		'billing_postcode',
		'billing_country',
		'billing_email',
		'billing_phone',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_company',
		'shipping_address_1',
		'shipping_address_2',
		'shipping_city',
		'shipping_state',
		'shipping_postcode',
		'shipping_country',
		'shipping_phone',
		'first_name',
		'last_name',
		'description',
		'nickname',
		'paying_customer',
		'last_update',
		'_order_count',
		'_money_spent',
		'wp_capabilities',
		'wp_user_level',
	];

	/**
	 * Initializes the customer meta field provider.
	 *
	 * @param MetaFieldCollector $collector Metadata field collector.
	 */
	public function __construct(
		private MetaFieldCollector $collector
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_field_provider',
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
	public function supports( ExportContext $context ): bool {
		return CustomerExportAdapter::ADAPTER_ID === $context->export_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_fields( ExportContext $context ): array {
		return $this->collector->get_fields( $context, self::FIELD_ID_PREFIX, self::RESERVED_META_KEYS );
	}

	/**
	 * Gets the stable export field ID for a customer meta key.
	 *
	 * @param string $meta_key WooCommerce customer meta key.
	 */
	public static function get_field_id( string $meta_key ): string {
		return MetaField::get_field_id( self::FIELD_ID_PREFIX, $meta_key );
	}
}
