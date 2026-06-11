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
use StoreAccountant\Invoice\InvoicePluginRegistry;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests invoice plugin registry behavior.
 */
final class InvoicePluginRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_available_returns_only_active_invoice_plugins(): void {
		$active   = $this->plugin( 'active', true );
		$inactive = $this->plugin( 'inactive', false );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ new TestRegistryItem( 'wrong-type' ), $inactive, $active ] );

		self::assertSame( [ 'active' => $active ], ( new InvoicePluginRegistry() )->get_available() );
	}

	public function test_get_enabled_returns_active_selected_plugin(): void {
		$plugin = $this->plugin( 'pdf_invoices', true );

		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'pdf_invoices' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $plugin ] );

		self::assertSame( $plugin, ( new InvoicePluginRegistry() )->get_enabled() );
	}

	public function test_get_enabled_returns_null_for_missing_or_inactive_plugin(): void {
		$plugin = $this->plugin( 'pdf_invoices', false );

		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'pdf_invoices' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $plugin ] );

		self::assertNull( ( new InvoicePluginRegistry() )->get_enabled() );
	}

	public function test_save_enabled_updates_option_for_available_plugin(): void {
		$plugin = $this->plugin( 'pdf_invoices', true );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $plugin ] );

		Functions\expect( 'update_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', 'pdf_invoices', false );

		Functions\expect( 'delete_option' )->never();

		( new InvoicePluginRegistry() )->save_enabled( 'pdf_invoices' );

		self::assertTrue( true );
	}

	public function test_save_enabled_deletes_option_for_unavailable_plugin(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [] );

		Functions\expect( 'delete_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin' );

		Functions\expect( 'update_option' )->never();

		( new InvoicePluginRegistry() )->save_enabled( 'missing' );

		self::assertTrue( true );
	}

	private function plugin( string $id, bool $active ): InvoicePluginInterface {
		$plugin = $this->createMock( InvoicePluginInterface::class );
		$plugin->method( 'get_id' )->willReturn( $id );
		$plugin->method( 'is_active' )->willReturn( $active );

		return $plugin;
	}
}
