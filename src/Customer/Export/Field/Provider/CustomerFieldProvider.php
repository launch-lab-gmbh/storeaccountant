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
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Export\Field\Type\NumberFieldType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides WooCommerce customer export fields.
 */
final readonly class CustomerFieldProvider implements FieldProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'customers';

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
	public function supports( ExportContext $context ): bool {
		return CustomerExportAdapter::ADAPTER_ID === $context->export_type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_fields( ExportContext $context ): array {
		$fields = [
			'customer_id'   => new Field( 'customer_id', 'customer_id', new NumberFieldType( NumberFieldType::FORMAT_INTEGER ) ),
			'username'      => new Field( 'username', 'username' ),
			'email'         => new Field( 'email', 'email' ),
			'first_name'    => new Field( 'first_name', 'first_name' ),
			'last_name'     => new Field( 'last_name', 'last_name' ),
			'display_name'  => new Field( 'display_name', 'display_name' ),
			'date_created'  => new Field( 'date_created', 'date_created', new DateTimeFieldType() ),
			'date_modified' => new Field( 'date_modified', 'date_modified', new DateTimeFieldType() ),
			'order_count'   => new Field( 'order_count', 'order_count', new NumberFieldType( NumberFieldType::FORMAT_INTEGER ) ),
			'total_spent'   => new Field( 'total_spent', 'total_spent', new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ) ),
		];

		foreach ( [
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_street',
			'billing_house_number',
			'billing_address_1',
			'billing_address_2',
			'billing_postcode',
			'billing_city',
			'billing_state',
			'billing_country',
			'billing_email',
			'billing_phone',
		] as $id ) {
			$fields[ $id ] = new Field( $id, $id );
		}

		foreach ( [
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_street',
			'shipping_house_number',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_postcode',
			'shipping_city',
			'shipping_state',
			'shipping_country',
			'shipping_phone',
		] as $id ) {
			$fields[ $id ] = new Field( $id, $id );
		}

		return $fields;
	}
}
