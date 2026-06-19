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

namespace StoreAccountant\Tests\Unit\Product\Export\Adapter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Filter\ExportFilterRegistry;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use StoreAccountant\Product\Export\Query\ProductQuery;
use WC_Product;

/**
 * Tests the product export adapter.
 */
final class ProductExportAdapterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_product_export_adapter(): void {
		$adapter = $this->adapter();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_adapter', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$adapter->register();

		self::assertSame( ProductExportAdapter::ADAPTER_ID, $adapter->get_id() );
	}

	public function test_get_context_keeps_array_items_and_configuration_id(): void {
		$items   = [ new WC_Product( [ 'id' => 42 ] ) ];
		$context = $this->adapter()->get_context(
			new ExportPayload(
				100,
				ProductExportAdapter::ADAPTER_ID,
				options: [
					'configuration_id' => 77,
				]
			),
			$items
		);

		self::assertSame( ProductExportAdapter::ADAPTER_ID, $context->export_type );
		self::assertSame( 77, $context->configuration_id );
		self::assertSame( $items, $context->items );
	}

	public function test_additional_fields_values_and_record_id_are_stable(): void {
		$adapter = $this->adapter();
		$product = new WC_Product( [ 'id' => 42 ] );

		self::assertInstanceOf( FieldCollection::class, $adapter->get_additional_fields( new ExportPayload( 1, ProductExportAdapter::ADAPTER_ID ), $adapter->get_context( new ExportPayload( 1, ProductExportAdapter::ADAPTER_ID ), [] ) ) );
		self::assertSame( [], $adapter->get_additional_values( $product, new ExportPayload( 1, ProductExportAdapter::ADAPTER_ID ), $adapter->get_context( new ExportPayload( 1, ProductExportAdapter::ADAPTER_ID ), [] ) ) );
		self::assertSame( '42', $adapter->get_record_id( $product ) );
		self::assertSame( '', $adapter->get_record_id( 'not-a-product' ) );
	}

	private function adapter(): ProductExportAdapter {
		return new ProductExportAdapter( new ProductQuery( new ExportFilterRegistry() ) );
	}
}
