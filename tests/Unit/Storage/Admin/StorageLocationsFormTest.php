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

namespace StoreAccountant\Tests\Unit\Storage\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Storage\Admin\StorageLocationsForm;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;

/**
 * Tests storage location settings form rendering.
 */
final class StorageLocationsFormTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text, string $domain = 'default' ): void {
				echo $text;
			}
		);
		Functions\when( 'checked' )->alias(
			static function ( bool $checked ): void {
				if ( $checked ) {
					echo 'checked="checked"';
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
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_render_fields_outputs_registered_adapters_and_enabled_state(): void {
		$local = $this->adapter( 'local' );
		$s3    = $this->adapter( 's3-compatible' );

		Functions\expect( 'apply_filters' )
			->twice()
			->with( 'storeaccountant_storage_adapter', [] )
			->andReturn( [ $local, $s3 ] );
		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_storage_adapters', null )
			->andReturn( [ 'local' ] );
		Functions\expect( 'sanitize_text_field' )
			->once()
			->with( 's3-compatible' )
			->andReturn( 's3-compatible' );

		$output = $this->render_form();

		self::assertStringContainsString( 'storage_adapter_local', $output );
		self::assertStringContainsString( 'S3 Compatible', $output );
		self::assertStringContainsString( 'id="storeaccountant-storage-engine-local"', $output );
		self::assertStringContainsString( 'value="local"', $output );
		self::assertStringContainsString( 'checked="checked"', $output );
		self::assertStringNotContainsString( 'disabled="disabled"', $output );
		self::assertStringNotContainsString( 'type="hidden"', $output );
	}

	public function test_render_fields_locks_single_available_adapter(): void {
		$local = $this->adapter( 'local' );

		Functions\expect( 'apply_filters' )
			->twice()
			->with( 'storeaccountant_storage_adapter', [] )
			->andReturn( [ $local ] );
		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_storage_adapters', null )
			->andReturn( [] );

		$output = $this->render_form();

		self::assertStringContainsString( 'checked="checked"', $output );
		self::assertStringContainsString( 'disabled="disabled"', $output );
		self::assertStringContainsString( 'type="hidden"', $output );
		self::assertStringContainsString( 'This is the only available storage location and cannot be disabled.', $output );
	}

	private function render_form(): string {
		ob_start();
		( new StorageLocationsForm( new StorageAdapterRegistry() ) )->render_fields();

		return (string) ob_get_clean();
	}

	private function adapter( string $id ): StorageAdapterInterface {
		$adapter = $this->createMock( StorageAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( $id );

		return $adapter;
	}
}
