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

namespace StoreAccountant\Tests\Unit\Invoice\Export\Order;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Invoice\Contract\InvoicePluginInterface;
use StoreAccountant\Invoice\Export\Order\InvoiceExportAttachmentSettings;
use StoreAccountant\Invoice\InvoiceFileType;

/**
 * Tests invoice attachment settings.
 */
final class InvoiceExportAttachmentSettingsTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_selected_file_types_filters_unknown_and_non_string_values(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ExportConfigurationPostType::META_ADDITIONAL_SETTINGS, true )
			->andReturn(
				json_encode(
					[
						InvoiceExportAttachmentSettings::PROVIDER_ID => [
							InvoiceExportAttachmentSettings::OPTION_FILE_TYPES => [
								'invoice_pdf',
								'unknown',
								123,
								'credit_note_pdf',
							],
						],
					]
				)
			);

		$settings = new InvoiceExportAttachmentSettings();

		self::assertSame(
			[ 'invoice_pdf', 'credit_note_pdf' ],
			$settings->get_selected_file_types( 42, $this->plugin_with_file_types() )
		);
	}

	public function test_get_selected_file_types_returns_all_available_types_when_legacy_export_files_flag_is_true(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ExportConfigurationPostType::META_ADDITIONAL_SETTINGS, true )
			->andReturn(
				json_encode(
					[
						InvoiceExportAttachmentSettings::PROVIDER_ID => [
							InvoiceExportAttachmentSettings::OPTION_EXPORT_FILES => true,
							InvoiceExportAttachmentSettings::OPTION_FILE_TYPES   => [],
						],
					]
				)
			);

		$settings = new InvoiceExportAttachmentSettings();

		self::assertSame(
			[ 'invoice_pdf', 'credit_note_pdf' ],
			$settings->get_selected_file_types( 42, $this->plugin_with_file_types() )
		);
	}

	public function test_is_enabled_reflects_selected_file_types(): void {
		Functions\expect( 'get_post_meta' )
			->twice()
			->with( 42, ExportConfigurationPostType::META_ADDITIONAL_SETTINGS, true )
			->andReturn(
				json_encode(
					[
						InvoiceExportAttachmentSettings::PROVIDER_ID => [
							InvoiceExportAttachmentSettings::OPTION_FILE_TYPES => [ 'invoice_pdf' ],
						],
					]
				),
				json_encode(
					[
						InvoiceExportAttachmentSettings::PROVIDER_ID => [
							InvoiceExportAttachmentSettings::OPTION_FILE_TYPES => [],
						],
					]
				)
			);

		$settings = new InvoiceExportAttachmentSettings();
		$plugin   = $this->plugin_with_file_types();

		self::assertTrue( $settings->is_enabled( 42, $plugin ) );
		self::assertFalse( $settings->is_enabled( 42, $plugin ) );
	}

	public function test_get_selected_file_types_returns_empty_array_for_invalid_configuration_or_settings(): void {
		$settings = new InvoiceExportAttachmentSettings();

		Functions\expect( 'get_post_meta' )->never();

		self::assertSame( [], $settings->get_selected_file_types( 0, $this->plugin_with_file_types() ) );
	}

	private function plugin_with_file_types(): InvoicePluginInterface {
		$plugin = $this->createMock( InvoicePluginInterface::class );
		$plugin->method( 'get_invoice_file_types' )
			->willReturn(
				[
					new InvoiceFileType( 'invoice_pdf', 'Invoice PDF' ),
					new InvoiceFileType( 'credit_note_pdf', 'Credit Note PDF' ),
				]
			);

		return $plugin;
	}
}
