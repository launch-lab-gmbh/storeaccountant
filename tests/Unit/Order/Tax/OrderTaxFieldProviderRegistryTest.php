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
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Order\Tax\OrderTaxFieldProviderRegistry;
use StoreAccountant\Tax\Contract\OrderTaxFieldProviderInterface;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests order tax field provider lookup.
 */
final class OrderTaxFieldProviderRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_tax_field_provider_hook_and_filters_by_type(): void {
		$provider = $this->provider( 'simple' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_order_tax_field_provider', [] )
			->andReturn( [ new TestRegistryItem( 'wrong-type' ), $provider ] );

		self::assertSame( [ 'simple' => $provider ], ( new OrderTaxFieldProviderRegistry() )->get_all() );
	}

	public function test_get_provider_returns_requested_provider_or_extended_fallback(): void {
		$extended = $this->provider( ExtendedOrderTaxFieldProvider::PROVIDER_ID );

		Functions\expect( 'apply_filters' )
			->times( 3 )
			->with( 'storeaccountant_export_order_tax_field_provider', [] )
			->andReturn( [ $extended ] );

		$registry = new OrderTaxFieldProviderRegistry();

		self::assertSame( $extended, $registry->get_provider( ExtendedOrderTaxFieldProvider::PROVIDER_ID ) );
		self::assertSame( $extended, $registry->get_provider( 'missing' ) );
	}

	private function provider( string $id ): OrderTaxFieldProviderInterface {
		$provider = $this->createMock( OrderTaxFieldProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( $id );

		return $provider;
	}
}
