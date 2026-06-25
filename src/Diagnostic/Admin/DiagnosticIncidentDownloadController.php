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

namespace StoreAccountant\Diagnostic\Admin;

use JsonException;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Contract\WordPress\Request;
use StoreAccountant\Diagnostic\DiagnosticIncidentRepository;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use function add_action;
use function check_admin_referer;
use function esc_html;
use function esc_html__;
use function header;
use function is_wp_error;
use function json_decode;
use function nocache_headers;
use function sprintf;
use function strlen;
use function wp_die;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streams protected diagnostic incident files to authorized users.
 */
final readonly class DiagnosticIncidentDownloadController implements HookRegistrarInterface {
	public const ACTION = 'storeaccountant_download_diagnostic_incident';

	/**
	 * Initializes the controller.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param DiagnosticIncidentRepository $repository  Incident repository.
	 * @param PermissionChecker            $permissions Permission checker.
	 */
	public function __construct(
		private DiagnosticIncidentRepository $repository,
		private PermissionChecker $permissions
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Handles the diagnostic incident download.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function handle(): void {
		if ( ! $this->permissions->can( PermissionActionIds::DIAGNOSTIC_PACKAGE_DOWNLOAD ) ) {
			wp_die( esc_html__( 'You are not allowed to download diagnostic packages.', 'storeaccountant' ) );
		}

		check_admin_referer( self::ACTION, '_wpnonce' );

		$support_id = Request::get_key( 'support_id' );
		$contents   = $this->repository->read( $support_id );

		if ( is_wp_error( $contents ) ) {
			wp_die( esc_html( $contents->get_error_message() ) );
		}

		try {
			$decoded = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );
			$output  = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		} catch ( JsonException ) {
			$output = false;
		}

		if ( false === $output ) {
			wp_die( esc_html__( 'StoreAccountant could not read the diagnostic incident file.', 'storeaccountant' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( sprintf( 'Content-Disposition: attachment; filename="storeaccountant-diagnostic-%s.json"', $support_id ) );
		header( 'Content-Length: ' . strlen( $output ) );

		echo wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}
}
