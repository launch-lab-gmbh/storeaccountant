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

namespace StoreAccountant\Security;

use WP_Error;
use function bin2hex;
use function defined;
use function function_exists;
use function hash;
use function hex2bin;
use function is_array;
use function is_string;
use function json_decode;
use function openssl_cipher_iv_length;
use function openssl_decrypt;
use function openssl_encrypt;
use function random_bytes;
use function sodium_crypto_secretbox;
use function sodium_crypto_secretbox_open;
use function wp_salt;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypts small secrets with Sodium first and OpenSSL as fallback.
 */
final readonly class ReversibleCrypto {
	private const CONTEXT          = 'storeaccountant_download_passwords';
	private const PROVIDER_SODIUM  = 'sodium';
	private const PROVIDER_OPENSSL = 'openssl-aes-256-gcm';
	private const CIPHER           = 'aes-256-gcm';

	/**
	 * Checks whether reversible encrypted storage is available.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function is_available(): bool {
		return null !== $this->get_active_provider();
	}

	/**
	 * Gets the selected provider ID.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_active_provider(): ?string {
		if ( function_exists( 'sodium_crypto_secretbox' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return self::PROVIDER_SODIUM;
		}

		if ( function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' ) ) {
			return self::PROVIDER_OPENSSL;
		}

		return null;
	}

	/**
	 * Encrypts a secret.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function encrypt( string $plain_text ): string|WP_Error {
		$provider = $this->get_active_provider();

		if ( null === $provider ) {
			return new WP_Error(
				'storeaccountant_crypto_unavailable',
				__( 'Download password encryption is unavailable on this server.', 'storeaccountant' )
			);
		}

		return self::PROVIDER_SODIUM === $provider
			? $this->encrypt_with_sodium( $plain_text )
			: $this->encrypt_with_openssl( $plain_text );
	}

	/**
	 * Decrypts a previously encrypted secret.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function decrypt( string $encrypted_value ): string|WP_Error {
		$payload = json_decode( $encrypted_value, true );

		if ( ! is_array( $payload ) || ! is_string( $payload['provider'] ?? null ) ) {
			return new WP_Error(
				'storeaccountant_crypto_payload_invalid',
				__( 'The stored download password cannot be read.', 'storeaccountant' )
			);
		}

		return match ( $payload['provider'] ) {
			self::PROVIDER_SODIUM => $this->decrypt_with_sodium( $payload ),
			self::PROVIDER_OPENSSL => $this->decrypt_with_openssl( $payload ),
			default => new WP_Error(
				'storeaccountant_crypto_provider_unavailable',
				__( 'The stored download password uses an unavailable encryption provider.', 'storeaccountant' )
			),
		};
	}

	/**
	 * Encrypts with Sodium secretbox.
	 */
	private function encrypt_with_sodium( string $plain_text ): string|WP_Error {
		$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = sodium_crypto_secretbox( $plain_text, $nonce, $this->get_key() );

		return (string) wp_json_encode(
			[
				'provider'   => self::PROVIDER_SODIUM,
				'nonce'      => bin2hex( $nonce ),
				'ciphertext' => bin2hex( $ciphertext ),
			]
		);
	}

	/**
	 * Decrypts with Sodium secretbox.
	 *
	 * @param array<string, mixed> $payload Encrypted payload.
	 */
	private function decrypt_with_sodium( array $payload ): string|WP_Error {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return new WP_Error(
				'storeaccountant_crypto_provider_unavailable',
				__( 'The stored download password uses an unavailable encryption provider.', 'storeaccountant' )
			);
		}

		$nonce      = is_string( $payload['nonce'] ?? null ) ? hex2bin( $payload['nonce'] ) : false;
		$ciphertext = is_string( $payload['ciphertext'] ?? null ) ? hex2bin( $payload['ciphertext'] ) : false;

		if ( false === $nonce || false === $ciphertext ) {
			return new WP_Error(
				'storeaccountant_crypto_payload_invalid',
				__( 'The stored download password cannot be read.', 'storeaccountant' )
			);
		}

		$plain_text = sodium_crypto_secretbox_open( $ciphertext, $nonce, $this->get_key() );

		return false !== $plain_text ? $plain_text : new WP_Error(
			'storeaccountant_crypto_decrypt_failed',
			__( 'The stored download password could not be decrypted.', 'storeaccountant' )
		);
	}

	/**
	 * Encrypts with OpenSSL AES-256-GCM.
	 */
	private function encrypt_with_openssl( string $plain_text ): string|WP_Error {
		$iv_length = openssl_cipher_iv_length( self::CIPHER );

		if ( false === $iv_length || $iv_length <= 0 ) {
			return new WP_Error(
				'storeaccountant_crypto_unavailable',
				__( 'Download password encryption is unavailable on this server.', 'storeaccountant' )
			);
		}

		$tag        = '';
		$iv         = random_bytes( $iv_length );
		$ciphertext = openssl_encrypt( $plain_text, self::CIPHER, $this->get_key(), OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $ciphertext || '' === $tag ) {
			return new WP_Error(
				'storeaccountant_crypto_encrypt_failed',
				__( 'The download password could not be encrypted.', 'storeaccountant' )
			);
		}

		return (string) wp_json_encode(
			[
				'provider'   => self::PROVIDER_OPENSSL,
				'iv'         => bin2hex( $iv ),
				'tag'        => bin2hex( $tag ),
				'ciphertext' => bin2hex( $ciphertext ),
			]
		);
	}

	/**
	 * Decrypts with OpenSSL AES-256-GCM.
	 *
	 * @param array<string, mixed> $payload Encrypted payload.
	 */
	private function decrypt_with_openssl( array $payload ): string|WP_Error {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return new WP_Error(
				'storeaccountant_crypto_provider_unavailable',
				__( 'The stored download password uses an unavailable encryption provider.', 'storeaccountant' )
			);
		}

		$iv         = is_string( $payload['iv'] ?? null ) ? hex2bin( $payload['iv'] ) : false;
		$tag        = is_string( $payload['tag'] ?? null ) ? hex2bin( $payload['tag'] ) : false;
		$ciphertext = is_string( $payload['ciphertext'] ?? null ) ? hex2bin( $payload['ciphertext'] ) : false;

		if ( false === $iv || false === $tag || false === $ciphertext ) {
			return new WP_Error(
				'storeaccountant_crypto_payload_invalid',
				__( 'The stored download password cannot be read.', 'storeaccountant' )
			);
		}

		$plain_text = openssl_decrypt( $ciphertext, self::CIPHER, $this->get_key(), OPENSSL_RAW_DATA, $iv, $tag );

		return false !== $plain_text ? $plain_text : new WP_Error(
			'storeaccountant_crypto_decrypt_failed',
			__( 'The stored download password could not be decrypted.', 'storeaccountant' )
		);
	}

	/**
	 * Derives a 32-byte key from WordPress salts and a plugin-specific context.
	 */
	private function get_key(): string {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) . wp_salt( 'secure_auth' ) : '';

		if ( '' === $salt && defined( 'AUTH_KEY' ) ) {
			$salt = AUTH_KEY;
		}

		return hash( 'sha256', $salt . '|' . self::CONTEXT, true );
	}
}
