# Legacy Period Providers

This document describes the legacy single-provider period UI. New export source
filtering uses [Export Filters](export-filters.md), where period providers are
reusable resolvers below the filter namespace and date selection is optional per
export type.

The current free month/year admin field is still reused by the built-in order
date filter, but generated exports no longer require a generic export period.

## Contracts

```php
StoreAccountant\Export\Admin\Period\Contract\ExportPeriodFieldProviderInterface
StoreAccountant\Export\Admin\Period\Contract\ExportPeriodViewProviderInterface
```

## Resolver

```php
StoreAccountant\Export\Admin\Period\ExportPeriodFieldProviderResolver
```

## Hooks

```php
storeaccountant_export_period_field_provider
storeaccountant_export_period_view_provider
```

Free registers itself on both period hooks with priority `100`. Premium
providers can register with a higher priority so the latest valid provider wins
without adding a separate provider selection UI.

## Registration

```php
add_filter(
	'storeaccountant_export_period_field_provider',
	static fn (): PremiumCalendarPeriodFieldProvider => $premium_provider,
	110
);

add_filter(
	'storeaccountant_export_period_view_provider',
	static fn (): PremiumCalendarPeriodViewProvider => $premium_view_provider,
	110
);
```

## Example Implementation

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Export\Admin\Period\Contract\ExportPeriodFieldProviderInterface;
use StoreAccountant\Export\Admin\Period\Contract\ExportPeriodViewProviderInterface;
use StoreAccountant\Export\ExportPeriod;

final class PremiumCalendarPeriodFieldProvider implements ExportPeriodFieldProviderInterface, ExportPeriodViewProviderInterface {
	public function render( ?ExportPeriod $period = null, array $selection = [], bool $read_only = false ): void {
		// Implement admin fields.
	}

	public function get_period_from_request( array $request ): ExportPeriod|\WP_Error {
		// Implement request parsing.
	}

	public function get_period_from_selection( array $selection ): ExportPeriod|\WP_Error {
		// Implement saved selection parsing.
	}

	public function get_period_selection_from_request( array $request ): array {
		// Implement selection extraction.
	}

	public function stores_concrete_period( array $selection ): bool {
		// Implement whether the selection should persist concrete dates.
	}

	public function get_default_title_suffix(): string {
		return 'custom-period';
	}

	public function format_period_label( ExportPeriod $period ): string {
		// Implement period display.
	}
}
```

Generated export records always store concrete start and end datetimes. Saved
export configurations also store the provider's period selection. Relative
configuration selections should not persist concrete start/end dates; they are
resolved to concrete dates when the configuration is executed.
