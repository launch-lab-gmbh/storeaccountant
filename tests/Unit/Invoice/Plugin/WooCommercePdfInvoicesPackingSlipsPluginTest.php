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

namespace StoreAccountant\Tests\Unit\Invoice\Plugin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Invoice\InvoicePluginHelper;
use StoreAccountant\Invoice\Plugin\WooCommercePdfInvoicesPackingSlipsPlugin;
use StoreAccountant\Storage\StorageFile;
use WC_Order;

/**
 * Tests WooCommerce PDF Invoices & Packing Slips integration.
 */
final class WooCommercePdfInvoicesPackingSlipsPluginTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'sanitize_file_name' )->alias( static fn ( string $file_name ): string => preg_replace( '/[^A-Za-z0-9._-]/', '-', $file_name ) ?? '' );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', $key ) ?? '' ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_invoice_plugin_filter_and_get_id_is_stable(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_invoice_plugin', \Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$plugin = $this->plugin();

		$plugin->register();

		self::assertSame( WooCommercePdfInvoicesPackingSlipsPlugin::PLUGIN_ID, $plugin->get_id() );
	}

	public function test_is_active_detects_plugin_api_function(): void {
		self::assertFalse( $this->plugin()->is_active() );

		Functions\when( 'wcpdf_get_document' )->alias( static fn (): object => (object) [] );

		self::assertTrue( $this->plugin()->is_active() );
	}

	public function test_invoice_values_and_pdf_file_are_read_from_plugin_api(): void {
		$invoice = new class() {
			public function get_number(): object {
				return new class() {
					public function get_formatted(): string {
						return 'INV-1001';
					}
				};
			}

			public function get_date(): \DateTimeImmutable {
				return new \DateTimeImmutable( '2026-03-04 12:30:00' );
			}

			public function get_filename(): string {
				return 'invoice-1001';
			}

			public function get_pdf(): string {
				return '%PDF-content';
			}
		};

		Functions\when( 'wcpdf_get_document' )->alias( static fn (): object => $invoice );

		$plugin = $this->plugin();
		$order  = new WC_Order( [ 'id' => 1001 ] );

		self::assertTrue( $plugin->has_invoice( $order ) );
		self::assertSame( 'INV-1001', $plugin->get_invoice_number( $order ) );
		self::assertSame( '2026-03-04 12:30:00', $plugin->get_invoice_date( $order ) );
		self::assertSame( 'invoice-1001.pdf', $plugin->get_invoice_file_name( $order, 'pdf' ) );

		$file = $plugin->get_invoice_file( $order, 'pdf' );

		self::assertInstanceOf( StorageFile::class, $file );
		self::assertSame( 'invoice-1001.pdf', $file->file_name );
		self::assertSame( 'application/pdf', $file->mime_type );
		self::assertSame( '%PDF-content', stream_get_contents( $file->stream ) );
		fclose( $file->stream );
	}

	public function test_missing_plugin_api_falls_back_to_order_meta_and_empty_files(): void {
		$plugin = $this->plugin();
		$order  = new WC_Order(
			[
				'id'                         => 1002,
				'_wcpdf_invoice_number_data' => [
					'formatted_number' => 'META-1002',
					'date'             => '2026-01-02',
				],
			]
		);

		self::assertTrue( $plugin->has_invoice( $order ) );
		self::assertSame( 'META-1002', $plugin->get_invoice_number( $order ) );
		self::assertSame( '2026-01-02', $plugin->get_invoice_date( $order ) );
		self::assertSame( 'invoice-META-1002.pdf', $plugin->get_invoice_file_name( $order, 'pdf' ) );
		self::assertNull( $plugin->get_invoice_file( $order, 'pdf' ) );
		self::assertSame( '', $plugin->get_invoice_file_name( $order, 'unknown' ) );
		self::assertNull( $plugin->get_invoice_file( $order, 'unknown' ) );
	}

	public function test_order_without_invoice_number_does_not_generate_invoice_files(): void {
		$invoice = new class() {
			public function get_number(): string {
				return '';
			}

			public function get_filename(): string {
				return 'invoice-1003';
			}

			public function get_pdf(): string {
				return '%PDF-content';
			}
		};

		Functions\when( 'wcpdf_get_document' )->alias( static fn (): object => $invoice );

		$plugin = $this->plugin();
		$order  = new WC_Order( [ 'id' => 1003 ] );

		self::assertFalse( $plugin->has_invoice( $order ) );
		self::assertSame( '', $plugin->get_invoice_number( $order ) );
		self::assertSame( '', $plugin->get_invoice_file_name( $order, 'pdf' ) );
		self::assertNull( $plugin->get_invoice_file( $order, 'pdf' ) );
	}

	public function test_file_types_include_pdf_and_xml_only_when_xml_api_is_available(): void {
		Functions\when( 'wcpdf_get_document' )->alias( static fn (): object => (object) [] );

		$file_types = $this->plugin()->get_invoice_file_types();

		self::assertCount( 1, $file_types );
		self::assertSame( 'pdf', $file_types[0]->id );
	}

	private function plugin(): WooCommercePdfInvoicesPackingSlipsPlugin {
		return new WooCommercePdfInvoicesPackingSlipsPlugin( new InvoicePluginHelper() );
	}
}
