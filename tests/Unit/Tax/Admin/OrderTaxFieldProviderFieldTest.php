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

namespace StoreAccountant\Tests\Unit\Tax\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Order\Tax\OrderTaxFieldProviderRegistry;
use StoreAccountant\Tax\Admin\OrderTaxFieldProviderField;
use StoreAccountant\Tax\Contract\OrderTaxFieldProviderInterface;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;
use WC_Order;

/**
 * Tests order tax provider admin field.
 */
final class OrderTaxFieldProviderFieldTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo $text;
			}
		);
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'selected' )->alias(
			static function ( string $selected, string $current ): void {
				if ( $selected === $current ) {
					echo 'selected="selected"';
				}
			}
		);
		Functions\when( 'disabled' )->alias(
			static function ( bool $disabled ): void {
				if ( $disabled ) {
					echo 'disabled="disabled"';
				}
			}
		);
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => strtolower( $value ) );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_request_and_configuration_values_are_validated_against_registry(): void {
		Functions\when( 'apply_filters' )->alias(
			fn ( string $hook, array $items ): array => 'storeaccountant_export_order_tax_field_provider' === $hook
				? [ $this->provider( 'simple', 'Simple' ), $this->provider( ExtendedOrderTaxFieldProvider::PROVIDER_ID, 'Extended' ) ]
				: $items
		);
		Functions\expect( 'get_post_meta' )
			->twice()
			->with( 42, ExportConfigurationPostType::META_ORDER_TAX_FIELD_PROVIDER, true )
			->andReturn( 'simple', 'missing' );

		$field = $this->field();

		self::assertSame( 'simple', $field->get_provider_id_from_request( [ 'storeaccountant_order_tax_field_provider' => 'simple' ] ) );
		self::assertSame( '', $field->get_provider_id_from_request( [ 'storeaccountant_order_tax_field_provider' => 'missing' ] ) );
		self::assertSame( 'simple', $field->get_provider_id_from_configuration( 42 ) );
		self::assertSame( ExtendedOrderTaxFieldProvider::PROVIDER_ID, $field->get_provider_id_from_configuration( 42 ) );
	}

	public function test_render_outputs_options_and_marks_saved_provider(): void {
		Functions\when( 'apply_filters' )->alias(
			fn ( string $hook, array $items ): array => 'storeaccountant_export_order_tax_field_provider' === $hook
				? [ $this->provider( 'simple', 'Simple' ), $this->provider( 'extended', 'Extended' ) ]
				: $items
		);

		ob_start();
		$this->field()->render( 'extended', true );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Tax Fields', $output );
		self::assertStringContainsString( 'value="simple"', $output );
		self::assertStringContainsString( 'value="extended"', $output );
		self::assertStringContainsString( 'selected="selected"', $output );
		self::assertStringContainsString( 'disabled="disabled"', $output );
	}

	private function field(): OrderTaxFieldProviderField {
		return new OrderTaxFieldProviderField( new OrderTaxFieldProviderRegistry() );
	}

	private function provider( string $id, string $label ): OrderTaxFieldProviderInterface {
		return new class( $id, $label ) implements OrderTaxFieldProviderInterface {
			public function __construct(
				private readonly string $id,
				private readonly string $label
			) {}

			public function get_id(): string {
				return $this->id;
			}

			public function get_label(): string {
				return $this->label;
			}

			public function get_fields( ExportContext $context ): array {
				return [ 'tax_total' => new Field( 'tax_total', 'Tax Total' ) ];
			}

			public function get_values( WC_Order $order, ExportContext $context ): array {
				return [ 'tax_total' => new FieldValue( 'tax_total', '1.00' ) ];
			}
		};
	}
}
