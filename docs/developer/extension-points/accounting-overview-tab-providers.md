# Accounting Overview Tab Providers

Accounting overview tab providers add tabs to the main StoreAccountant
Accounting area, next to the built-in `Exports`, `Export Configurations`, and
`Support` tabs.

The built-in tab priorities are:

- `10` for `Exports`.
- `20` for `Export Configurations`.
- `900` for `Support`.

Add-ons that should appear between `Export Configurations` and `Support` can use
priorities between `21` and `899`.

## Contract

```php
StoreAccountant\Admin\Contract\AccountingOverviewTabProviderInterface
```

Methods:

- `get_id(): string`
- `get_label(): string`
- `get_url(): string`
- `is_visible(): bool`
- `get_priority(): int`

The header renders only providers where `is_visible()` returns `true`. Providers
should check the relevant capability before exposing a tab URL.

## Registry

```php
StoreAccountant\Admin\AccountingOverviewTabProviderRegistry
```

## Hook

```php
storeaccountant_accounting_overview_tab_provider
```

Provider ordering is determined by each provider's `get_priority()` return
value. If two providers use the same priority, StoreAccountant sorts by tab ID
as a deterministic fallback.

## Registration

```php
<?php

add_filter(
	'storeaccountant_accounting_overview_tab_provider',
	static function ( array $providers ) use ( $tab_provider ): array {
		$providers[ $tab_provider->get_id() ] = $tab_provider;

		return $providers;
	},
	100
);
```

Important: the registered value must be a concrete instance of
`AccountingOverviewTabProviderInterface`.
