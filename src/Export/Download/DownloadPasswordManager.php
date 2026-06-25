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

namespace StoreAccountant\Export\Download;

use WP_Error;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Security\ReversibleCrypto;
use function bin2hex;
use function get_option;
use function get_post_meta;
use function password_hash;
use function password_verify;
use function random_bytes;
use function trim;
use function update_option;
use function update_post_meta;
use function wp_check_password;
use function wp_generate_password;
use function wp_hash_password;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages encrypted download passwords and verification hashes.
 */
final readonly class DownloadPasswordManager {
	public const OPTION_GLOBAL_PASSWORD      = 'storeaccountant_download_password';
	public const OPTION_GLOBAL_PASSWORD_HASH = 'storeaccountant_download_password_hash';

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private ReversibleCrypto $crypto
	) {}

	/**
	 * Checks whether password storage and reveal can work.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function is_available(): bool {
		return $this->crypto->is_available();
	}

	/**
	 * Ensures a global password exists.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function ensure_global_password(): bool|WP_Error {
		if ( $this->has_global_password() ) {
			return true;
		}

		if ( ! $this->is_available() ) {
			return new WP_Error(
				'storeaccountant_download_password_crypto_unavailable',
				__( 'Download password encryption is unavailable on this server.', 'storeaccountant' )
			);
		}

		return $this->save_global_password( $this->generate_password() );
	}

	/**
	 * Stores a new global password.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function save_global_password( string $plain_text ): bool|WP_Error {
		return $this->save_password_options( $plain_text, self::OPTION_GLOBAL_PASSWORD, self::OPTION_GLOBAL_PASSWORD_HASH );
	}

	/**
	 * Resolves the plain text password that will be stored for a password submission.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_password_for_submission( string $plain_text ): string|WP_Error {
		$plain_text = trim( $plain_text );

		if ( '' !== $plain_text ) {
			return $plain_text;
		}

		$ensured = $this->ensure_global_password();

		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}

		return $this->reveal_global_password();
	}

	/**
	 * Resolves the plain text password that will be stored for a configuration submission.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_configuration_password_for_submission( string $plain_text ): string|WP_Error {
		return $this->get_password_for_submission( $plain_text );
	}

	/**
	 * Gets an encrypted password snapshot from a submitted password value.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array{encrypted:string, hash:string}|WP_Error
	 */
	public function get_snapshot_for_submission( string $plain_text ): array|WP_Error {
		$plain_text = $this->get_password_for_submission( $plain_text );

		if ( is_wp_error( $plain_text ) ) {
			return $plain_text;
		}

		if ( ! $this->is_available() ) {
			return new WP_Error(
				'storeaccountant_download_password_crypto_unavailable',
				__( 'Download password encryption is unavailable on this server.', 'storeaccountant' )
			);
		}

		$encrypted = $this->crypto->encrypt( $plain_text );

		if ( is_wp_error( $encrypted ) ) {
			return $encrypted;
		}

		return [
			'encrypted' => $encrypted,
			'hash'      => $this->hash_password( $plain_text ),
		];
	}

	/**
	 * Stores a configuration download password.
	 *
	 * An empty submitted password snapshots the current global download password.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function save_configuration_password( int $configuration_id, string $plain_text ): bool|WP_Error {
		$snapshot = $this->get_snapshot_for_submission( $plain_text );

		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}

		update_post_meta( $configuration_id, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD, $snapshot['encrypted'] );
		update_post_meta( $configuration_id, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD_HASH, $snapshot['hash'] );

		return true;
	}

	/**
	 * Gets the encrypted password snapshot for an export creation.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array{encrypted:string, hash:string}|WP_Error
	 */
	public function get_effective_snapshot_for_configuration( ?int $configuration_id ): array|WP_Error {
		if ( null !== $configuration_id ) {
			$encrypted = (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD, true );
			$hash      = (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD_HASH, true );

			if ( '' !== $encrypted && '' !== $hash ) {
				return [
					'encrypted' => $encrypted,
					'hash'      => $hash,
				];
			}
		}

		$ensured = $this->ensure_global_password();

		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}

		$encrypted = (string) get_option( self::OPTION_GLOBAL_PASSWORD, '' );
		$hash      = (string) get_option( self::OPTION_GLOBAL_PASSWORD_HASH, '' );

		if ( '' === $encrypted || '' === $hash ) {
			return new WP_Error(
				'storeaccountant_download_password_missing',
				__( 'No usable download password is configured.', 'storeaccountant' )
			);
		}

		return [
			'encrypted' => $encrypted,
			'hash'      => $hash,
		];
	}

	/**
	 * Reveals the current global password.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function reveal_global_password(): string|WP_Error {
		return $this->reveal( (string) get_option( self::OPTION_GLOBAL_PASSWORD, '' ) );
	}

	/**
	 * Reveals the stored configuration override.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function reveal_configuration_override( int $configuration_id ): string|WP_Error {
		return $this->reveal( (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD, true ) );
	}

	/**
	 * Reveals the stored configuration download password.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function reveal_configuration_password( int $configuration_id ): string|WP_Error {
		return $this->reveal_configuration_override( $configuration_id );
	}

	/**
	 * Reveals the password snapshot stored on an export run.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function reveal_export_password( int $export_id ): string|WP_Error {
		return $this->reveal( (string) get_post_meta( $export_id, ExportPostType::META_DOWNLOAD_PASSWORD, true ) );
	}

	/**
	 * Verifies a submitted password against a stored hash.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function verify( string $plain_text, string $hash ): bool {
		if ( '' === $hash ) {
			return false;
		}

		return function_exists( 'wp_check_password' )
			? wp_check_password( $plain_text, $hash )
			: password_verify( $plain_text, $hash );
	}

	/**
	 * Checks whether a global password exists.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function has_global_password(): bool {
		return '' !== (string) get_option( self::OPTION_GLOBAL_PASSWORD, '' )
			&& '' !== (string) get_option( self::OPTION_GLOBAL_PASSWORD_HASH, '' );
	}

	/**
	 * Checks whether a configuration has an override.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function has_configuration_override( int $configuration_id ): bool {
		return '' !== (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD, true )
			&& '' !== (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_DOWNLOAD_PASSWORD_HASH, true );
	}

	/**
	 * Checks whether a configuration has a stored download password.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function has_configuration_password( int $configuration_id ): bool {
		return $this->has_configuration_override( $configuration_id );
	}

	/**
	 * Stores encrypted password and hash options.
	 */
	private function save_password_options( string $plain_text, string $password_option, string $hash_option ): bool|WP_Error {
		$plain_text = trim( $plain_text );

		if ( '' === $plain_text ) {
			return new WP_Error(
				'storeaccountant_download_password_empty',
				__( 'Enter a download password.', 'storeaccountant' )
			);
		}

		if ( ! $this->is_available() ) {
			return new WP_Error(
				'storeaccountant_download_password_crypto_unavailable',
				__( 'Download password encryption is unavailable on this server.', 'storeaccountant' )
			);
		}

		$encrypted = $this->crypto->encrypt( $plain_text );

		if ( is_wp_error( $encrypted ) ) {
			return $encrypted;
		}

		update_option( $password_option, $encrypted, false );
		update_option( $hash_option, $this->hash_password( $plain_text ), false );

		return true;
	}

	/**
	 * Decrypts an encrypted value for authorized backend reveal screens.
	 */
	private function reveal( string $encrypted ): string|WP_Error {
		if ( '' === $encrypted ) {
			return new WP_Error(
				'storeaccountant_download_password_missing',
				__( 'No download password is stored.', 'storeaccountant' )
			);
		}

		return $this->crypto->decrypt( $encrypted );
	}

	/**
	 * Hashes a password for verification-only checks.
	 */
	private function hash_password( string $plain_text ): string {
		return function_exists( 'wp_hash_password' )
			? wp_hash_password( $plain_text )
			: password_hash( $plain_text, PASSWORD_DEFAULT );
	}

	/**
	 * Generates a default download password.
	 */
	private function generate_password(): string {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 24, true, true );
		}

		return bin2hex( random_bytes( 16 ) );
	}
}
