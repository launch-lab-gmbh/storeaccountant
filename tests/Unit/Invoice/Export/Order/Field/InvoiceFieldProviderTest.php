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

namespace StoreAccountant\Tests\Unit\Invoice\Export\Order\Field;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Invoice\Export\Order\Field\InvoiceFieldProvider;
use StoreAccountant\Invoice\InvoiceFileType;
use StoreAccountant\Invoice\InvoicePluginDetector;
use StoreAccountant\Invoice\InvoicePluginRegistry;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use function array_keys;
use function preg_replace;
use function strtolower;

/**
 * Tests invoice field definitions for order exports.
 */
final class InvoiceFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $key ): string => strtolower( preg_replace( '/[^a-z0-9_-]/', '', $key ) ?? '' )
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_invoice_field_provider(): void {
		$provider = new InvoiceFieldProvider( new InvoicePluginDetector( new InvoicePluginRegistry() ) );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( InvoiceFieldProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_order_exports_when_invoice_plugin_is_enabled(): void {
		$provider = new InvoiceFieldProvider( new InvoicePluginDetector( new InvoicePluginRegistry() ) );

		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'pdf' );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $this->active_plugin( 'pdf' ) ] );

		self::assertTrue( $provider->supports( new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new ExportContext( 'customers' ) ) );
	}

	public function test_get_fields_returns_invoice_number_date_and_typed_file_names(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'pdf' );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $this->active_plugin( 'pdf' ) ] );

		$fields = ( new InvoiceFieldProvider( new InvoicePluginDetector( new InvoicePluginRegistry() ) ) )->get_fields( new ExportContext( OrderExportAdapter::ADAPTER_ID ) );

		self::assertSame( [ 'invoice_number', 'invoice_date', 'invoice_file_name_pdf', 'invoice_file_name_xml' ], array_keys( $fields ) );
		self::assertInstanceOf( DateTimeFieldType::class, $fields['invoice_date']->type );
	}

	private function active_plugin( string $id ) {
		$plugin = $this->createMock( \StoreAccountant\Invoice\Contract\InvoicePluginInterface::class );
		$plugin->method( 'get_id' )->willReturn( $id );
		$plugin->method( 'is_active' )->willReturn( true );
		$plugin->method( 'get_invoice_file_types' )->willReturn(
			[
				new InvoiceFileType( 'pdf', 'PDF' ),
				new InvoiceFileType( 'xml', 'XML' ),
			]
		);

		return $plugin;
	}
}
