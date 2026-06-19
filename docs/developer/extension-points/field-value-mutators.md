# Field Value Mutators

Field value mutators can change resolved values before an export dataset is
written. They run after field value providers. Each mutator receives the
field definition and decides through `supports()` whether it should handle that
field.

Mutators are chain-style extension points. If multiple mutators support the same
field, they run in registry order and each mutator receives the `FieldValue`
returned by the previous mutator.

The `settings` argument contains the saved field mapping options for the current
field. The `ExportContext` argument carries typed runtime data prepared by the
current export adapter, such as `$context->export_type`,
`$context->configuration_id`, and adapter values via
`$context->get( 'tax_rates', [] )`.

## Contract

```php
StoreAccountant\Export\Contract\FieldValueMutatorInterface
```

Methods:

- `get_id(): string`
- `supports(Field $field, ExportContext $context): bool`
- `mutate(FieldValue $value, Field $field, array $settings, ExportContext $context): FieldValue`

## Registry

```php
StoreAccountant\Export\Field\Mutator\FieldValueMutatorRegistry
```

## Hook

```php
storeaccountant_export_field_value_mutator
```

The hook is intentionally field-oriented and export-type independent. Export
adapters, such as the order adapter, run this registry for their mapped fields.
The `supports()` method decides field-by-field whether a mutator applies.

## Registration

```php
<?php

add_filter(
	'storeaccountant_export_field_value_mutator',
	static function ( array $mutators ) use ( $mutator ): array {
		$mutators[ $mutator->get_id() ] = $mutator;

		return $mutators;
	},
	110
);
```

## Example Implementation

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Export\Contract\FieldValueMutatorInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldValue;

final class DateFormatMutator implements FieldValueMutatorInterface {
	public function get_id(): string {
		return 'date_format';
	}

	public function supports( Field $field, ExportContext $context ): bool {
		return $field->type instanceof \StoreAccountant\Export\Field\Type\DateTimeFieldType;
	}

	public function mutate( FieldValue $value, Field $field, array $settings, ExportContext $context ): FieldValue {
		return $value;
	}
}
```

Important: the registered value must be a concrete instance of
`FieldValueMutatorInterface`.

## Built-in Amount Format Mutator

Decimal number fields are handled by the built-in amount format mutator:

```php
StoreAccountant\Export\Field\Mutator\AmountMutator
```

It supports `NumberFieldType` fields using the `decimal` format and reads the
`amount_format` field mapping option. Supported values:

- `amount`: keep the normalized full amount, for example `12.34`.
- `cents`: export minor units, for example `1234`.

## Built-in Date Mutator

Date and datetime fields are handled by the built-in date mutator:

```php
StoreAccountant\Export\Field\Mutator\DateMutator
```

It supports `DateTimeFieldType` fields and reads the `date_format` field mapping
option. Supported values include:

- `original`: keep the provider value unchanged.
- `date_iso`: export `Y-m-d`, for example `2026-05-24`.
- `date_german`: export `d.m.Y`, for example `24.05.2026`.
- `date_slash`: export `m/d/Y`, for example `05/24/2026`.
- `date_compact`: export `Ymd`, for example `20260524`.
- `datetime_iso`: export `Y-m-d H:i:s`, for example `2026-05-24 14:30:00`.
- `datetime_german`: export `d.m.Y H:i`, for example `24.05.2026 14:30`.
- `datetime_german_seconds`: export `d.m.Y H:i:s`, for example `24.05.2026 14:30:00`.
- `datetime_local`: export `Y-m-d\TH:i`, for example `2026-05-24T14:30`.
- `datetime_rfc3339`: export RFC 3339, for example `2026-05-24T14:30:00+00:00`.
- `timestamp`: export a Unix timestamp.

If parsing or formatting fails, the original field value is kept unchanged.
