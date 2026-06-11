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
use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests the export renderer registry.
 */
final class ExportRendererRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_export_renderer_hook_and_filters_by_renderer_type(): void {
		$renderer = $this->createMock( ExportRendererInterface::class );
		$renderer->method( 'get_id' )->willReturn( 'csv' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_renderer', [] )
			->andReturn(
				[
					new TestRegistryItem( 'not-a-renderer' ),
					$renderer,
				]
			);

		self::assertSame( [ 'csv' => $renderer ], ( new ExportRendererRegistry() )->get_all() );
	}
}
