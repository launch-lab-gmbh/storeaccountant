# Export Read Tab Providers

Export read tab providers add tabs to saved export read views. They are meant
for read-only export information, diagnostics, generated file context, and
future export-type-specific review screens. Providers decide through
`supports()` whether they apply to the current saved export.

The bundled read view uses the same provider system for its own tabs:

- `Export Details`
- `Raw Data`

## Contract

```php
StoreAccountant\Export\Contract\ExportReadTabProviderInterface
```

Methods:

- `get_id(): string`
- `supports(\WP_Post $export): bool`
- `get_tabs(\WP_Post $export): array`
- `render(string $tab, \WP_Post $export): void`

## Registry

```php
StoreAccountant\Export\ExportReadTabProviderRegistry
```

## Hook

```php
storeaccountant_export_read_tab_provider
```

The hook is intentionally shared across export types. Providers decide through
`supports()` whether they apply to the current saved export, usually by checking
the stored export adapter ID such as `orders` or `customers`.

## Registration

```php
add_filter(
	'storeaccountant_export_read_tab_provider',
	static function ( array $providers ) use ( $tab_provider ): array {
		$providers[ $tab_provider->get_id() ] = $tab_provider;

		return $providers;
	},
	110
);
```

Important: the registered value must be a concrete instance of
`ExportReadTabProviderInterface`.

## Example Implementation

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Export\Contract\ExportReadTabProviderInterface;

final class ExportAuditReadTabProvider implements ExportReadTabProviderInterface {
	public function get_id(): string {
		return 'acme_export_audit';
	}

	public function supports( \WP_Post $export ): bool {
		return 'orders' === get_post_meta( $export->ID, '_storeaccountant_export_adapter', true );
	}

	public function get_tabs( \WP_Post $export ): array {
		return [
			'acme_audit' => __( 'Audit', 'acme-storeaccountant' ),
		];
	}

	public function render( string $tab, \WP_Post $export ): void {
		if ( 'acme_audit' !== $tab ) {
			return;
		}

		echo '<p>' . esc_html__( 'Render export audit information here.', 'acme-storeaccountant' ) . '</p>';
	}
}
```
