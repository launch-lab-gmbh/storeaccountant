# Export Adapters

Export adapters are selected in the export forms. They collect domain data and
provide adapter-specific context and record IDs to the shared dataset builder.
A dataset defines fields once, including stable IDs, labels, types, and optional
metadata.
Source filtering is carried by `ExportPayload` as filter selections. Export
adapters decide whether and how those filters apply to their source query.
Each record carries values keyed by field ID. Renderers can therefore serialize
the same dataset to CSV, XML, XLSX, or another format without knowing whether it
contains orders, customers, or another entity type.

## Contract

```php
<?php

StoreAccountant\Export\Contract\ExportAdapterInterface
```

Methods:

- `get_id(): string`
- `get_items(ExportPayload $payload): iterable|\WP_Error`
- `get_context(ExportPayload $payload, iterable $items): ExportContext`
- `get_additional_fields(ExportPayload $payload, ExportContext $context): FieldCollection`
- `get_additional_values(mixed $item, ExportPayload $payload, ExportContext $context): array`
- `get_record_id(mixed $item): string`

`ExportContext` carries the export type, the related configuration ID, the
current source items, and additional adapter values. Field and value providers
can read adapter values with helpers such as `$context->get( 'tax_rates', [] )`.
`get_additional_fields()` and `get_additional_values()` exist for rare
adapter-owned fields, but normal extensible data should be implemented through
`FieldProviderInterface` and `FieldValueProviderInterface`.

## Registry

```php
<?php

StoreAccountant\Export\ExportAdapterRegistry
```

## Hook

```php
<?php

storeaccountant_export_adapter
```

If multiple export adapters register the same ID, the later registered adapter
wins after priority resolution.

## Registration

```php
<?php

add_filter(
	'storeaccountant_export_adapter',
	static function ( array $adapters ) use ( $customer_adapter ): array {
		$adapters[ $customer_adapter->get_id() ] = $customer_adapter;

		return $adapters;
	},
	110
);
```

## Example Implementation

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\FieldCollection;

final class CustomerAdapter implements ExportAdapterInterface {
	public function get_id(): string {
		return 'customers';
	}

	public function get_items( ExportPayload $payload ): iterable|\WP_Error {
		// Return customer source items, optionally using $payload->filters.
	}

	public function get_context( ExportPayload $payload, iterable $items ): ExportContext {
		return new ExportContext( $this->get_id(), 0, is_array( $items ) ? $items : [] );
	}

	public function get_additional_fields( ExportPayload $payload, ExportContext $context ): FieldCollection {
		return new FieldCollection();
	}

	public function get_additional_values( mixed $item, ExportPayload $payload, ExportContext $context ): array {
		return [];
	}

	public function get_record_id( mixed $item ): string {
		return '';
	}
}
```

Important: the registered value must be a concrete instance of
`ExportAdapterInterface`, not only a label string.
