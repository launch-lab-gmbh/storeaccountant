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

namespace StoreAccountant\Tests\Unit\Product\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Product\Admin\ProductVariantExportFieldProvider;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use StoreAccountant\Product\Export\Filter\ProductVariantExportFilter;

/**
 * Tests the product variant export field provider.
 */
final class ProductVariantExportFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_filter_field_provider(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_filter_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$this->provider()->register();

		self::assertTrue( true );
	}

	public function test_get_id_and_supports_are_stable(): void {
		$provider = $this->provider();

		self::assertSame( ProductVariantExportFilter::FILTER_ID, $provider->get_id() );
		self::assertTrue( $provider->supports( ProductExportAdapter::ADAPTER_ID ) );
		self::assertFalse( $provider->supports( 'customers' ) );
	}

	public function test_get_selection_from_request_sanitizes_variant_mode(): void {
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $value ) ?? '' )
		);

		$selection = $this->provider()->get_selection_from_request(
			[
				ProductVariantExportFieldProvider::FIELD_VARIANT_MODE => ProductVariantExportFilter::MODE_SEPARATE_VARIANTS,
			]
		);

		self::assertSame( ProductVariantExportFilter::FILTER_ID, $selection->filter_id );
		self::assertSame( ProductVariantExportFilter::MODE_SEPARATE_VARIANTS, $selection->settings['mode'] );
	}

	public function test_get_selection_from_request_falls_back_to_parent_products(): void {
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $value ) ?? '' )
		);

		$selection = $this->provider()->get_selection_from_request(
			[
				ProductVariantExportFieldProvider::FIELD_VARIANT_MODE => 'anything-else',
			]
		);

		self::assertSame( ProductVariantExportFilter::MODE_PARENT_PRODUCTS, $selection->settings['mode'] );
	}

	public function test_get_default_selection_uses_parent_products(): void {
		$selection = $this->provider()->get_default_selection();

		self::assertInstanceOf( ExportFilterSelection::class, $selection );
		self::assertSame( ProductVariantExportFilter::MODE_PARENT_PRODUCTS, $selection->settings['mode'] );
	}

	public function test_render_outputs_variant_mode_select(): void {
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $value ) ?? '' )
		);
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo htmlspecialchars( $text, ENT_QUOTES );
			}
		);
		Functions\when( 'esc_html' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'selected' )->alias(
			static function ( mixed $selected, mixed $current = true ): void {
				if ( $selected === $current ) {
					echo ' selected="selected"';
				}
			}
		);
		Functions\when( 'disabled' )->alias(
			static function ( bool $disabled ): void {
				if ( $disabled ) {
					echo ' disabled="disabled"';
				}
			}
		);

		ob_start();
		$this->provider()->render(
			new ExportFilterSelection(
				ProductVariantExportFilter::FILTER_ID,
				[
					'mode' => ProductVariantExportFilter::MODE_SEPARATE_VARIANTS,
				]
			)
		);
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="storeaccountant_product_variant_mode"', $output );
		self::assertMatchesRegularExpression( '/value="separate_variants"\\s+selected="selected"/', $output );
		self::assertStringContainsString( 'Product Variants', $output );
	}

	private function provider(): ProductVariantExportFieldProvider {
		return new ProductVariantExportFieldProvider();
	}
}
