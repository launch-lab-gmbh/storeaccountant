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
use function add_action;
use function add_filter;
use function add_rewrite_rule;
use function get_post_type;
use function get_query_var;
use function status_header;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles async HTTP loopback requests for StoreAccountant queue processing.
 */
final readonly class QueueLoopbackEndpoint implements HookRegistrarInterface {
	public const ROUTE_PATH = 'storeaccountant/queue-loopback';
	private const QUERY_VAR = 'storeaccountant_queue_loopback';

	public function __construct(
		private QueueLoopbackDispatcher $dispatcher,
		private ActionSchedulerLoopbackRunner $runner
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_rewrite_rule' ] );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ] );
	}

	/**
	 * Registers the pretty queue loopback route.
	 */
	public function register_rewrite_rule(): void {
		add_rewrite_rule(
			'^' . self::ROUTE_PATH . '/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * Adds the queue loopback query var.
	 *
	 * @param array<int, string> $query_vars Public query vars.
	 *
	 * @return array<int, string>
	 */
	public function register_query_var( array $query_vars ): array {
		$query_vars[] = self::QUERY_VAR;

		return $query_vars;
	}

	/**
	 * Handles a frontend queue loopback request.
	 */
	public function handle_request(): void {
		if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$this->handle();
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
