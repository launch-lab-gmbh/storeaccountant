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

namespace StoreAccountant\Tests\Unit\Order\Tax;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Order\Tax\OrderTaxRateResolver;
use WC_Order;
use WC_Order_Item_Tax;

/**
 * Tests order tax rate key resolution.
 */
final class OrderTaxRateResolverTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_tax_rate_key_returns_configured_key_for_matching_rate_id(): void {
		$tax = new WC_Order_Item_Tax( 7, 19.0 );

		self::assertSame( '19_vat_de', ( new OrderTaxRateResolver() )->get_tax_rate_key( $tax, [ '19_vat_de' => 7 ] ) );
	}

	public function test_get_tax_rate_key_builds_fallback_key_from_percent(): void {
		$tax = new WC_Order_Item_Tax( 0, 7.7 );

		self::assertSame( '7_7_tax', ( new OrderTaxRateResolver() )->get_tax_rate_key( $tax, [] ) );
	}

	public function test_get_tax_rate_key_returns_empty_string_without_rate_information(): void {
		$tax = new WC_Order_Item_Tax( 0, 0.0 );

		self::assertSame( '', ( new OrderTaxRateResolver() )->get_tax_rate_key( $tax, [] ) );
	}

	public function test_get_tax_rates_adds_rates_discovered_from_orders_and_sorts_keys(): void {
		$order = new WC_Order(
			[
				new WC_Order_Item_Tax( 0, 19.0 ),
				new WC_Order_Item_Tax( 0, 7.0 ),
				'not-a-tax-item',
			]
		);

		self::assertSame(
			[
				'7_tax'  => 0,
				'19_tax' => 0,
			],
			( new OrderTaxRateResolver() )->get_tax_rates( [ $order ] )
		);
	}
}
