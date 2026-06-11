# Export Filters

Export filters describe how an export adapter limits its source data. A filter
is optional and belongs to a specific export type. Date ranges are therefore not
part of the generic export model; they are just one possible filter for export
types such as `orders` or future invoice exports.

## Contracts

```php
StoreAccountant\Export\Filter\Contract\ExportFilterInterface
StoreAccountant\Export\Filter\Contract\ExportFilterFieldProviderInterface
```

Query filters implement:

- `get_id(): string`
- `supports(string $export_type): bool`
- `apply(mixed $query, ExportFilterSelection $selection, ExportPayload $payload): true|\WP_Error`

Admin field providers implement:

- `get_id(): string`
- `supports(string $export_type): bool`
- `render(?ExportFilterSelection $selection = null, bool $read_only = false): void`
- `get_selection_from_request(array $request): ExportFilterSelection|\WP_Error`
- `get_default_selection(): ExportFilterSelection`

Field providers should avoid enhanced JavaScript widgets and render static or
disabled values when `$read_only` is `true`.

## Registries

```php
StoreAccountant\Export\Filter\ExportFilterRegistry
StoreAccountant\Export\Filter\ExportFilterFieldProviderRegistry
```

## Hooks

```php
storeaccountant_export_filter
storeaccountant_export_filter_field_provider
storeaccountant_export_filter_period_provider
```

Use `storeaccountant_export_filter` for runtime filters that mutate the
export-type query object. Use `storeaccountant_export_filter_field_provider` for
admin fields that render and validate the stored filter selection. Use
`storeaccountant_export_filter_period_provider` for reusable period resolvers
that can be used inside date-like filters.

Free registers core providers with priority `100`.

## Stored Selections

Export configurations and generated exports store filter selections as JSON in:

```text
_storeaccountant_config_filters
_storeaccountant_filters
```

Each item contains the filter ID and settings:

```json
[
  {
    "filter_id": "order_date",
    "settings": {
      "date_field": "date_created",
      "period_provider": "month-year",
      "period": {
        "provider": "month-year",
        "month": "last_month"
      }
    }
  }
]
```

Generated exports snapshot dynamic period values into the filter settings so
historical export records stay stable:

```json
{
  "filter_id": "order_date",
  "settings": {
    "date_field": "date_created",
    "period_provider": "month-year",
    "period": {
      "provider": "month-year",
      "month": "last_month"
    },
    "resolved_period": {
      "start_at": "2026-04-01 00:00:00",
      "end_at": "2026-04-30 21:59:59"
    }
  }
}
```

## Built-In Order Filters

The built-in order export currently registers:

- `order_date`: applies a period to a selectable WooCommerce order date query
  field.
- `order_status`: applies selected WooCommerce order statuses.

The order date filter supports these WooCommerce query fields:

- `date_created`
- `date_modified`
- `date_completed`
- `date_paid`

These map to WooCommerce order query arguments and are applied to
`WC_Order_Query`. Do not use direct SQL for standard order filtering; this keeps
filters aligned with WooCommerce storage changes such as HPOS.

## Registration

```php
add_filter(
	'storeaccountant_export_filter',
	static function ( array $filters ) use ( $filter ): array {
		$filters[ $filter->get_id() ] = $filter;

		return $filters;
	},
	110
);

add_filter(
	'storeaccountant_export_filter_field_provider',
	static function ( array $providers ) use ( $provider ): array {
		$providers[ $provider->get_id() ] = $provider;

		return $providers;
	},
	110
);
```

## Example Runtime Filter

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use WC_Order_Query;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\Contract\ExportFilterInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;

final class PaymentMethodFilter implements ExportFilterInterface {
	public function get_id(): string {
		return 'payment_method';
	}

	public function supports( string $export_type ): bool {
		return OrderExportAdapter::ADAPTER_ID === $export_type;
	}

	public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true|\WP_Error {
		if ( ! $query instanceof WC_Order_Query ) {
			return new \WP_Error( 'invalid_query', 'Invalid query.' );
		}

		$query->set( 'payment_method', sanitize_key( $selection->settings['method'] ?? '' ) );

		return true;
	}
}
```

The matching field provider should use the same ID and return an
`ExportFilterSelection` with the settings expected by the runtime filter.

## Example Field Provider

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Export\Filter\Contract\ExportFilterFieldProviderInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;

final class PaymentMethodFilterFieldProvider implements ExportFilterFieldProviderInterface {
	public function get_id(): string {
		return 'payment_method';
	}

	public function supports( string $export_type ): bool {
		return OrderExportAdapter::ADAPTER_ID === $export_type;
	}

	public function render( ?ExportFilterSelection $selection = null, bool $read_only = false ): void {
		$value = isset( $selection->settings['method'] ) ? sanitize_key( $selection->settings['method'] ) : '';

		echo '<input type="text" name="payment_method" value="' . esc_attr( $value ) . '"' . disabled( $read_only, true, false ) . ' />';
	}

	public function get_selection_from_request( array $request ): ExportFilterSelection|\WP_Error {
		return new ExportFilterSelection(
			$this->get_id(),
			[
				'method' => isset( $request['payment_method'] ) ? sanitize_key( wp_unslash( $request['payment_method'] ) ) : '',
			]
		);
	}

	public function get_default_selection(): ExportFilterSelection {
		return new ExportFilterSelection( $this->get_id(), [ 'method' => '' ] );
	}
}
```
