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

Every export adapter must implement this base contract. It describes how source
items become normalized export records and is shared by all export execution
variants.

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

## Supported Adapter Variants

StoreAccountant supports three adapter loading variants. Adapters can implement
the lowest contract that fits their use case, but large or mutable sources
should use the snapshot variant.

### Basic Adapter

```php
<?php

StoreAccountant\Export\Contract\ExportAdapterInterface
```

This is the base variant. `get_items()` returns all source items for the payload
in one iterable. It is useful for small or custom sources where the caller can
process the complete source set in one pass.

Queued manual exports use background batch processing. If an adapter should be
usable for those exports, it should also implement `BatchExportAdapterInterface`
or `SnapshotExportAdapterInterface`.

### Batch Adapter

```php
<?php

StoreAccountant\Export\Contract\BatchExportAdapterInterface
```

This variant adds offset-based batch access:

- `count_items(ExportPayload $payload): int|\WP_Error`
- `get_batch_items(ExportPayload $payload, int $offset, int $limit): iterable|\WP_Error`

The export queue calls `count_items()` at the start, calculates the number of
batches from the configured batch size, and then calls `get_batch_items()` for
each offset and limit.

Use this variant only when the source set is stable while the export is running.
If new items can be created, deleted, or moved into the selected filters during
the export, offset-based loading can duplicate or skip rows because later
batches query a changed source set.

### Snapshot Batch Adapter

```php
<?php

StoreAccountant\Export\Contract\SnapshotExportAdapterInterface
```

This is the preferred variant for large WooCommerce-like datasets. It extends
`BatchExportAdapterInterface` and adds stable ID snapshot access:

- `get_item_ids(ExportPayload $payload): array|\WP_Error`
- `get_items_by_ids(ExportPayload $payload, array $item_ids): iterable|\WP_Error`

At export start, the queue calls `get_item_ids()` once and stores the resulting
IDs as the export source snapshot. The snapshot is normalized and de-duplicated
by the exporter. Batch count and progress are calculated from that stored ID
list. Each batch then loads one ID slice from the snapshot and calls
`get_items_by_ids()`.

With this variant, items created after the export was triggered are not included
in that export run. This also avoids offset drift when the source query changes
between batches.

`count_items()` and `get_batch_items()` are still part of the inherited batch
contract. They should remain implemented as a fallback, but the queued exporter
uses the snapshot methods whenever a snapshot is available.

## Choosing A Variant

| Source behavior | Recommended contract |
| --- | --- |
| Small static source, one-shot export only | `ExportAdapterInterface` |
| Stable source where offset/limit cannot drift | `BatchExportAdapterInterface` |
| WooCommerce orders, customers, products, or any changing source | `SnapshotExportAdapterInterface` |

For most production adapters, especially anything backed by orders or documents,
prefer `SnapshotExportAdapterInterface`.

## Snapshot Rules

Snapshot IDs should be:

- Filtered exactly like the exported source items.
- Returned in a deterministic order, usually ascending primary key.
- Scalar values, normally integer IDs or string IDs.
- Unique when possible. The exporter also de-duplicates defensively.

`get_items_by_ids()` should:

- Load only the IDs it receives.
- Preserve the input order where the underlying API allows it.
- Omit missing or inaccessible items rather than replacing them with placeholder
  records.
- Use WooCommerce and WordPress APIs instead of direct table access.

The snapshot freezes the membership of an export, not necessarily every field
value. If an existing item changes while the export is running, the batch loads
the current item state when it processes that ID.

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

## Snapshot Example

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Export\Contract\SnapshotExportAdapterInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\FieldCollection;

final class OrderAdapter implements SnapshotExportAdapterInterface {
	public function get_id(): string {
		return 'acme_orders';
	}

	public function get_items( ExportPayload $payload ): iterable|\WP_Error {
		$ids = $this->get_item_ids( $payload );

		return is_wp_error( $ids ) ? $ids : $this->get_items_by_ids( $payload, $ids );
	}

	public function count_items( ExportPayload $payload ): int|\WP_Error {
		$ids = $this->get_item_ids( $payload );

		return is_wp_error( $ids ) ? $ids : count( $ids );
	}

	public function get_batch_items( ExportPayload $payload, int $offset, int $limit ): iterable|\WP_Error {
		$ids = $this->get_item_ids( $payload );

		return is_wp_error( $ids )
			? $ids
			: $this->get_items_by_ids( $payload, array_slice( $ids, max( 0, $offset ), max( 0, $limit ) ) );
	}

	public function get_item_ids( ExportPayload $payload ): array|\WP_Error {
		// Apply the same payload filters as the final export query and return IDs
		// in deterministic order.
		return [ 1001, 1002, 1003 ];
	}

	public function get_items_by_ids( ExportPayload $payload, array $item_ids ): iterable|\WP_Error {
		// Load the concrete source items for this stored snapshot slice.
		return array_filter(
			array_map(
				static fn ( int|string $id ): mixed => function_exists( 'wc_get_order' ) ? wc_get_order( (int) $id ) : null,
				$item_ids
			)
		);
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
		return is_object( $item ) && method_exists( $item, 'get_id' ) ? (string) $item->get_id() : '';
	}
}
```
