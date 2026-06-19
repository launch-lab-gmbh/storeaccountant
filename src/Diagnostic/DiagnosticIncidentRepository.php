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

namespace StoreAccountant\Diagnostic;

use WP_Error;
use StoreAccountant\Contract\WordPress\WordPressFilesystem;
use StoreAccountant\Storage\ProtectedUploadDirectory;
use function __;
use function is_wp_error;
use function preg_match;
use function sprintf;
use function trailingslashit;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and reads diagnostic incident files.
 */
final readonly class DiagnosticIncidentRepository {
	/**
	 * Initializes the repository.
	 *
	 * @param DiagnosticLogConfiguration $configuration Diagnostic path configuration.
	 * @param ProtectedUploadDirectory   $directory     Protected directory preparer.
	 */
	public function __construct(
		private DiagnosticLogConfiguration $configuration,
		private ProtectedUploadDirectory $directory
	) {}

	/**
	 * Ensures the diagnostic log directory exists and is protected.
	 *
	 * @return true|WP_Error
	 */
	public function ensure(): true|WP_Error {
		$root_path = $this->configuration->get_root_path();

		if ( is_wp_error( $root_path ) ) {
			return $root_path;
		}

		return $this->directory->ensure( $root_path, $this->configuration->display_root_path );
	}

	/**
	 * Stores an incident payload.
	 *
	 * @param string               $support_id Incident support ID.
	 * @param array<string, mixed> $payload    Incident payload.
	 *
	 * @return DiagnosticIncident|WP_Error
	 */
	public function store( string $support_id, array $payload ): DiagnosticIncident|WP_Error {
		if ( ! $this->is_valid_support_id( $support_id ) ) {
			return new WP_Error(
				'storeaccountant_diagnostic_support_id_invalid',
				__( 'The diagnostic support ID is invalid.', 'storeaccountant' )
			);
		}

		$ensured = $this->ensure();

		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}

		$root_path = $this->configuration->get_root_path();

		if ( is_wp_error( $root_path ) ) {
			return $root_path;
		}

		$file_name = $support_id . '.json';
		$contents  = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( false === $contents ) {
			return new WP_Error(
				'storeaccountant_diagnostic_payload_not_encoded',
				__( 'StoreAccountant could not encode the diagnostic incident payload.', 'storeaccountant' )
			);
		}

		if ( ! WordPressFilesystem::put_contents( trailingslashit( $root_path ) . $file_name, $contents ) ) {
			return new WP_Error(
				'storeaccountant_diagnostic_file_not_written',
				sprintf(
					/* translators: %s: diagnostic log directory path */
					__( 'StoreAccountant could not write the diagnostic incident file in %s.', 'storeaccountant' ),
					$this->configuration->display_root_path
				)
			);
		}

		return new DiagnosticIncident( $support_id, $file_name );
	}

	/**
	 * Reads an incident payload as text.
	 *
	 * @param string $support_id Incident support ID.
	 *
	 * @return string|WP_Error
	 */
	public function read( string $support_id ): string|WP_Error {
		if ( ! $this->is_valid_support_id( $support_id ) ) {
			return new WP_Error(
				'storeaccountant_diagnostic_support_id_invalid',
				__( 'The diagnostic support ID is invalid.', 'storeaccountant' )
			);
		}

		$root_path = $this->configuration->get_root_path();

		if ( is_wp_error( $root_path ) ) {
			return $root_path;
		}

		$contents = WordPressFilesystem::get_contents( trailingslashit( $root_path ) . $support_id . '.json' );

		if ( false === $contents ) {
			return new WP_Error(
				'storeaccountant_diagnostic_file_not_readable',
				__( 'StoreAccountant could not read the diagnostic incident file.', 'storeaccountant' )
			);
		}

		return $contents;
	}

	/**
	 * Checks whether the support ID matches the generated format.
	 */
	public function is_valid_support_id( string $support_id ): bool {
		return 1 === preg_match( '/^[a-f0-9-]{36}$/', $support_id );
	}
}
