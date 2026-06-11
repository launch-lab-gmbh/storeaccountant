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

namespace StoreAccountant\Tests\Unit\Export;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests the export adapter registry.
 */
final class ExportAdapterRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_export_adapter_hook_and_filters_by_adapter_type(): void {
		$adapter = $this->createMock( ExportAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( 'orders' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_adapter', [] )
			->andReturn(
				[
					new TestRegistryItem( 'not-an-adapter' ),
					$adapter,
				]
			);

		self::assertSame( [ 'orders' => $adapter ], ( new ExportAdapterRegistry() )->get_all() );
	}
}
