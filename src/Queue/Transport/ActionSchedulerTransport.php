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

namespace StoreAccountant\Queue\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use StoreAccountant\Export\Queue\Message\FinalizeExportAttachmentsMessage;
use StoreAccountant\Export\Queue\Message\FinalizeExportMessage;
use StoreAccountant\Export\Queue\Message\ProcessExportBatchMessage;
use StoreAccountant\Export\Queue\Message\StartExportMessage;
use function function_exists;
use function get_debug_type;
use function property_exists;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Messenger transport backed by WooCommerce Action Scheduler.
 */
final readonly class ActionSchedulerTransport implements TransportInterface {
	public const HOOK_EXPORT_START                = 'storeaccountant_export_queue_start';
	public const HOOK_EXPORT_PROCESS_BATCH        = 'storeaccountant_export_queue_process_batch';
	public const HOOK_EXPORT_FINALIZE             = 'storeaccountant_export_queue_finalize';
	public const HOOK_EXPORT_FINALIZE_ATTACHMENTS = 'storeaccountant_export_queue_finalize_attachments';
	public const HOOK_DEFAULT                     = 'storeaccountant_queue_message';
	private const GROUP                           = 'storeaccountant';

	/**
	 * Initializes the transport.
	 *
	 * @param string              $queue_name Queue name.
	 * @param SerializerInterface $serializer Messenger transport serializer.
	 */
	public function __construct(
		private string $queue_name,
		private SerializerInterface $serializer
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TransportException When Action Scheduler or WordPress cron cannot queue the message.
	 */
	public function send( Envelope $envelope ): Envelope {
		$encoded = $this->serializer->encode( $envelope );
		$hook    = $this->get_hook( $envelope );
		$args    = [
			'queue_name'    => $this->queue_name,
			'envelope'      => $encoded,
			'message_class' => get_debug_type( $envelope->getMessage() ),
		];
		$message = $envelope->getMessage();

		if ( property_exists( $message, 'export_id' ) ) {
			$args['export_id'] = (int) $message->export_id;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			$action_id = as_enqueue_async_action( $hook, $args, self::GROUP );

			if ( is_wp_error( $action_id ) || false === $action_id ) {
				throw new TransportException( 'The message could not be queued through Action Scheduler.' );
			}

			return $envelope->with( new TransportMessageIdStamp( (string) $action_id ) );
		}

		$scheduled = wp_schedule_single_event( time(), $hook, $args, true );

		if ( is_wp_error( $scheduled ) ) {
			throw new TransportException( 'The message could not be queued through WordPress cron.' );
		}

		return $envelope;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get(): iterable {
		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function ack( Envelope $envelope ): void {}

	/**
	 * {@inheritDoc}
	 */
	public function reject( Envelope $envelope ): void {}

	/**
	 * Gets all Action Scheduler hooks handled by this transport.
	 *
	 * @return array<int, string>
	 */
	public static function get_hooks(): array {
		return [
			self::HOOK_EXPORT_START,
			self::HOOK_EXPORT_PROCESS_BATCH,
			self::HOOK_EXPORT_FINALIZE,
			self::HOOK_EXPORT_FINALIZE_ATTACHMENTS,
			self::HOOK_DEFAULT,
		];
	}

	/**
	 * Gets the Action Scheduler hook for the envelope message.
	 *
	 * @param Envelope $envelope Messenger envelope.
	 */
	private function get_hook( Envelope $envelope ): string {
		return match ( $envelope->getMessage()::class ) {
			StartExportMessage::class => self::HOOK_EXPORT_START,
			ProcessExportBatchMessage::class => self::HOOK_EXPORT_PROCESS_BATCH,
			FinalizeExportMessage::class => self::HOOK_EXPORT_FINALIZE,
			FinalizeExportAttachmentsMessage::class => self::HOOK_EXPORT_FINALIZE_ATTACHMENTS,
			default => self::HOOK_DEFAULT,
		};
	}
}
