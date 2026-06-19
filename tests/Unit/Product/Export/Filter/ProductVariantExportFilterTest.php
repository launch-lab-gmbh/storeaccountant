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

namespace StoreAccountant\Tests\Unit\Product\Export\Filter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use StoreAccountant\Product\Export\Filter\ProductVariantExportFilter;
use StoreAccountant\Product\Export\Query\ProductQueryCriteria;
use WP_Error;

/**
 * Tests the product variant export filter.
 */
final class ProductVariantExportFilterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $value ) ?? '' )
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_export_filter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_filter', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		( new ProductVariantExportFilter() )->register();

		self::assertTrue( true );
	}

	public function test_get_id_supports_and_modes_are_stable(): void {
		$filter = new ProductVariantExportFilter();

		self::assertSame( ProductVariantExportFilter::FILTER_ID, $filter->get_id() );
		self::assertTrue( $filter->supports( ProductExportAdapter::ADAPTER_ID ) );
		self::assertFalse( $filter->supports( 'orders' ) );
		self::assertSame(
			[
				ProductVariantExportFilter::MODE_PARENT_PRODUCTS   => 'Parent products only',
				ProductVariantExportFilter::MODE_SEPARATE_VARIANTS => 'Export variants separately',
			],
			ProductVariantExportFilter::get_modes()
		);
	}

	public function test_apply_enables_separate_variant_rows_only_for_separate_mode(): void {
		$criteria = new ProductQueryCriteria();

		$result = ( new ProductVariantExportFilter() )->apply(
			$criteria,
			new ExportFilterSelection(
				ProductVariantExportFilter::FILTER_ID,
				[
					'mode' => ProductVariantExportFilter::MODE_SEPARATE_VARIANTS,
				]
			),
			new ExportPayload( 1, ProductExportAdapter::ADAPTER_ID )
		);

		self::assertTrue( $result );
		self::assertTrue( $criteria->export_variations );

		$result = ( new ProductVariantExportFilter() )->apply(
			$criteria,
			new ExportFilterSelection(
				ProductVariantExportFilter::FILTER_ID,
				[
					'mode' => 'invalid',
				]
			),
			new ExportPayload( 1, ProductExportAdapter::ADAPTER_ID )
		);

		self::assertTrue( $result );
		self::assertFalse( $criteria->export_variations );
	}

	public function test_apply_returns_error_for_wrong_query_type(): void {
		$result = ( new ProductVariantExportFilter() )->apply( new \stdClass(), new ExportFilterSelection( ProductVariantExportFilter::FILTER_ID ), new ExportPayload( 1, ProductExportAdapter::ADAPTER_ID ) );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'storeaccountant_invalid_product_query', $result->get_error_code() );
	}
}
