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

namespace StoreAccountant\Tests\Unit\Contract\WordPress;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\WordPress\Request;

/**
 * Tests request value normalization.
 */
final class RequestTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_key_sanitizes_scalar_get_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_GET, 'tab', FILTER_CALLBACK, [ 'options' => [ Request::class, 'sanitize_key_value' ] ] )
			->andReturn( 'export-configurations' );

		self::assertSame( 'export-configurations', Request::get_key( 'tab', 'fallback' ) );
	}

	public function test_get_key_returns_fallback_for_non_scalar_get_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_GET, 'tab', FILTER_CALLBACK, [ 'options' => [ Request::class, 'sanitize_key_value' ] ] )
			->andReturn( [ 'not-scalar' ] );

		self::assertSame( 'fallback', Request::get_key( 'tab', 'fallback' ) );
	}

	public function test_get_int_returns_absolute_integer_from_get_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_GET, 'export_id', FILTER_SANITIZE_NUMBER_INT )
			->andReturn( '-42' );

		Functions\expect( 'absint' )
			->once()
			->with( '-42' )
			->andReturn( 42 );

		self::assertSame( 42, Request::get_int( 'export_id' ) );
	}

	public function test_get_int_returns_zero_for_non_scalar_get_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_GET, 'export_id', FILTER_SANITIZE_NUMBER_INT )
			->andReturn( [ 'not-scalar' ] );

		Functions\expect( 'absint' )->never();

		self::assertSame( 0, Request::get_int( 'export_id' ) );
	}

	public function test_has_get_returns_true_for_empty_string_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_GET, 'created', FILTER_CALLBACK, [ 'options' => [ Request::class, 'sanitize_text_value' ] ] )
			->andReturn( '' );

		self::assertTrue( Request::has_get( 'created' ) );
	}

	public function test_has_get_returns_false_for_missing_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_GET, 'created', FILTER_CALLBACK, [ 'options' => [ Request::class, 'sanitize_text_value' ] ] )
			->andReturn( null );

		self::assertFalse( Request::has_get( 'created' ) );
	}

	public function test_post_text_sanitizes_scalar_post_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_POST, 'title', FILTER_CALLBACK, [ 'options' => [ Request::class, 'sanitize_text_value' ] ] )
			->andReturn( 'Monthly Export' );

		self::assertSame( 'Monthly Export', Request::post_text( 'title', 'fallback' ) );
	}

	public function test_post_text_returns_fallback_for_non_scalar_post_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_POST, 'title', FILTER_CALLBACK, [ 'options' => [ Request::class, 'sanitize_text_value' ] ] )
			->andReturn( [ 'not-scalar' ] );

		self::assertSame( 'fallback', Request::post_text( 'title', 'fallback' ) );
	}

	public function test_post_secret_preserves_special_characters(): void {
		$password = '>j(Hq^ENVD Xnz86v/<j/s';

		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_POST, 'password', FILTER_UNSAFE_RAW )
			->andReturn( $password );

		Functions\expect( 'sanitize_text_field' )->never();

		self::assertSame( $password, Request::post_secret( 'password', 'fallback' ) );
	}

	public function test_post_secret_returns_fallback_for_non_scalar_post_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_POST, 'password', FILTER_UNSAFE_RAW )
			->andReturn( [ 'not-scalar' ] );

		self::assertSame( 'fallback', Request::post_secret( 'password', 'fallback' ) );
	}

	public function test_post_key_sanitizes_scalar_post_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_POST, 'adapter', FILTER_CALLBACK, [ 'options' => [ Request::class, 'sanitize_key_value' ] ] )
			->andReturn( 'orders' );

		self::assertSame( 'orders', Request::post_key( 'adapter', 'fallback' ) );
	}

	public function test_post_data_returns_sanitized_post_array(): void {
		Functions\expect( 'filter_input_array' )
			->once()
			->with( INPUT_POST, FILTER_UNSAFE_RAW )
			->andReturn(
				[
					'title'    => '<b>Monthly Export</b>',
					'settings' => [
						'adapter' => '<script>orders</script>',
					],
				]
			);

		Functions\expect( 'sanitize_text_field' )
			->once()
			->with( '<b>Monthly Export</b>' )
			->andReturn( 'Monthly Export' );
		Functions\expect( 'sanitize_text_field' )
			->once()
			->with( '<script>orders</script>' )
			->andReturn( 'orders' );

		self::assertSame(
			[
				'title'    => 'Monthly Export',
				'settings' => [
					'adapter' => 'orders',
				],
			],
			Request::post_data()
		);
	}

	public function test_post_array_returns_sanitized_array_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with(
				INPUT_POST,
				'export_ids',
				FILTER_CALLBACK,
				[
					'flags'   => FILTER_REQUIRE_ARRAY,
					'options' => [ Request::class, 'sanitize_text_value' ],
				]
			)
			->andReturn( [ '12', '25' ] );

		self::assertSame( [ '12', '25' ], Request::post_array( 'export_ids' ) );
	}

	public function test_post_int_returns_absolute_integer_from_post_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_POST, 'batch_size', FILTER_SANITIZE_NUMBER_INT )
			->andReturn( '25' );

		Functions\expect( 'absint' )
			->once()
			->with( '25' )
			->andReturn( 25 );

		self::assertSame( 25, Request::post_int( 'batch_size' ) );
	}

	public function test_post_int_returns_zero_for_non_scalar_post_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_POST, 'batch_size', FILTER_SANITIZE_NUMBER_INT )
			->andReturn( [ 'not-scalar' ] );

		Functions\expect( 'absint' )->never();

		self::assertSame( 0, Request::post_int( 'batch_size' ) );
	}

	public function test_server_key_sanitizes_scalar_server_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_SERVER, 'REQUEST_METHOD', FILTER_CALLBACK, [ 'options' => [ Request::class, 'sanitize_key_value' ] ] )
			->andReturn( 'post' );

		self::assertSame( 'post', Request::server_key( 'REQUEST_METHOD', 'GET' ) );
	}

	public function test_server_text_sanitizes_scalar_server_value(): void {
		Functions\expect( 'filter_input' )
			->once()
			->with( INPUT_SERVER, 'REQUEST_URI', FILTER_CALLBACK, [ 'options' => [ Request::class, 'sanitize_text_value' ] ] )
			->andReturn( '/download/?token=abc' );

		self::assertSame( '/download/?token=abc', Request::server_text( 'REQUEST_URI' ) );
	}

	public function test_sanitize_text_value_uses_wordpress_text_sanitizer(): void {
		Functions\expect( 'sanitize_text_field' )
			->once()
			->with( '<b>Monthly Export</b>' )
			->andReturn( 'Monthly Export' );

		self::assertSame( 'Monthly Export', Request::sanitize_text_value( '<b>Monthly Export</b>' ) );
	}

	public function test_sanitize_key_value_uses_wordpress_key_sanitizer(): void {
		Functions\expect( 'sanitize_key' )
			->once()
			->with( 'Orders!' )
			->andReturn( 'orders' );

		self::assertSame( 'orders', Request::sanitize_key_value( 'Orders!' ) );
	}
}
