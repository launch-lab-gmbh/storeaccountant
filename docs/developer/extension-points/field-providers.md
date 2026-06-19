# Field Providers

Field providers define which fields exist for export datasets. They do not
resolve row values. Values are resolved separately by field value providers.
Field providers are registered globally and decide which export types they
support through `supports()`.

Each `Field` has a semantic type implementing
`StoreAccountant\Export\Field\FieldTypeInterface`. StoreAccountant ships common
types below `StoreAccountant\Export\Field\Type`, including `StringFieldType`,
`NumberFieldType`, `DateTimeFieldType`, and `BooleanFieldType`. The `Field`
constructor still accepts legacy string identifiers such as `string`,
`integer`, `decimal`, `date`, and `datetime`, but new code should pass a field
type object.

## Contract

```php
StoreAccountant\Export\Contract\FieldProviderInterface
```

Methods:

- `get_id(): string`
- `supports(ExportContext $context): bool`
- `get_fields(ExportContext $context): array`

## Registry

```php
StoreAccountant\Export\Field\FieldProviderRegistry
```

## Hook

```php
storeaccountant_export_field_provider
```

The export field resolver asks this registry for the current export type's
`FieldCollection`.
Providers run in hook priority order. If two providers define the same field ID,
the later provider can override the earlier field definition.

Metadata-backed custom fields use shared helpers below
`StoreAccountant\Export\Field\Meta`. The built-in order and customer meta field
providers keep export-type-specific field ID prefixes and reserved key lists, but
share metadata collection, stable ID creation, and value formatting. Any field
with the shared `meta_key` field option is treated as a custom metadata field by
the resolver and is moved to the end of the field collection before saved field
mappings are applied. This keeps custom fields last; for `orders`, selectable
tax fields therefore remain before custom order fields.

The built-in export adapter IDs are:

```text
orders
customers
```

## Registration

```php
<?php

add_filter(
	'storeaccountant_export_field_provider',
	static function ( array $providers ) use ( $field_provider ): array {
		$providers[ $field_provider->get_id() ] = $field_provider;

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

use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Contract\FieldProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;

final class ExtraOrderFieldProvider implements FieldProviderInterface {
	public function get_id(): string {
		return 'extra_order_fields';
	}

	public function supports( ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type;
	}

	public function get_fields( ExportContext $context ): array {
		return [
			'my_custom_field' => new Field( 'my_custom_field', 'my_custom_field' ),
			'my_date_field'   => new Field( 'my_date_field', 'my_date_field', new DateTimeFieldType() ),
		];
	}
}
```

Important: the registered value must be a concrete instance of
`FieldProviderInterface`.
