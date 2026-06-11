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

namespace StoreAccountant\Invoice\Plugin;

use Throwable;
use WC_Order;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Contract\WordPress\WordPressFilesystem;
use StoreAccountant\Invoice\Contract\InvoicePluginInterface;
use StoreAccountant\Invoice\InvoiceFileType;
use StoreAccountant\Invoice\InvoicePluginHelper;
use StoreAccountant\Storage\StorageFile;
use function add_filter;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Storage attachments are passed as PHP stream resources.
use function fclose;
use function function_exists;
use function in_array;
use function is_array;
use function is_object;
use function is_readable;
use function is_resource;
use function is_scalar;
use function method_exists;
use function rewind;
use function trim;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- php://temp streams are used for generated invoice attachments.
use function fwrite;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://temp streams are used for generated invoice attachments.
use function fopen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates WooCommerce PDF Invoices & Packing Slips.
 */
final readonly class WooCommercePdfInvoicesPackingSlipsPlugin implements InvoicePluginInterface, HookRegistrarInterface {
	public const PLUGIN_ID     = 'woocommerce-pdf-invoices-packing-slips';
	public const FILE_TYPE_PDF = 'pdf';
	public const FILE_TYPE_XML = 'xml';

	/**
	 * Initializes the invoice plugin integration.
	 *
	 * @param InvoicePluginHelper $helper Invoice plugin helper.
	 */
	public function __construct(
		private InvoicePluginHelper $helper
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_invoice_plugin',
			function ( array $plugins ): array {
				$plugins[ self::PLUGIN_ID ] = $this;

				return $plugins;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::PLUGIN_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active(): bool {
		return function_exists( 'wcpdf_get_document' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_invoice_number( WC_Order $order ): string {
		$invoice_number = $this->get_invoice_number_from_plugin_api( $order );

		if ( '' !== $invoice_number ) {
			return $invoice_number;
		}

		return $this->get_invoice_number_from_order_meta( $order );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_invoice_date( WC_Order $order ): string {
		$invoice_date = $this->get_invoice_date_from_plugin_api( $order );

		if ( '' !== $invoice_date ) {
			return $invoice_date;
		}

		return $this->get_invoice_date_from_order_meta( $order );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_invoice_file_types(): array {
		$file_types = [
			new InvoiceFileType( self::FILE_TYPE_PDF, __( 'PDF', 'storeaccountant' ) ),
		];

		if ( $this->is_xml_invoice_available() ) {
			$file_types[] = new InvoiceFileType( self::FILE_TYPE_XML, __( 'E-Invoice (XML)', 'storeaccountant' ) );
		}

		return $file_types;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_invoice_file_name( WC_Order $order, string $type ): string {
		if ( ! in_array( $type, [ self::FILE_TYPE_PDF, self::FILE_TYPE_XML ], true ) ) {
			return '';
		}

		$invoice = $this->get_invoice_document( $order );

		if ( is_object( $invoice ) && method_exists( $invoice, 'get_filename' ) ) {
			try {
				$file_name = self::FILE_TYPE_XML === $type
					? $invoice->get_filename( 'download', [ 'output' => self::FILE_TYPE_XML ] )
					: $invoice->get_filename();
			} catch ( Throwable ) {
				$file_name = '';
			}

			if ( is_scalar( $file_name ) && '' !== trim( (string) $file_name ) ) {
				return self::FILE_TYPE_XML === $type
					? $this->helper->ensure_file_extension( (string) $file_name, self::FILE_TYPE_XML )
					: $this->helper->ensure_file_extension( (string) $file_name, self::FILE_TYPE_PDF );
			}
		}

		$invoice_number = $this->get_invoice_number( $order );
		$file_name      = '' !== $invoice_number ? 'invoice-' . $invoice_number : 'invoice-' . (string) $order->get_id();

		return self::FILE_TYPE_XML === $type
			? $this->helper->ensure_file_extension( $file_name, self::FILE_TYPE_XML )
			: $this->helper->ensure_file_extension( $file_name, self::FILE_TYPE_PDF );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_invoice_file( WC_Order $order, string $type ): ?StorageFile {
		if ( ! in_array( $type, [ self::FILE_TYPE_PDF, self::FILE_TYPE_XML ], true ) ) {
			return null;
		}

		$invoice = $this->get_invoice_document( $order );

		if ( null === $invoice ) {
			return null;
		}

		$content = self::FILE_TYPE_XML === $type
			? $this->get_xml_content( $invoice )
			: $this->get_pdf_content( $invoice );

		if ( '' === $content ) {
			return null;
		}

		$stream = $this->open_temporary_stream();

		if ( false === $stream ) {
			return null;
		}

		if ( false === $this->write_stream( $stream, $content ) ) {
			$this->close_stream( $stream );

			return null;
		}

		rewind( $stream );

		return new StorageFile(
			$stream,
			$this->get_invoice_file_name( $order, $type ),
			self::FILE_TYPE_XML === $type ? 'application/xml' : 'application/pdf'
		);
	}

	/**
	 * Opens an in-memory temporary stream for generated invoice content.
	 *
	 * @return resource|false
	 */
	private function open_temporary_stream() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://temp is required to hand generated content to storage as a stream.
		return fopen( 'php://temp', 'rb+' );
	}

	/**
	 * Writes generated invoice content to a stream.
	 *
	 * @param resource $stream  Target stream.
	 * @param string   $content Generated content.
	 */
	private function write_stream( $stream, string $content ): int|false {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- php://temp is required to hand generated content to storage as a stream.
		return fwrite( $stream, $content );
	}

	/**
	 * Closes a PHP stream resource.
	 *
	 * @param mixed $stream Potential stream resource.
	 */
	private function close_stream( mixed $stream ): void {
		if ( is_resource( $stream ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://temp is required to hand generated content to storage as a stream.
			fclose( $stream );
		}
	}

	/**
	 * Gets the invoice number through the plugin API without creating invoices.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function get_invoice_number_from_plugin_api( WC_Order $order ): string {
		$invoice = $this->get_invoice_document( $order );

		if ( ! is_object( $invoice ) || ! method_exists( $invoice, 'get_number' ) ) {
			return '';
		}

		try {
			$number = $invoice->get_number();
		} catch ( Throwable ) {
			return '';
		}

		return $this->helper->format_invoice_number_value( $number );
	}

	/**
	 * Gets the invoice document through the plugin API without creating invoices.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function get_invoice_document( WC_Order $order ): ?object {
		if ( ! $this->is_active() ) {
			return null;
		}

		try {
			$invoice = wcpdf_get_document( 'invoice', $order, false );
		} catch ( Throwable ) {
			return null;
		}

		return is_object( $invoice ) ? $invoice : null;
	}

	/**
	 * Gets the invoice date through the plugin API.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function get_invoice_date_from_plugin_api( WC_Order $order ): string {
		$invoice = $this->get_invoice_document( $order );

		if ( ! is_object( $invoice ) || ! method_exists( $invoice, 'get_date' ) ) {
			return '';
		}

		try {
			$date = $invoice->get_date();
		} catch ( Throwable ) {
			return '';
		}

		return $this->helper->format_invoice_date_value( $date );
	}

	/**
	 * Gets the invoice number directly from stored order metadata.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function get_invoice_number_from_order_meta( WC_Order $order ): string {
		$number = $this->helper->get_first_scalar_array_meta_value( $order, '_wcpdf_invoice_number_data', [ 'formatted_number' ] );

		if ( '' !== $number ) {
			return $number;
		}

		return $this->helper->get_first_scalar_meta(
			$order,
			[
				'_wcpdf_formatted_invoice_number',
				'_wcpdf_invoice_number',
			]
		);
	}

	/**
	 * Gets the invoice date directly from stored order metadata.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function get_invoice_date_from_order_meta( WC_Order $order ): string {
		$date = $this->helper->get_first_scalar_array_meta_value( $order, '_wcpdf_invoice_number_data', [ 'date', 'formatted_date' ] );

		if ( '' !== $date ) {
			return $date;
		}

		return $this->helper->get_first_scalar_meta(
			$order,
			[
				'_wcpdf_invoice_date',
				'_wcpdf_invoice_date_formatted',
			]
		);
	}

	/**
	 * Gets PDF content from an invoice document.
	 *
	 * @param object $invoice Invoice document.
	 */
	private function get_pdf_content( object $invoice ): string {
		if ( method_exists( $invoice, 'get_pdf' ) ) {
			try {
				$pdf = $invoice->get_pdf();
			} catch ( Throwable ) {
				$pdf = '';
			}

			if ( is_scalar( $pdf ) && '' !== (string) $pdf ) {
				return (string) $pdf;
			}
		}

		foreach ( [ 'get_pdf_path', 'get_file_path' ] as $method ) {
			if ( ! method_exists( $invoice, $method ) ) {
				continue;
			}

			try {
				$path = $invoice->{$method}();
			} catch ( Throwable ) {
				$path = '';
			}

			if ( is_scalar( $path ) && is_readable( (string) $path ) ) {
				$pdf = WordPressFilesystem::get_contents( (string) $path );

				return false === $pdf ? '' : $pdf;
			}
		}

		return '';
	}

	/**
	 * Gets XML e-invoice content from an invoice document.
	 *
	 * @param object $invoice Invoice document.
	 */
	private function get_xml_content( object $invoice ): string {
		if ( ! $this->is_xml_invoice_available() ) {
			return '';
		}

		try {
			$path = wcpdf_get_document_file( $invoice, self::FILE_TYPE_XML );
		} catch ( Throwable ) {
			return '';
		}

		if ( ! is_scalar( $path ) || ! is_readable( (string) $path ) ) {
			return '';
		}

		$xml = WordPressFilesystem::get_contents( (string) $path );

		return false === $xml ? '' : $xml;
	}

	/**
	 * Checks whether XML e-invoice files can be generated.
	 */
	private function is_xml_invoice_available(): bool {
		if ( ! function_exists( 'WPO_WCPDF' ) || ! function_exists( 'wcpdf_get_document_file' ) || ! function_exists( 'wpo_ips_edi_is_available' ) ) {
			return false;
		}

		try {
			if ( ! wpo_ips_edi_is_available() || ! is_object( WPO_WCPDF()->documents ) || ! method_exists( WPO_WCPDF()->documents, 'get_documents' ) ) {
				return false;
			}

			$documents = WPO_WCPDF()->documents->get_documents( 'enabled', self::FILE_TYPE_XML );
		} catch ( Throwable ) {
			return false;
		}

		if ( ! is_array( $documents ) ) {
			return false;
		}

		foreach ( $documents as $document ) {
			if ( is_object( $document ) && method_exists( $document, 'get_type' ) && 'invoice' === $document->get_type() ) {
				return true;
			}
		}

		return false;
	}
}
