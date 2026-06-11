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

namespace StoreAccountant\Invoice\Export\Order\Attachment;

use WC_Order;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Attachment\ExportAttachment;
use StoreAccountant\Export\Contract\ExportAttachmentProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Invoice\Export\Order\InvoiceExportAttachmentSettings;
use StoreAccountant\Invoice\InvoicePluginDetector;
use function ltrim;
use function sanitize_file_name;
use function sanitize_key;
use function str_contains;
use function strtolower;
use function trailingslashit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds invoice files to WooCommerce order export archives.
 */
final readonly class InvoiceAttachmentProvider implements ExportAttachmentProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'order_invoice_attachments';

	/**
	 * Initializes the provider.
	 *
	 * @param InvoicePluginDetector           $detector Invoice plugin detector.
	 * @param InvoiceExportAttachmentSettings $settings Invoice attachment settings.
	 */
	public function __construct(
		private InvoicePluginDetector $detector,
		private InvoiceExportAttachmentSettings $settings
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_attachment_provider',
			function ( array $providers ): array {
				$providers[ $this->get_id() ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type && $this->detector->is_enabled();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_directory( ExportContext $context ): string {
		return sanitize_file_name( __( 'Invoices', 'storeaccountant' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_attachments( mixed $item, ExportPayload $payload, ExportContext $context ): iterable {
		$plugin = $this->detector->get_enabled();

		if ( OrderExportAdapter::ADAPTER_ID !== $context->export_type || ! $item instanceof WC_Order || null === $plugin ) {
			return [];
		}

		$attachments = [];
		$file_types  = $this->settings->get_selected_file_types( $context->configuration_id, $plugin );

		foreach ( $file_types as $file_type ) {
			$file = $plugin->get_invoice_file( $item, $file_type );

			if ( null === $file ) {
				continue;
			}

			$file_name = sanitize_file_name( $file->file_name );

			if ( '' === $file_name ) {
				continue;
			}

			$attachments[] = new ExportAttachment(
				$file->stream,
				$file_name,
				$file->mime_type,
				$this->get_internal_path( $file_type, $file_name, $context )
			);
		}

		return $attachments;
	}

	/**
	 * Gets the internal archive path for an invoice attachment.
	 *
	 * @param string        $file_type Invoice file type.
	 * @param string        $file_name Invoice file name.
	 * @param ExportContext $context   Export context.
	 */
	private function get_internal_path( string $file_type, string $file_name, ExportContext $context ): string {
		$directory = ltrim( $this->get_directory( $context ), '/' );
		$file_type = $this->normalize_file_type_directory( $file_type );

		if ( '' !== $file_type ) {
			$directory = trailingslashit( $directory ) . $file_type;
		}

		return trailingslashit( $directory ) . $file_name;
	}

	/**
	 * Normalizes invoice file type IDs into stable archive directory names.
	 *
	 * @param string $file_type Invoice file type.
	 */
	private function normalize_file_type_directory( string $file_type ): string {
		$normalized = sanitize_key( $file_type );
		$lookup     = strtolower( $file_type . ' ' . $normalized );

		if ( 'pdf' === $normalized || str_contains( $lookup, 'pdf' ) ) {
			return 'pdf';
		}

		if ( 'xml' === $normalized || str_contains( $lookup, 'xml' ) ) {
			return 'xml';
		}

		return $normalized;
	}
}
