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

namespace StoreAccountant\Tests\Unit\Invoice\Export\Order\Configuration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Invoice\Contract\InvoicePluginInterface;
use StoreAccountant\Invoice\Export\Order\Configuration\InvoiceAttachmentConfigurationFieldProvider;
use StoreAccountant\Invoice\Export\Order\InvoiceExportAttachmentSettings;
use StoreAccountant\Invoice\InvoiceFileType;
use StoreAccountant\Invoice\InvoicePluginDetector;
use StoreAccountant\Invoice\InvoicePluginRegistry;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;

/**
 * Tests invoice attachment configuration fields.
 */
final class InvoiceAttachmentConfigurationFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo $text;
			}
		);
		Functions\when( 'esc_html' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'esc_attr' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'checked' )->alias( static fn ( bool $checked ): string => $checked ? 'checked="checked"' : '' );
		Functions\when( 'disabled' )->alias( static fn ( bool $disabled ): string => $disabled ? 'disabled="disabled"' : '' );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', $value ) ?? '' )
		);
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_configuration_field_provider_filter(): void {
		$provider = $this->provider();

		Monkey\Filters\expectAdded( 'storeaccountant_export_configuration_form_field_provider' )->once();

		$provider->register();

		self::assertSame( InvoiceExportAttachmentSettings::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_export_type_accepts_only_orders(): void {
		$provider = $this->provider();

		self::assertTrue( $provider->supports_export_type( OrderExportAdapter::ADAPTER_ID ) );
		self::assertFalse( $provider->supports_export_type( 'customers' ) );
	}

	public function test_sanitize_settings_normalizes_file_type_selection(): void {
		$settings = $this->provider()->sanitize_settings(
			[
				'storeaccountant_invoice_file_types' => [ 'PDF File', 'xml', '../bad', '' ],
			]
		);

		self::assertTrue( $settings[ InvoiceExportAttachmentSettings::OPTION_EXPORT_FILES ] );
		self::assertSame(
			[ 'pdffile', 'xml', 'bad', '' ],
			$settings[ InvoiceExportAttachmentSettings::OPTION_FILE_TYPES ]
		);
	}

	public function test_sanitize_settings_disables_export_when_no_types_are_selected(): void {
		$settings = $this->provider()->sanitize_settings( [] );

		self::assertFalse( $settings[ InvoiceExportAttachmentSettings::OPTION_EXPORT_FILES ] );
		self::assertSame( [], $settings[ InvoiceExportAttachmentSettings::OPTION_FILE_TYPES ] );
	}

	public function test_validate_settings_currently_accepts_normalized_settings(): void {
		self::assertTrue(
			$this->provider()->validate_settings(
				[
					InvoiceExportAttachmentSettings::OPTION_EXPORT_FILES => true,
					InvoiceExportAttachmentSettings::OPTION_FILE_TYPES   => [ 'pdf' ],
				]
			)
		);
	}

	public function test_render_fields_outputs_enabled_invoice_file_types(): void {
		$plugin = $this->plugin();

		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_invoice_plugin', '' )
			->andReturn( 'pdf_plugin' );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_invoice_plugin', [] )
			->andReturn( [ $plugin ] );

		ob_start();
		$this->provider()->render_fields(
			[
				InvoiceExportAttachmentSettings::OPTION_EXPORT_FILES => true,
				InvoiceExportAttachmentSettings::OPTION_FILE_TYPES   => [ 'xml' ],
			]
		);
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Invoice Files', $output );
		self::assertStringContainsString( 'storeaccountant-invoice-file-type-pdf', $output );
		self::assertStringContainsString( 'storeaccountant-invoice-file-type-xml', $output );
		self::assertStringContainsString( 'Export e-invoice as attachment.', $output );
	}

	public function test_render_fields_outputs_nothing_when_no_invoice_plugin_is_enabled(): void {
		Functions\expect( 'get_option' )->once()->with( 'storeaccountant_enabled_invoice_plugin', '' )->andReturn( '' );

		ob_start();
		$this->provider()->render_fields( [] );
		$output = (string) ob_get_clean();

		self::assertSame( '', $output );
	}

	private function provider(): InvoiceAttachmentConfigurationFieldProvider {
		return new InvoiceAttachmentConfigurationFieldProvider(
			new InvoicePluginDetector( new InvoicePluginRegistry() )
		);
	}

	private function plugin(): InvoicePluginInterface {
		$plugin = $this->createMock( InvoicePluginInterface::class );
		$plugin->method( 'get_id' )->willReturn( 'pdf_plugin' );
		$plugin->method( 'is_active' )->willReturn( true );
		$plugin->method( 'get_invoice_file_types' )
			->willReturn(
				[
					new InvoiceFileType( 'pdf', 'PDF' ),
					new InvoiceFileType( 'xml', 'XML' ),
				]
			);

		return $plugin;
	}
}
