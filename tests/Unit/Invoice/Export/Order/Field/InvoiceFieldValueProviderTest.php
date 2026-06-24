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
use RuntimeException;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\Event\ExportEvents;
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
			->twice()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'pdf' );
		Functions\expect( 'apply_filters' )
			->twice()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $this->plugin() ] );

		$provider = $this->provider();

		self::assertTrue( $provider->supports( new Field( 'invoice_number', 'invoice_number' ), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertTrue( $provider->supports( new Field( 'invoice_file_name_pdf', 'invoice_file_name_pdf' ), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new Field( 'invoice_file_name', 'invoice_file_name' ), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
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
							InvoiceExportAttachmentSettings::OPTION_FILE_TYPES => [ 'pdf', 'xml' ],
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
					new Field( 'invoice_file_name_pdf', 'invoice_file_name_pdf' ),
					new Field( 'invoice_file_name_xml', 'invoice_file_name_xml' ),
				]
			),
			new ExportContext( OrderExportAdapter::ADAPTER_ID, 77 )
		);

		self::assertSame(
			[
				'invoice_number'        => 'INV-1001',
				'invoice_date'          => '2026-05-04',
				'invoice_file_name_pdf' => 'invoice-1001.pdf',
				'invoice_file_name_xml' => 'invoice-1001.xml',
			],
			array_map( static fn ( $value ): mixed => $value->value, $values )
		);
	}

	public function test_get_values_logs_warning_and_returns_empty_values_when_invoice_plugin_throws(): void {
		$number_exception = new RuntimeException( 'Invoice number failed' );
		$file_exception   = new RuntimeException( 'Invoice PDF name failed' );
		$events           = [];
		$plugin           = $this->createMock( InvoicePluginInterface::class );
		$order            = new WC_Order( [ 'id' => 1001 ] );

		$plugin->method( 'get_id' )->willReturn( 'pdf' );
		$plugin->method( 'is_active' )->willReturn( true );
		$plugin->method( 'get_invoice_number' )->willThrowException( $number_exception );
		$plugin->method( 'get_invoice_date' )->willReturn( '2026-05-04' );
		$plugin->method( 'get_invoice_file_types' )->willReturn(
			[
				new InvoiceFileType( 'pdf', 'PDF' ),
				new InvoiceFileType( 'xml', 'XML' ),
			]
		);
		$plugin->method( 'get_invoice_file_name' )->willReturnCallback(
			static function ( WC_Order $order, string $type ) use ( $file_exception ): string {
				if ( 'pdf' === $type ) {
					throw $file_exception;
				}

				return 'invoice-1001.xml';
			}
		);
		$plugin->method( 'has_invoice' )->willReturn( true );

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
							InvoiceExportAttachmentSettings::OPTION_FILE_TYPES => [ 'pdf', 'xml' ],
						],
					]
				)
			);
		Functions\when( 'do_action' )->alias(
			static function ( mixed ...$args ) use ( &$events ): void {
				$events[] = $args;
			}
		);

		$values = $this->provider()->get_values(
			$order,
			new FieldCollection(
				[
					new Field( 'invoice_number', 'invoice_number' ),
					new Field( 'invoice_date', 'invoice_date' ),
					new Field( 'invoice_file_name_pdf', 'invoice_file_name_pdf' ),
					new Field( 'invoice_file_name_xml', 'invoice_file_name_xml' ),
				]
			),
			new ExportContext( OrderExportAdapter::ADAPTER_ID, 77, [], [ 'export_id' => 123 ] )
		);

		self::assertSame(
			[
				'invoice_number'        => '',
				'invoice_date'          => '2026-05-04',
				'invoice_file_name_pdf' => '',
				'invoice_file_name_xml' => 'invoice-1001.xml',
			],
			array_map( static fn ( $value ): mixed => $value->value, $values )
		);

		self::assertCount( 2, $events );
		self::assertSame( ExportEvents::LOG_ENTRY->value, $events[0][0] );
		self::assertSame( 123, $events[0][1] );
		self::assertSame( 'warning', $events[0][2] );
		self::assertSame( 'Invoice plugin error while resolving invoice export data.', $events[0][3] );
		self::assertSame( 'invoice_number', $events[0][4]['field_id'] );
		self::assertSame( 'Invoice number failed', $events[0][4]['exception_message'] );
		self::assertSame( $number_exception, $events[0][5] );
		self::assertSame( 'invoice_file_name_pdf', $events[1][4]['field_id'] );
		self::assertSame( 'pdf', $events[1][4]['invoice_file_type'] );
		self::assertSame( 'Invoice PDF name failed', $events[1][4]['exception_message'] );
		self::assertSame( $file_exception, $events[1][5] );
	}

	public function test_get_values_returns_empty_invoice_file_names_without_existing_invoice(): void {
		$plugin = $this->createMock( InvoicePluginInterface::class );
		$order  = new WC_Order( [ 'id' => 1001 ] );

		$plugin->method( 'get_id' )->willReturn( 'pdf' );
		$plugin->method( 'is_active' )->willReturn( true );
		$plugin->method( 'has_invoice' )->willReturn( false );
		$plugin->expects( self::never() )->method( 'get_invoice_file_name' );

		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'pdf' );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $plugin ] );

		$values = $this->provider()->get_values(
			$order,
			new FieldCollection(
				[
					new Field( 'invoice_file_name_pdf', 'invoice_file_name_pdf' ),
					new Field( 'invoice_file_name_xml', 'invoice_file_name_xml' ),
				]
			),
			new ExportContext( OrderExportAdapter::ADAPTER_ID, 77, [], [ 'export_id' => 123 ] )
		);

		self::assertSame( '', $values['invoice_file_name_pdf']->value );
		self::assertSame( '', $values['invoice_file_name_xml']->value );
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
		$plugin->method( 'has_invoice' )->willReturn( true );
		$plugin->method( 'get_invoice_number' )->willReturn( 'INV-1001' );
		$plugin->method( 'get_invoice_date' )->willReturn( '2026-05-04' );
		$plugin->method( 'get_invoice_file_types' )->willReturn(
			[
				new InvoiceFileType( 'pdf', 'PDF' ),
				new InvoiceFileType( 'xml', 'XML' ),
			]
		);
		$plugin->method( 'get_invoice_file_name' )->willReturnCallback(
			static fn ( WC_Order $order, string $type ): string => 'pdf' === $type ? 'invoice-1001.pdf' : 'invoice-1001.xml'
		);

		return $plugin;
	}
}
