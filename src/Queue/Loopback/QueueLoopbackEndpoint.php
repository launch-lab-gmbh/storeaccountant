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

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Contract\WordPress\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles async HTTP loopback requests for StoreAccountant queue processing.
 */
final readonly class QueueLoopbackEndpoint implements HookRegistrarInterface {
	public const ACTION = 'storeaccountant_run_export_queue_loopback';

	public function __construct(
		private QueueLoopbackDispatcher $dispatcher,
		private ActionSchedulerLoopbackRunner $runner
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Handles the loopback request.
	 */
	public function handle(): void {
		$export_id = Request::post_int( 'export_id' );
		$token     = Request::post_text( 'token' );

		if ( $export_id <= 0 || ExportPostType::POST_TYPE !== get_post_type( $export_id ) || '' === $token || ! $this->dispatcher->is_valid_token( $export_id, $token ) ) {
			status_header( 403 );
			exit;
		}

			$should_continue = $this->runner->run( $export_id );

		if ( $should_continue ) {
			$this->dispatcher->refresh_token( $export_id, $token );
			ExportEventDispatcher::dispatch(
				ExportEvents::LOG_ENTRY,
				$export_id,
				'info',
				'Loopback runner rescheduled itself.',
				[
					'export_id' => $export_id,
				]
			);
			$this->dispatcher->dispatch( $export_id, $token );
		} else {
			$this->dispatcher->delete_token( $export_id );
		}

		status_header( 204 );
		exit;
	}
}
