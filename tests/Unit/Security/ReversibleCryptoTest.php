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

namespace StoreAccountant\Tests\Unit\Security;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Security\ReversibleCrypto;

/**
 * Tests reversible encryption for download password reveal storage.
 */
final class ReversibleCryptoTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( 'wp_salt' )->justReturn( 'unit-test-salt' );
		Functions\when( 'wp_json_encode' )->alias( [ self::class, 'encode_json' ] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_encrypt_decrypt_round_trip(): void {
		$crypto = new ReversibleCrypto();

		self::assertTrue( $crypto->is_available() );

		$encrypted = $crypto->encrypt( 'secret-download-password' );

		self::assertIsString( $encrypted );
		self::assertNotSame( 'secret-download-password', $encrypted );
		self::assertSame( 'secret-download-password', $crypto->decrypt( $encrypted ) );
	}

	/**
	 * Encodes JSON for the mocked WordPress helper.
	 */
	public static function encode_json( mixed $value ): string|false {
		return call_user_func( 'json_encode', $value );
	}
}
