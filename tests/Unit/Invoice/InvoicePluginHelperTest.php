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

namespace StoreAccountant\Tests\Unit\Invoice;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Invoice\InvoicePluginHelper;
use WC_Order;

/**
 * Tests reusable invoice plugin helper behavior.
 */
final class InvoicePluginHelperTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_first_scalar_meta_returns_first_non_empty_scalar_value(): void {
		$order = new WC_Order(
			[
				'_empty'  => '',
				'_array'  => [ 'invalid' ],
				'_number' => 1234,
			]
		);

		self::assertSame( '1234', ( new InvoicePluginHelper() )->get_first_scalar_meta( $order, [ '_empty', '_array', '_number' ] ) );
	}

	public function test_get_first_scalar_array_meta_value_returns_first_matching_scalar_item(): void {
		$order = new WC_Order(
			[
				'_invoice' => [
					'empty'  => '',
					'number' => 'INV-1001',
				],
			]
		);

		self::assertSame(
			'INV-1001',
			( new InvoicePluginHelper() )->get_first_scalar_array_meta_value( $order, '_invoice', [ 'missing', 'empty', 'number' ] )
		);
	}

	public function test_format_invoice_number_value_handles_formatted_scalar_and_stringable_values(): void {
		$helper     = new InvoicePluginHelper();
		$formatted  = new class() {
			public function get_formatted(): string {
				return 'INV-1001';
			}
		};
		$stringable = new class() {
			public function __toString(): string {
				return 'INV-1002';
			}
		};

		self::assertSame( 'INV-1001', $helper->format_invoice_number_value( $formatted ) );
		self::assertSame( '123', $helper->format_invoice_number_value( 123 ) );
		self::assertSame( 'INV-1002', $helper->format_invoice_number_value( $stringable ) );
		self::assertSame( '', $helper->format_invoice_number_value( [ 'invalid' ] ) );
	}

	public function test_format_invoice_date_value_handles_date_objects_and_scalars(): void {
		$helper = new InvoicePluginHelper();
		$date   = new DateTimeImmutable( '2026-05-24 14:30:00' );

		self::assertSame( '2026-05-24', $helper->format_invoice_date_value( $date, 'Y-m-d' ) );
		self::assertSame( 'already formatted', $helper->format_invoice_date_value( 'already formatted' ) );
		self::assertSame( '', $helper->format_invoice_date_value( [ 'invalid' ] ) );
	}

	public function test_ensure_file_extension_sanitizes_file_name_and_appends_missing_extension(): void {
		Functions\expect( 'sanitize_file_name' )
			->once()
			->with( 'Invoice 1001' )
			->andReturn( 'Invoice-1001' );

		Functions\expect( 'sanitize_key' )
			->once()
			->with( 'pdf' )
			->andReturn( 'pdf' );

		self::assertSame( 'Invoice-1001.pdf', ( new InvoicePluginHelper() )->ensure_file_extension( 'Invoice 1001', '.pdf' ) );
	}

	public function test_ensure_file_extension_keeps_existing_extension_case_insensitively(): void {
		Functions\expect( 'sanitize_file_name' )
			->once()
			->with( 'invoice.PDF' )
			->andReturn( 'invoice.PDF' );

		Functions\expect( 'sanitize_key' )
			->once()
			->with( 'pdf' )
			->andReturn( 'pdf' );

		self::assertSame( 'invoice.PDF', ( new InvoicePluginHelper() )->ensure_file_extension( 'invoice.PDF', 'pdf' ) );
	}
}
