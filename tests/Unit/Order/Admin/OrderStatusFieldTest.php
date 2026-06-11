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

namespace StoreAccountant\Tests\Unit\Order\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Order\Admin\OrderStatusField;
use StoreAccountant\Order\Export\OrderStatusProvider;

/**
 * Tests order status admin field behavior.
 */
final class OrderStatusFieldTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_statuses_from_request_sanitizes_and_validates_values(): void {
		$this->mock_order_statuses();
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => strtolower( str_replace( ' ', '-', $value ) ) );

		self::assertSame(
			[ 'wc-completed', 'wc-failed' ],
			$this->field()->get_statuses_from_request(
				[
					OrderStatusField::FIELD_NAME => [
						'wc-completed',
						'WC Failed',
						'wc-unknown',
						'wc-completed',
						123,
					],
				]
			)
		);
	}

	public function test_get_default_statuses_uses_order_status_provider(): void {
		$this->mock_order_statuses();

		self::assertSame( [ 'wc-completed', 'wc-failed' ], $this->field()->get_default_statuses() );
	}

	public function test_render_outputs_selected_statuses(): void {
		$this->mock_order_statuses();
		$this->mock_render_functions();

		ob_start();
		$this->field()->render( [ 'wc-failed' ] );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'value="wc-completed"', $output );
		self::assertStringContainsString( 'value="wc-failed"', $output );
		self::assertStringContainsString( 'Failed', $output );
		self::assertStringContainsString( 'checked="checked"', $output );
	}

	public function test_render_read_only_outputs_selected_labels_only(): void {
		$this->mock_order_statuses();
		$this->mock_render_functions();

		ob_start();
		$this->field()->render( [ 'wc-failed' ], true );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( '<li>Failed</li>', $output );
		self::assertStringNotContainsString( '<li>Completed</li>', $output );
		self::assertStringNotContainsString( 'type="checkbox"', $output );
	}

	private function field(): OrderStatusField {
		return new OrderStatusField( new OrderStatusProvider() );
	}

	private function mock_order_statuses(): void {
		Functions\expect( 'wc_get_order_statuses' )
			->once()
			->andReturn(
				[
					'wc-completed' => 'Completed',
					'wc-failed'    => 'Failed',
				]
			);
	}

	private function mock_render_functions(): void {
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo htmlspecialchars( $text, ENT_QUOTES );
			}
		);
		Functions\when( 'esc_attr_e' )->alias(
			static function ( string $text ): void {
				echo htmlspecialchars( $text, ENT_QUOTES );
			}
		);
		Functions\when( 'esc_attr' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_html' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
		Functions\when( 'sanitize_html_class' )->alias( static fn ( string $class ): string => str_replace( '-', '-', $class ) );
		Functions\when( 'checked' )->alias(
			static function ( bool $checked ): void {
				if ( $checked ) {
					echo 'checked="checked"';
				}
			}
		);
	}
}
