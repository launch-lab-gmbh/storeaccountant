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

use Throwable;
use WC_Order;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Attachment\ExportAttachment;
use StoreAccountant\Export\Contract\ExportAttachmentProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\Mapping\FieldMappingRepository;
use StoreAccountant\Invoice\Export\Order\Field\InvoiceFieldProvider;
use StoreAccountant\Invoice\Export\Order\InvoiceExportAttachmentSettings;
use StoreAccountant\Invoice\Contract\InvoicePluginInterface;
use StoreAccountant\Invoice\InvoicePluginDetector;
use function array_filter;
use function array_values;
use function in_array;
use function is_string;
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
	 * @since 1.0.0
	 * @internal
	 *
	 * @param InvoicePluginDetector           $detector Invoice plugin detector.
	 * @param InvoiceExportAttachmentSettings $settings Invoice attachment settings.
	 * @param FieldMappingRepository          $field_mapping Field mapping repository.
	 */
	public function __construct(
		private InvoicePluginDetector $detector,
		private InvoiceExportAttachmentSettings $settings,
		private FieldMappingRepository $field_mapping
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
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
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports( ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type && $this->detector->is_enabled();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_directory( ExportContext $context ): string {
		return sanitize_file_name( __( 'Invoices', 'storeaccountant' ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_attachments( mixed $item, ExportPayload $payload, ExportContext $context ): iterable {
		$plugin = $this->detector->get_enabled();

		if ( OrderExportAdapter::ADAPTER_ID !== $context->export_type || ! $item instanceof WC_Order || null === $plugin ) {
			return [];
		}

		try {
			$file_types = $this->get_exported_file_types( $context, $plugin, $payload->export_id );
		} catch ( Throwable $exception ) {
			$this->log_invoice_plugin_warning( $payload->export_id, $item, $plugin->get_id(), '', $exception );

			return [];
		}

		if ( [] === $file_types ) {
			return [];
		}

		try {
			if ( ! $plugin->has_invoice( $item ) ) {
				return [];
			}
		} catch ( Throwable $exception ) {
			$this->log_invoice_plugin_warning( $payload->export_id, $item, $plugin->get_id(), '', $exception );

			return [];
		}

		$attachments = [];

		foreach ( $file_types as $file_type ) {
			try {
				$file = $plugin->get_invoice_file( $item, $file_type );
			} catch ( Throwable $exception ) {
				$this->log_invoice_plugin_warning( $payload->export_id, $item, $plugin->get_id(), $file_type, $exception );

				continue;
			}

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
	 * Gets invoice file types selected in settings and enabled in field mapping.
	 *
	 * @param ExportContext          $context   Export context.
	 * @param InvoicePluginInterface $plugin    Invoice plugin.
	 * @param int                    $export_id Export post ID.
	 *
	 * @return array<int, string>
	 */
	private function get_exported_file_types( ExportContext $context, InvoicePluginInterface $plugin, int $export_id ): array {
		$selected_file_types = $this->settings->get_selected_file_types( $context->configuration_id, $plugin, $export_id );
		$mapped_file_types   = $this->get_mapped_file_types( $context, $plugin );

		return array_values(
			array_filter(
				$selected_file_types,
				static fn ( string $file_type ): bool => in_array( $file_type, $mapped_file_types, true )
			)
		);
	}

	/**
	 * Gets invoice file types that are enabled in the final field mapping.
	 *
	 * @param ExportContext          $context Export context.
	 * @param InvoicePluginInterface $plugin  Invoice plugin.
	 *
	 * @return array<int, string>
	 */
	private function get_mapped_file_types( ExportContext $context, InvoicePluginInterface $plugin ): array {
		$available_fields = [];

		foreach ( $plugin->get_invoice_file_types() as $file_type ) {
			$type_id = sanitize_key( $file_type->id );

			if ( '' === $type_id ) {
				continue;
			}

			$field_id                      = 'invoice_file_name_' . $type_id;
			$available_fields[ $field_id ] = new Field(
				$field_id,
				$field_id,
				options: [
					InvoiceFieldProvider::OPTION_INVOICE_FILE_TYPE => $file_type->id,
				]
			);
		}

		$mapped_file_types = [];

		foreach ( $this->field_mapping->get_mapped_fields( $context->configuration_id, new FieldCollection( $available_fields ) ) as $field ) {
			$file_type = $field->options[ InvoiceFieldProvider::OPTION_INVOICE_FILE_TYPE ] ?? null;

			if ( is_string( $file_type ) && '' !== $file_type ) {
				$mapped_file_types[] = $file_type;
			}
		}

		return $mapped_file_types;
	}

	/**
	 * Logs a non-fatal invoice plugin attachment error.
	 *
	 * @param int       $export_id Export post ID.
	 * @param WC_Order  $order     WooCommerce order.
	 * @param string    $plugin_id Invoice plugin ID.
	 * @param string    $file_type Invoice file type, if known.
	 * @param Throwable $exception Plugin exception.
	 */
	private function log_invoice_plugin_warning( int $export_id, WC_Order $order, string $plugin_id, string $file_type, Throwable $exception ): void {
		if ( $export_id <= 0 ) {
			return;
		}

		ExportEventDispatcher::dispatch(
			ExportEvents::LOG_ENTRY,
			$export_id,
			'warning',
			'Invoice plugin error while retrieving an invoice file.',
			[
				'export_id'          => $export_id,
				'order_id'           => $order->get_id(),
				'invoice_plugin_id'  => $plugin_id,
				'invoice_file_type'  => $file_type,
				'exception_message'  => $exception->getMessage(),
				'attachment_skipped' => true,
			],
			$exception
		);
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
