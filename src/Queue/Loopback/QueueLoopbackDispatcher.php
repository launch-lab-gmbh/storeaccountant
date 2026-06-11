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

namespace StoreAccountant\Queue\Loopback;

use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Queue\QueueTransportRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts async HTTP loopback requests for manually triggered queued exports.
 */
final readonly class QueueLoopbackDispatcher {
	private const TOKEN_TTL = 15 * MINUTE_IN_SECONDS;

	public function __construct(
		private QueueTransportRegistry $queue_transports
	) {}

	/**
	 * Starts the loopback runner if the active transport supports it.
	 *
	 * @param int $export_id Export post ID.
	 */
	public function maybe_dispatch_for_manual_export( int $export_id ): void {
		$provider = $this->queue_transports->get_active();

		if ( null === $provider || ! $provider->supports_manual_loopback() ) {
			return;
		}

		$token = $this->create_token( $export_id );

		ExportEventDispatcher::dispatch(
			ExportEvents::LOG_ENTRY,
			$export_id,
			'info',
			'Manual export loopback runner requested.',
			[
				'export_id'          => $export_id,
				'transport_provider' => $provider->get_id(),
			]
		);

		$this->dispatch( $export_id, $token );
	}

	/**
	 * Dispatches a loopback continuation request.
	 *
	 * @param int    $export_id Export post ID.
	 * @param string $token     Loopback token.
	 */
	public function dispatch( int $export_id, string $token ): void {
		if ( $export_id <= 0 || ExportPostType::POST_TYPE !== get_post_type( $export_id ) ) {
			return;
		}

		$response = wp_remote_post(
			admin_url( 'admin-post.php' ),
			[
				'blocking' => false,
				'timeout'  => 1,
				'body'     => [
					'action'    => QueueLoopbackEndpoint::ACTION,
					'export_id' => (string) $export_id,
					'token'     => $token,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			ExportEventDispatcher::dispatch(
				ExportEvents::LOG_ENTRY,
				$export_id,
				'error',
				'Loopback request could not be started. The queued export will continue when the queue runner runs.',
				[
					'export_id'     => $export_id,
					'wp_error_code' => $response->get_error_code(),
					'wp_error_data' => $response->get_error_data(),
				]
			);
		}
	}

	/**
	 * Checks whether the provided token is valid for the export.
	 *
	 * @param int    $export_id Export post ID.
	 * @param string $token     Token from the loopback request.
	 */
	public function is_valid_token( int $export_id, string $token ): bool {
		$stored = get_transient( $this->get_token_key( $export_id ) );

		return is_string( $stored ) && '' !== $stored && hash_equals( $stored, $token );
	}

	/**
	 * Refreshes an existing token for another chained loopback request.
	 *
	 * @param int    $export_id Export post ID.
	 * @param string $token     Existing token.
	 */
	public function refresh_token( int $export_id, string $token ): void {
		set_transient( $this->get_token_key( $export_id ), $token, self::TOKEN_TTL );
	}

	/**
	 * Deletes the token for a finished export loopback.
	 *
	 * @param int $export_id Export post ID.
	 */
	public function delete_token( int $export_id ): void {
		delete_transient( $this->get_token_key( $export_id ) );
	}

	/**
	 * Creates a token for the export.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function create_token( int $export_id ): string {
		$token = wp_generate_password( 32, false, false );

		set_transient( $this->get_token_key( $export_id ), $token, self::TOKEN_TTL );

		return $token;
	}

	/**
	 * Gets the transient key for an export loopback token.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function get_token_key( int $export_id ): string {
		return 'storeaccountant_loopback_token_' . $export_id;
	}
}
