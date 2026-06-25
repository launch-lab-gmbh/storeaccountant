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

namespace StoreAccountant\Export\Admin;

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Contract\WordPress\Request;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use function absint;
use function add_action;
use function array_values;
use function check_ajax_referer;
use function get_post_type;
use function wp_send_json_error;
use function wp_send_json_success;
use function __;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin AJAX polling for export list rows.
 */
final readonly class ExportListPollingAjaxController implements HookRegistrarInterface {
	public const ACTION       = 'storeaccountant_poll_exports';
	public const NONCE_ACTION = 'storeaccountant_poll_exports';

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private ExportListPollingResponseFactory $response_factory,
		private PermissionChecker $permissions
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Handles an export polling request.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! $this->permissions->can( PermissionActionIds::EXPORT_LIST ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You are not allowed to view accounting exports.', 'storeaccountant' ),
				],
				403
			);
		}

		$export_ids = $this->get_export_ids_from_request();
		$exports    = [];

		foreach ( $export_ids as $export_id ) {
			if ( $export_id <= 0 || ExportPostType::POST_TYPE !== get_post_type( $export_id ) ) {
				continue;
			}

			if ( ! $this->permissions->can( PermissionActionIds::EXPORT_VIEW, $export_id ) ) {
				continue;
			}

			$exports[] = $this->response_factory->create( $export_id );
		}

		wp_send_json_success(
			[
				'exports' => $exports,
			]
		);
	}

	/**
	 * Gets sanitized export IDs from the AJAX request.
	 *
	 * @return array<int, int>
	 */
	private function get_export_ids_from_request(): array {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$submitted = Request::post_array( 'export_ids' );
		if ( [] === $submitted ) {
			return [];
		}

		return array_map( 'absint', array_values( $submitted ) );
	}
}
