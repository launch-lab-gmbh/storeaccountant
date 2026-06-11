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
use PHPUnit\Framework\TestCase;
use StoreAccountant\Invoice\Contract\InvoicePluginInterface;
use StoreAccountant\Invoice\InvoicePluginDetector;
use StoreAccountant\Invoice\InvoicePluginRegistry;

/**
 * Tests invoice plugin detection.
 */
final class InvoicePluginDetectorTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_enabled_delegates_to_registry(): void {
		$plugin = $this->createMock( InvoicePluginInterface::class );
		$plugin->method( 'get_id' )->willReturn( 'pdf_invoices' );
		$plugin->method( 'is_active' )->willReturn( true );

		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'pdf_invoices' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $plugin ] );

		self::assertSame( $plugin, ( new InvoicePluginDetector( new InvoicePluginRegistry() ) )->get_enabled() );
	}

	public function test_is_enabled_reflects_enabled_plugin_presence(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( '' );

		self::assertFalse( ( new InvoicePluginDetector( new InvoicePluginRegistry() ) )->is_enabled() );
	}
}
