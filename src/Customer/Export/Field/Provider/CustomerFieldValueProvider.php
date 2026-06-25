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
use WC_DateTime;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use function in_array;
use function method_exists;
use function number_format;
use function preg_match;
use function trim;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides WooCommerce customer export field values.
 */
final readonly class CustomerFieldValueProvider implements FieldValueProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'customers';

	/**
	 * Field IDs resolved by this provider.
	 *
	 * @var array<int, string>
	 */
	private const SUPPORTED_FIELD_IDS = [
		'customer_id',
		'username',
		'email',
		'first_name',
		'last_name',
		'display_name',
		'date_created',
		'date_modified',
		'order_count',
		'total_spent',
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
	];

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
		return CustomerExportAdapter::ADAPTER_ID === $context->export_type && in_array( $field->id, self::SUPPORTED_FIELD_IDS, true );
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

		$billing_address_parts  = $this->split_street_and_house_number( $item->get_billing_address_1() );
		$shipping_address_parts = $this->split_street_and_house_number( $item->get_shipping_address_1() );
		$values                 = [
			'customer_id'           => new FieldValue( 'customer_id', $item->get_id() ),
			'username'              => new FieldValue( 'username', $item->get_username() ),
			'email'                 => new FieldValue( 'email', $item->get_email() ),
			'first_name'            => new FieldValue( 'first_name', $item->get_first_name() ),
			'last_name'             => new FieldValue( 'last_name', $item->get_last_name() ),
			'display_name'          => new FieldValue( 'display_name', $this->get_display_name( $item ) ),
			'date_created'          => new FieldValue( 'date_created', $this->format_datetime( $item->get_date_created() ) ),
			'date_modified'         => new FieldValue( 'date_modified', $this->format_datetime( $this->get_date_modified( $item ) ) ),
			'order_count'           => new FieldValue( 'order_count', $item->get_order_count() ),
			'total_spent'           => new FieldValue( 'total_spent', $this->format_amount( (float) $item->get_total_spent() ) ),
			'billing_first_name'    => new FieldValue( 'billing_first_name', $item->get_billing_first_name() ),
			'billing_last_name'     => new FieldValue( 'billing_last_name', $item->get_billing_last_name() ),
			'billing_company'       => new FieldValue( 'billing_company', $item->get_billing_company() ),
			'billing_street'        => new FieldValue( 'billing_street', $billing_address_parts['street'] ),
			'billing_house_number'  => new FieldValue( 'billing_house_number', $billing_address_parts['house_number'] ),
			'billing_address_1'     => new FieldValue( 'billing_address_1', $item->get_billing_address_1() ),
			'billing_address_2'     => new FieldValue( 'billing_address_2', $item->get_billing_address_2() ),
			'billing_postcode'      => new FieldValue( 'billing_postcode', $item->get_billing_postcode() ),
			'billing_city'          => new FieldValue( 'billing_city', $item->get_billing_city() ),
			'billing_state'         => new FieldValue( 'billing_state', $item->get_billing_state() ),
			'billing_country'       => new FieldValue( 'billing_country', $item->get_billing_country() ),
			'billing_email'         => new FieldValue( 'billing_email', $item->get_billing_email() ),
			'billing_phone'         => new FieldValue( 'billing_phone', $item->get_billing_phone() ),
			'shipping_first_name'   => new FieldValue( 'shipping_first_name', $item->get_shipping_first_name() ),
			'shipping_last_name'    => new FieldValue( 'shipping_last_name', $item->get_shipping_last_name() ),
			'shipping_company'      => new FieldValue( 'shipping_company', $item->get_shipping_company() ),
			'shipping_street'       => new FieldValue( 'shipping_street', $shipping_address_parts['street'] ),
			'shipping_house_number' => new FieldValue( 'shipping_house_number', $shipping_address_parts['house_number'] ),
			'shipping_address_1'    => new FieldValue( 'shipping_address_1', $item->get_shipping_address_1() ),
			'shipping_address_2'    => new FieldValue( 'shipping_address_2', $item->get_shipping_address_2() ),
			'shipping_postcode'     => new FieldValue( 'shipping_postcode', $item->get_shipping_postcode() ),
			'shipping_city'         => new FieldValue( 'shipping_city', $item->get_shipping_city() ),
			'shipping_state'        => new FieldValue( 'shipping_state', $item->get_shipping_state() ),
			'shipping_country'      => new FieldValue( 'shipping_country', $item->get_shipping_country() ),
			'shipping_phone'        => new FieldValue( 'shipping_phone', $this->get_shipping_phone( $item ) ),
		];

		return $fields->filter_values( $values );
	}

	/**
	 * Gets a customer display name where supported.
	 *
	 * @param WC_Customer $customer WooCommerce customer.
	 */
	private function get_display_name( WC_Customer $customer ): string {
		if ( method_exists( $customer, 'get_display_name' ) ) {
			return $customer->get_display_name();
		}

		$user = get_userdata( $customer->get_id() );

		return false !== $user ? (string) $user->display_name : '';
	}

	/**
	 * Gets the customer modification date when supported.
	 *
	 * @param WC_Customer $customer WooCommerce customer.
	 */
	private function get_date_modified( WC_Customer $customer ): ?WC_DateTime {
		if ( method_exists( $customer, 'get_date_modified' ) ) {
			return $customer->get_date_modified();
		}

		return null;
	}

	/**
	 * Gets the shipping phone number when supported.
	 *
	 * @param WC_Customer $customer WooCommerce customer.
	 */
	private function get_shipping_phone( WC_Customer $customer ): string {
		if ( method_exists( $customer, 'get_shipping_phone' ) ) {
			return $customer->get_shipping_phone();
		}

		return '';
	}

	/**
	 * Splits an address into street and house number where possible.
	 *
	 * @param string $address Address line 1.
	 *
	 * @return array{street: string, house_number: string}
	 */
	private function split_street_and_house_number( string $address ): array {
		$address = trim( $address );

		if ( preg_match( '/^(?P<street>.*?)[\s,]+(?P<number>\d+[a-zA-Z]?([\s\/-]*\d+[a-zA-Z]?)?)$/', $address, $matches ) ) {
			return [
				'street'       => trim( (string) $matches['street'] ),
				'house_number' => trim( (string) $matches['number'] ),
			];
		}

		return [
			'street'       => $address,
			'house_number' => '',
		];
	}

	/**
	 * Formats a WooCommerce date for export output.
	 */
	private function format_datetime( ?WC_DateTime $date ): string {
		if ( null === $date ) {
			return '';
		}

		return $date->date( 'Y-m-d H:i:s' );
	}

	/**
	 * Formats an amount for export output.
	 */
	private function format_amount( float $amount ): string {
		return number_format( $amount, 2, '.', '' );
	}
}
