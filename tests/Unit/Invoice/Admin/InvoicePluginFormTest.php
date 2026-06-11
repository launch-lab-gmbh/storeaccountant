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

namespace StoreAccountant\Tests\Unit\Invoice\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Invoice\Admin\InvoicePluginForm;
use StoreAccountant\Invoice\Contract\InvoicePluginInterface;
use StoreAccountant\Invoice\InvoicePluginRegistry;

/**
 * Tests invoice plugin settings form rendering.
 */
final class InvoicePluginFormTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( '__' )->alias(
			static fn ( string $text, string $domain = 'default' ): string => 'invoice_plugin_woocommerce-pdf-invoices-packing-slips' === $text
				? 'WooCommerce PDF Invoices & Packing Slips'
				: $text
		);
		Functions\when( 'esc_html' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text, string $domain = 'default' ): void {
				echo htmlspecialchars( $text, ENT_QUOTES );
			}
		);
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'checked' )->alias(
			static function ( bool $checked ): void {
				if ( $checked ) {
					echo 'checked="checked"';
				}
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_render_fields_outputs_available_plugins_enabled_state_and_description(): void {
		$plugin = $this->plugin( 'woocommerce-pdf-invoices-packing-slips', true );

		Functions\expect( 'apply_filters' )
			->twice()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $plugin ] );
		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'woocommerce-pdf-invoices-packing-slips' );

		$output = $this->render_form();

		self::assertStringContainsString( 'WooCommerce PDF Invoices &amp; Packing Slips', $output );
		self::assertStringContainsString( 'id="storeaccountant-invoice-plugin-woocommerce-pdf-invoices-packing-slips"', $output );
		self::assertStringContainsString( 'value="woocommerce-pdf-invoices-packing-slips"', $output );
		self::assertStringContainsString( 'checked="checked"', $output );
		self::assertStringContainsString( 'Enable this invoice provider for export fields.', $output );
		self::assertStringContainsString( 'Only one invoice provider can be enabled at a time.', $output );
	}

	public function test_render_fields_outputs_empty_state_without_active_plugins(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [] );
		Functions\expect( 'get_option' )->never();

		$output = $this->render_form();

		self::assertStringContainsString( 'No supported invoice plugin is currently active.', $output );
		self::assertStringNotContainsString( 'storeaccountant_enabled_invoice_plugin', $output );
	}

	private function render_form(): string {
		ob_start();
		( new InvoicePluginForm( new InvoicePluginRegistry() ) )->render_fields();

		return (string) ob_get_clean();
	}

	private function plugin( string $id, bool $active ): InvoicePluginInterface {
		$plugin = $this->createMock( InvoicePluginInterface::class );
		$plugin->method( 'get_id' )->willReturn( $id );
		$plugin->method( 'is_active' )->willReturn( $active );

		return $plugin;
	}
}
