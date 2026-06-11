# Order Tax Field Providers

Order tax field providers define selectable tax field strategies for WooCommerce
order exports. They are registered through the
`storeaccountant_export_order_tax_field_provider` filter and also register as
regular export field providers through `storeaccountant_export_field_provider`.
This keeps tax fields in the shared field pipeline while still allowing an order
configuration to choose one tax strategy.

The provider must implement
`StoreAccountant\Tax\Contract\OrderTaxFieldProviderInterface`. Tax field
providers that add export columns should also implement
`StoreAccountant\Export\Contract\FieldProviderInterface` and only support the
order export when their provider ID matches the selected configuration value in
`ExportContext`.

## Hook

```php
storeaccountant_export_order_tax_field_provider
```

## Registration

```php
add_filter(
	'storeaccountant_export_order_tax_field_provider',
	static function ( array $providers ): array {
		$providers['my_tax_provider'] = new MyTaxFieldProvider();

		return $providers;
	}
);
```

Each provider exposes a stable ID, a translated label, field definitions, and
values for a `WC_Order`. The field mapping UI stores the selected tax provider
on the export configuration. `OrderTaxFieldValueProvider` resolves values for
the selected tax provider through the normal field value provider pipeline.

Built-in tax field providers register their field definitions after the normal
order fields. Metadata-backed custom order fields are moved to the end by the
field resolver and mapping repository, so the final order is fixed order fields,
tax fields, then custom fields.

Top-level reusable tax classes:

```text
StoreAccountant\Tax\Contract\OrderTaxFieldProviderInterface
StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider
StoreAccountant\Tax\Field\Provider\SimpleOrderTaxFieldProvider
StoreAccountant\Tax\Field\Provider\OrderTaxFieldValueProvider
StoreAccountant\Tax\Admin\OrderTaxFieldProviderField
```

Order-specific tax support classes:

```text
StoreAccountant\Order\Tax\OrderTaxFieldProviderRegistry
StoreAccountant\Order\Tax\OrderTaxRateResolver
```

Built-in providers:

- `extended`: creates separate item, shipping, and total tax fields per tax rate.
- `simple`: aggregates order tax into item tax and shipping tax columns.

## Example Implementation

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use WC_Order;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\FieldProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Tax\Contract\OrderTaxFieldProviderInterface;

final class CompactTaxFieldProvider implements OrderTaxFieldProviderInterface, FieldProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'compact';

	public function register(): void {
		add_filter(
			'storeaccountant_export_order_tax_field_provider',
			function ( array $providers ): array {
				$providers[ self::PROVIDER_ID ] = $this;

				return $providers;
			}
		);

		add_filter(
			'storeaccountant_export_field_provider',
			function ( array $providers ): array {
				$providers[ 'order_tax_' . self::PROVIDER_ID ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY + 10
		);
	}

	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	public function get_label(): string {
		return __( 'Compact tax fields', 'storeaccountant' );
	}

	public function supports( ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type
			&& self::PROVIDER_ID === (string) $context->get( 'tax_field_provider_id', '' );
	}

	public function get_fields( ExportContext $context ): array {
		return [
			'tax_total' => new Field( 'tax_total', 'tax_total', new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ) ),
		];
	}

	public function get_values( WC_Order $order, ExportContext $context ): array {
		return [
			'tax_total' => new FieldValue( 'tax_total', (string) $order->get_total_tax() ),
		];
	}
}
```

Providers that need WooCommerce tax-rate keys can inject
`StoreAccountant\Order\Tax\OrderTaxRateResolver`. The built-in extended provider
uses it to combine configured WooCommerce tax rates with tax rates discovered
from the exported orders.
