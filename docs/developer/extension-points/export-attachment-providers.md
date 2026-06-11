# Export Attachment Providers

Export attachment providers add additional files to a generated export archive.
They are independent from export renderers: CSV, XML, XLSX, and future renderers
still write only the primary dataset file, while storage adapters receive
attachments through the storage file configuration.

## Contract

```php
StoreAccountant\Export\Contract\ExportAttachmentProviderInterface
```

Methods:

- `get_id(): string`
- `supports(ExportContext $context): bool`
- `get_directory(ExportContext $context): string`
- `get_attachments(mixed $item, ExportPayload $payload, ExportContext $context): iterable`

Attachment providers can use `ExportContext` for typed basics such as
`$context->export_type` and `$context->configuration_id`, plus adapter-specific
values through `$context->get( 'key', $default )`.

## Registry

```php
StoreAccountant\Export\Attachment\ExportAttachmentProviderRegistry
```

## Hook

```php
storeaccountant_export_attachment_provider
```

Providers run while the export dataset builder still has access to the source
item, for example a `WC_Order`. Returned `ExportAttachment` objects are stored
with the generated export file. The bundled local storage adapter writes them as
additional entries inside the same ZIP archive.

Each provider declares a base directory for its attachments. Concrete
attachments may add additional subdirectories below that base directory, for
example to group different invoice file types.

## Registration

```php
add_filter(
	'storeaccountant_export_attachment_provider',
	static function ( array $providers ) use ( $attachment_provider ): array {
		$providers[ $attachment_provider->get_id() ] = $attachment_provider;

		return $providers;
	},
	110
);
```

## Example Implementation

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Export\Attachment\ExportAttachment;
use StoreAccountant\Export\Contract\ExportAttachmentProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;

final class OrderPdfAttachmentProvider implements ExportAttachmentProviderInterface {
	public function get_id(): string {
		return 'acme_order_pdf';
	}

	public function supports( ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type;
	}

	public function get_directory( ExportContext $context ): string {
		return 'documents';
	}

	public function get_attachments( mixed $item, ExportPayload $payload, ExportContext $context ): iterable {
		if ( ! $item instanceof \WC_Order ) {
			return [];
		}

		$stream = fopen( '/path/to/generated.pdf', 'rb' );

		if ( false === $stream ) {
			return [];
		}

		yield new ExportAttachment(
			$stream,
			'order-' . $item->get_id() . '.pdf',
			'application/pdf',
			'invoices/order-' . $item->get_id() . '.pdf'
		);
	}
}
```

Important: the registered value must be a concrete instance of
`ExportAttachmentProviderInterface`.
