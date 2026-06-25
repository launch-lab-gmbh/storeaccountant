# Invoice Plugins

Invoice plugin integrations describe invoice-capable WooCommerce extensions to
StoreAccountant. The invoice namespace owns plugin detection, invoice-specific
logic, and invoice field providers. The field providers then register into the
main export field hooks.

## Contract

```php
StoreAccountant\Invoice\Contract\InvoicePluginInterface
```

Methods:

- `get_id(): string`
- `is_active(): bool`
- `has_invoice(\WC_Order $order): bool`
- `get_invoice_number(\WC_Order $order): string`
- `get_invoice_date(\WC_Order $order): string`
- `get_invoice_file_types(): array`
- `get_invoice_file_name(\WC_Order $order, string $type): string`
- `get_invoice_file(\WC_Order $order, string $type): ?StorageFile`

## Registry

```php
StoreAccountant\Invoice\InvoicePluginRegistry
```

## Helper

```php
StoreAccountant\Invoice\InvoicePluginHelper
```

Invoice plugin integrations can reuse this service for common, plugin-agnostic
tasks: reading scalar fallback values from order meta, formatting plugin number
or date objects into strings, and normalizing invoice file names with expected
extensions.

## Hook

```php
storeaccountant_invoice_plugin
```

## Built-In Integration

The bundled WooCommerce PDF Invoices & Packing Slips integration uses the ID
`woocommerce-pdf-invoices-packing-slips`.

Its order export providers live below
`StoreAccountant\Invoice\Export\Order`:

- `InvoiceFieldProvider` contributes `invoice_number`, `invoice_date`, and one
  typed `invoice_file_name_{type}` field per invoice file type, such as
  `invoice_file_name_pdf` and `invoice_file_name_xml`, to the `orders` export
  type when an invoice plugin integration is enabled.
- `InvoiceFieldValueProvider` resolves those fields through the enabled invoice
  plugin integration.
- `InvoiceAttachmentProvider` adds selected invoice files to the export ZIP
  through `storeaccountant_export_attachment_provider`.

Administrators can enable one active invoice plugin integration on the plugin
settings page. If no invoice plugin integration is enabled, invoice fields are
not offered in export field mapping.

Export configurations can select one or more invoice file types to attach to
the export. The bundled WooCommerce PDF Invoices & Packing Slips integration
offers `pdf` and, when its EDI/XML feature is available, `xml`.
Attachment generation also requires the matching typed invoice file name field
to be enabled in field mapping, for example `invoice_file_name_pdf` for PDF
files or `invoice_file_name_xml` for XML files.

Saved export configurations may still contain invoice field mapping entries from
an earlier state. Those entries are intentionally ignored while the related
invoice plugin integration is unavailable or disabled, so exports do not fail
and do not emit stale invoice columns.

## Registration

```php
<?php

add_filter(
	'storeaccountant_invoice_plugin',
	static function ( array $plugins ) use ( $invoice_plugin ): array {
		$plugins[ $invoice_plugin->get_id() ] = $invoice_plugin;

		return $plugins;
	},
	110
);
```

Important: the registered value must be a concrete instance of
`InvoicePluginInterface`.

## Example Implementation

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Invoice\Contract\InvoicePluginInterface;
use StoreAccountant\Storage\StorageFile;

final class AcmeInvoicePlugin implements InvoicePluginInterface {
	public function get_id(): string {
		return 'acme-invoices';
	}

	public function is_active(): bool {
		return class_exists( \Acme_Invoices::class );
	}

	public function has_invoice( \WC_Order $order ): bool {
		return '' !== $this->get_invoice_number( $order );
	}

	public function get_invoice_number( \WC_Order $order ): string {
		return (string) $order->get_meta( '_acme_invoice_number' );
	}

	public function get_invoice_date( \WC_Order $order ): string {
		return (string) $order->get_meta( '_acme_invoice_date' );
	}

	public function get_invoice_file_types(): array {
		return [ 'pdf' ];
	}

	public function get_invoice_file_name( \WC_Order $order, string $type ): string {
		return 'invoice-' . $order->get_id() . '.' . $type;
	}

	public function get_invoice_file( \WC_Order $order, string $type ): ?StorageFile {
		// Return a StorageFile with a readable stream when the invoice exists.
		return null;
	}
}
```
