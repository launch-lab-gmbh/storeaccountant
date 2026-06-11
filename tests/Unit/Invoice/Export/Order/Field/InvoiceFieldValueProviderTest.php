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
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Invoice\Contract\InvoicePluginInterface;
use StoreAccountant\Invoice\Export\Order\Field\InvoiceFieldValueProvider;
use StoreAccountant\Invoice\Export\Order\InvoiceExportAttachmentSettings;
use StoreAccountant\Invoice\InvoiceFileType;
use StoreAccountant\Invoice\InvoicePluginDetector;
use StoreAccountant\Invoice\InvoicePluginRegistry;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use WC_Order;
use function array_map;

/**
 * Tests invoice value resolution for order exports.
 */
final class InvoiceFieldValueProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_invoice_value_provider(): void {
		$provider = $this->provider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_value_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( InvoiceFieldValueProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_invoice_fields_for_order_exports_when_plugin_is_enabled(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'pdf' );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $this->plugin() ] );

		$provider = $this->provider();

		self::assertTrue( $provider->supports( new Field( 'invoice_number', 'invoice_number' ), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new Field( 'order_total', 'order_total' ), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new Field( 'invoice_number', 'invoice_number' ), new ExportContext( 'customers' ) ) );
	}

	public function test_get_values_resolves_requested_invoice_fields_and_selected_file_names(): void {
		$plugin = $this->plugin();
		$order  = new WC_Order( [ 'id' => 1001 ] );

		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'pdf' );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $plugin ] );
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 77, ExportConfigurationPostType::META_ADDITIONAL_SETTINGS, true )
			->andReturn(
				json_encode(
					[
						InvoiceExportAttachmentSettings::PROVIDER_ID => [
							InvoiceExportAttachmentSettings::OPTION_FILE_TYPES => [ 'pdf', 'ubl' ],
						],
					]
				)
			);

		$values = $this->provider()->get_values(
			$order,
			new FieldCollection(
				[
					new Field( 'invoice_number', 'invoice_number' ),
					new Field( 'invoice_date', 'invoice_date' ),
					new Field( 'invoice_file_name', 'invoice_file_name' ),
				]
			),
			new ExportContext( OrderExportAdapter::ADAPTER_ID, 77 )
		);

		self::assertSame(
			[
				'invoice_number'    => 'INV-1001',
				'invoice_date'      => '2026-05-04',
				'invoice_file_name' => 'invoice-1001.pdf',
			],
			array_map( static fn ( $value ): mixed => $value->value, $values )
		);
	}

	public function test_get_values_returns_empty_array_without_order_context_or_enabled_plugin(): void {
		Functions\expect( 'get_option' )
			->twice()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( '' );

		$provider = $this->provider();

		self::assertSame( [], $provider->get_values( new WC_Order(), new FieldCollection(), new ExportContext( 'customers' ) ) );
		self::assertSame( [], $provider->get_values( 'not-an-order', new FieldCollection(), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
	}

	private function provider(): InvoiceFieldValueProvider {
		return new InvoiceFieldValueProvider(
			new InvoicePluginDetector( new InvoicePluginRegistry() ),
			new InvoiceExportAttachmentSettings()
		);
	}

	private function plugin(): InvoicePluginInterface {
		$plugin = $this->createMock( InvoicePluginInterface::class );
		$plugin->method( 'get_id' )->willReturn( 'pdf' );
		$plugin->method( 'is_active' )->willReturn( true );
		$plugin->method( 'get_invoice_number' )->willReturn( 'INV-1001' );
		$plugin->method( 'get_invoice_date' )->willReturn( '2026-05-04' );
		$plugin->method( 'get_invoice_file_types' )->willReturn(
			[
				new InvoiceFileType( 'pdf', 'PDF' ),
				new InvoiceFileType( 'ubl', 'UBL' ),
			]
		);
		$plugin->method( 'get_invoice_file_name' )->willReturnCallback(
			static fn ( WC_Order $order, string $type ): string => 'pdf' === $type ? 'invoice-1001.pdf' : ''
		);

		return $plugin;
	}
}
