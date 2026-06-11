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

namespace StoreAccountant\Tests\Unit\Export\Filter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Filter\Contract\ExportFilterFieldProviderInterface;
use StoreAccountant\Export\Filter\ExportFilterFieldProviderRegistry;

/**
 * Tests the export filter field provider registry.
 */
final class ExportFilterFieldProviderRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_providers_returns_only_providers_supporting_export_type(): void {
		$supported = $this->createMock( ExportFilterFieldProviderInterface::class );
		$supported->method( 'get_id' )->willReturn( 'order_date' );
		$supported->expects( self::once() )->method( 'supports' )->with( 'orders' )->willReturn( true );

		$unsupported = $this->createMock( ExportFilterFieldProviderInterface::class );
		$unsupported->method( 'get_id' )->willReturn( 'customer_country' );
		$unsupported->expects( self::once() )->method( 'supports' )->with( 'orders' )->willReturn( false );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_filter_field_provider', [] )
			->andReturn( [ $unsupported, $supported ] );

		self::assertSame(
			[ 'order_date' => $supported ],
			( new ExportFilterFieldProviderRegistry() )->get_providers( 'orders' )
		);
	}
}
