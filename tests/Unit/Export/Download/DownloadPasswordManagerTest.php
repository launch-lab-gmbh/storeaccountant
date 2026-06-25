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

namespace StoreAccountant\Tests\Unit\Export\Download;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Security\ReversibleCrypto;

/**
 * Tests encrypted download password management.
 */
final class DownloadPasswordManagerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( 'wp_salt' )->justReturn( 'unit-test-salt' );
		Functions\when( 'wp_json_encode' )->alias( [ self::class, 'encode_json' ] );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_is_available_reflects_crypto_availability(): void {
		self::assertTrue( $this->manager()->is_available() );
	}

	public function test_save_global_password_encrypts_and_hashes_password(): void {
		$stored_encrypted = null;
		$stored_hash      = null;

		Functions\expect( 'update_option' )
			->once()
			->with(
				DownloadPasswordManager::OPTION_GLOBAL_PASSWORD,
				Mockery::type( 'string' ),
				false
			)
			->andReturnUsing(
				static function ( string $option, string $value ) use ( &$stored_encrypted ): bool {
					$stored_encrypted = $value;

					return true;
				}
			);
		Functions\expect( 'update_option' )
			->once()
			->with(
				DownloadPasswordManager::OPTION_GLOBAL_PASSWORD_HASH,
				Mockery::type( 'string' ),
				false
			)
			->andReturnUsing(
				static function ( string $option, string $value ) use ( &$stored_hash ): bool {
					$stored_hash = $value;

					return true;
				}
			);

		self::assertTrue( $this->manager()->save_global_password( 'secret-password' ) );
		self::assertIsString( $stored_encrypted );
		self::assertNotSame( 'secret-password', $stored_encrypted );
		self::assertTrue( $this->manager()->verify( 'secret-password', (string) $stored_hash ) );
	}

	public function test_save_global_password_preserves_special_characters(): void {
		$password    = '\'"$&%?>=`jHq^ENVD Xnz86v/<j/s[.|]()-,*+';
		$stored_hash = null;

		Functions\expect( 'update_option' )
			->once()
			->with(
				DownloadPasswordManager::OPTION_GLOBAL_PASSWORD,
				Mockery::type( 'string' ),
				false
			);
		Functions\expect( 'update_option' )
			->once()
			->with(
				DownloadPasswordManager::OPTION_GLOBAL_PASSWORD_HASH,
				Mockery::type( 'string' ),
				false
			)
			->andReturnUsing(
				static function ( string $option, string $value ) use ( &$stored_hash ): bool {
					$stored_hash = $value;

					return true;
				}
			);

		self::assertTrue( $this->manager()->save_global_password( $password ) );
		self::assertTrue( $this->manager()->verify( $password, (string) $stored_hash ) );
		self::assertFalse( $this->manager()->verify( 'j(Hq^ENVD Xnz86v/j/s', (string) $stored_hash ) );
	}

	public function test_empty_submission_reuses_existing_global_password(): void {
		$encrypted = $this->encrypted( 'existing-password' );
		$hash      = password_hash( 'existing-password', PASSWORD_DEFAULT );

		Functions\expect( 'get_option' )
			->once()
			->with( DownloadPasswordManager::OPTION_GLOBAL_PASSWORD, '' )
			->andReturn( $encrypted );
		Functions\expect( 'get_option' )
			->once()
			->with( DownloadPasswordManager::OPTION_GLOBAL_PASSWORD_HASH, '' )
			->andReturn( $hash );
		Functions\expect( 'get_option' )
			->once()
			->with( DownloadPasswordManager::OPTION_GLOBAL_PASSWORD, '' )
			->andReturn( $encrypted );

		self::assertSame( 'existing-password', $this->manager()->get_password_for_submission( '   ' ) );
	}

	public function test_save_configuration_password_stores_submitted_snapshot(): void {
		$hash = null;

		Functions\expect( 'update_post_meta' )
			->once()
			->with( 42, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD, Mockery::type( 'string' ) );
		Functions\expect( 'update_post_meta' )
			->once()
			->with( 42, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD_HASH, Mockery::type( 'string' ) )
			->andReturnUsing(
				static function ( int $post_id, string $meta_key, string $value ) use ( &$hash ): bool {
					$hash = $value;

					return true;
				}
			);

		self::assertTrue( $this->manager()->save_configuration_password( 42, 'configuration-password' ) );
		self::assertTrue( $this->manager()->verify( 'configuration-password', (string) $hash ) );
	}

	public function test_effective_snapshot_prefers_configuration_password_over_global_password(): void {
		$encrypted = $this->encrypted( 'configuration-password' );
		$hash      = password_hash( 'configuration-password', PASSWORD_DEFAULT );

		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD, true )
			->andReturn( $encrypted );
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD_HASH, true )
			->andReturn( $hash );
		Functions\expect( 'get_option' )->never();

		self::assertSame(
			[
				'encrypted' => $encrypted,
				'hash'      => $hash,
			],
			$this->manager()->get_effective_snapshot_for_configuration( 42 )
		);
	}

	public function test_reveal_methods_decrypt_stored_password_snapshots(): void {
		$global        = $this->encrypted( 'global-password' );
		$configuration = $this->encrypted( 'configuration-password' );
		$export        = $this->encrypted( 'export-password' );

		Functions\expect( 'get_option' )
			->once()
			->with( DownloadPasswordManager::OPTION_GLOBAL_PASSWORD, '' )
			->andReturn( $global );
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD, true )
			->andReturn( $configuration );
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 99, ExportPostType::META_DOWNLOAD_PASSWORD, true )
			->andReturn( $export );

		$manager = $this->manager();

		self::assertSame( 'global-password', $manager->reveal_global_password() );
		self::assertSame( 'configuration-password', $manager->reveal_configuration_password( 42 ) );
		self::assertSame( 'export-password', $manager->reveal_export_password( 99 ) );
	}

	public function test_verify_rejects_empty_hash_and_wrong_password(): void {
		$hash = password_hash( 'secret-password', PASSWORD_DEFAULT );

		self::assertFalse( $this->manager()->verify( 'secret-password', '' ) );
		self::assertFalse( $this->manager()->verify( 'wrong-password', $hash ) );
		self::assertTrue( $this->manager()->verify( 'secret-password', $hash ) );
	}

	public function test_has_password_checks_require_encrypted_value_and_hash(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( DownloadPasswordManager::OPTION_GLOBAL_PASSWORD, '' )
			->andReturn( 'encrypted' );
		Functions\expect( 'get_option' )
			->once()
			->with( DownloadPasswordManager::OPTION_GLOBAL_PASSWORD_HASH, '' )
			->andReturn( '' );
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD, true )
			->andReturn( 'encrypted' );
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD_HASH, true )
			->andReturn( 'hash' );

		$manager = $this->manager();

		self::assertFalse( $manager->has_global_password() );
		self::assertTrue( $manager->has_configuration_password( 42 ) );
	}

	public static function encode_json( mixed $value ): string|false {
		return json_encode( $value );
	}

	private function manager(): DownloadPasswordManager {
		return new DownloadPasswordManager( new ReversibleCrypto() );
	}

	private function encrypted( string $plain_text ): string {
		$encrypted = ( new ReversibleCrypto() )->encrypt( $plain_text );

		self::assertIsString( $encrypted );

		return $encrypted;
	}
}
