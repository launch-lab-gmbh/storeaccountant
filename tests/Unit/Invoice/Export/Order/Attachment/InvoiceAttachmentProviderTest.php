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

namespace StoreAccountant\Tests\Unit\Invoice\Export\Order\Attachment;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Invoice\Contract\InvoicePluginInterface;
use StoreAccountant\Invoice\Export\Order\Attachment\InvoiceAttachmentProvider;
use StoreAccountant\Invoice\Export\Order\InvoiceExportAttachmentSettings;
use StoreAccountant\Invoice\InvoiceFileType;
use StoreAccountant\Invoice\InvoicePluginDetector;
use StoreAccountant\Invoice\InvoicePluginRegistry;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Storage\StorageFile;
use WC_Order;

/**
 * Tests invoice attachment export provider.
 */
final class InvoiceAttachmentProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'sanitize_file_name' )->alias(
			static fn ( string $value ): string => trim( preg_replace( '/[^A-Za-z0-9._-]+/', '-', $value ) ?? '', '-' )
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', $value ) ?? '' )
		);
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/\\' ) . '/' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_provider_to_attachment_registry_filter(): void {
		$provider = $this->provider( null );

		Monkey\Filters\expectAdded( 'storeaccountant_export_attachment_provider' )->once();

		$provider->register();

		self::assertSame( InvoiceAttachmentProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_only_order_exports_with_enabled_invoice_plugin(): void {
		$plugin = $this->plugin( [ 'pdf' ], [] );

		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'pdf_plugin' );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $plugin ] );

		$provider = $this->provider( null );

		self::assertTrue( $provider->supports( new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new ExportContext( 'customers' ) ) );
	}

	public function test_get_directory_returns_stable_sanitized_invoice_directory(): void {
		self::assertSame( 'Invoices', $this->provider( null )->get_directory( new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
	}

	public function test_get_attachments_returns_selected_existing_invoice_files_only(): void {
		$pdf_stream = fopen( 'php://temp', 'rb+' );
		$xml_stream = fopen( 'php://temp', 'rb+' );

		self::assertIsResource( $pdf_stream );
		self::assertIsResource( $xml_stream );

		$plugin = $this->plugin(
			[ 'pdf', 'xml', 'missing' ],
			[
				'pdf' => new StorageFile( $pdf_stream, 'Invoice 1001.pdf', 'application/pdf' ),
				'xml' => new StorageFile( $xml_stream, 'Invoice 1001.xml', 'application/xml' ),
			]
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'pdf_plugin' );
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
							InvoiceExportAttachmentSettings::OPTION_FILE_TYPES => [ 'pdf', 'xml', 'missing', 'unknown' ],
						],
					]
				)
			);

		$attachments = $this->provider( null )->get_attachments(
			new WC_Order( 1001 ),
			new ExportPayload( 123, OrderExportAdapter::ADAPTER_ID ),
			new ExportContext( OrderExportAdapter::ADAPTER_ID, 77 )
		);

		$attachments = is_array( $attachments ) ? $attachments : iterator_to_array( $attachments );

		self::assertCount( 2, $attachments );
		self::assertSame( 'Invoice-1001.pdf', $attachments[0]->file_name );
		self::assertSame( 'Invoices/pdf/Invoice-1001.pdf', $attachments[0]->internal_path );
		self::assertSame( 'Invoice-1001.xml', $attachments[1]->file_name );
		self::assertSame( 'Invoices/xml/Invoice-1001.xml', $attachments[1]->internal_path );
	}

	public function test_get_attachments_ignores_non_order_or_missing_plugin(): void {
		Functions\expect( 'get_option' )->once()->with( 'storeaccountant_enabled_invoice_plugin', '' )->andReturn( '' );

		self::assertSame(
			[],
			$this->provider( null )->get_attachments(
				new \stdClass(),
				new ExportPayload( 123, OrderExportAdapter::ADAPTER_ID ),
				new ExportContext( OrderExportAdapter::ADAPTER_ID, 77 )
			)
		);
	}

	/**
	 * @param array<int, string>              $file_types Invoice file type IDs.
	 * @param array<string, StorageFile|null> $files      Files indexed by type.
	 */
	private function plugin( array $file_types, array $files ): InvoicePluginInterface {
		$plugin = $this->createMock( InvoicePluginInterface::class );
		$plugin->method( 'get_id' )->willReturn( 'pdf_plugin' );
		$plugin->method( 'is_active' )->willReturn( true );
		$plugin->method( 'get_invoice_file_types' )
			->willReturn( array_map( static fn ( string $id ): InvoiceFileType => new InvoiceFileType( $id, strtoupper( $id ) ), $file_types ) );
		$plugin->method( 'get_invoice_file' )
			->willReturnCallback( static fn ( WC_Order $order, string $type ): ?StorageFile => $files[ $type ] ?? null );

		return $plugin;
	}

	private function provider( ?InvoicePluginInterface $plugin ): InvoiceAttachmentProvider {
		return new InvoiceAttachmentProvider(
			new InvoicePluginDetector( new InvoicePluginRegistry() ),
			new InvoiceExportAttachmentSettings()
		);
	}
}
