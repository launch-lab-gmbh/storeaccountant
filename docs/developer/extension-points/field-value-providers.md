# Field Value Providers

Field value providers resolve row values for fields that were defined by field
providers. Providers are registered globally and decide which export types and
fields they support through `supports()`. The export dataset builder prepares
the `FieldCollection` once and passes the supported subset, together with the
current source item, through all matching value providers. Providers run in hook
priority order; later providers can override values returned by earlier
providers by returning the same field ID.

## Contract

```php
StoreAccountant\Export\Contract\FieldValueProviderInterface
```

Methods:

- `get_id(): string`
- `supports(Field $field, ExportContext $context): bool`
- `get_values(mixed $item, FieldCollection $fields, ExportContext $context): array`

## Registry

```php
StoreAccountant\Export\Field\FieldValueProviderRegistry
```

## Hook

```php
storeaccountant_export_field_value_provider
```

## Registration

```php
add_filter(
	'storeaccountant_export_field_value_provider',
	static function ( array $providers ) use ( $value_provider ): array {
		$providers[ $value_provider->get_id() ] = $value_provider;

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
use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;

final class ExtraOrderFieldValueProvider implements FieldValueProviderInterface {
	public function get_id(): string {
		return 'extra_order_values';
	}

	public function supports( Field $field, ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type && 'my_custom_field' === $field->id;
	}

	public function get_values( mixed $item, FieldCollection $fields, ExportContext $context ): array {
		if ( ! $item instanceof \WC_Order || ! $fields->has( 'my_custom_field' ) ) {
			return [];
		}

		return [
			'my_custom_field' => new FieldValue( 'my_custom_field', 'Implement value resolving.' ),
		];
	}
}
```

Important: the registered value must be a concrete instance of
`FieldValueProviderInterface`.
