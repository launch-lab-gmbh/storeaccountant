# Export Configuration Tab Providers

Export configuration tab providers add type-specific tabs to saved export
configuration detail pages. The default configuration form stays available as
the `Configuration` tab. Additional providers decide through `supports()` whether
they apply to the current saved configuration.

## Contract

```php
StoreAccountant\Export\Contract\ExportConfigurationTabProviderInterface
```

Methods:

- `get_id(): string`
- `supports(\WP_Post $configuration): bool`
- `get_tabs(\WP_Post $configuration): array`
- `render(string $tab, \WP_Post $configuration, bool $read_only = false): void`

Providers should render disabled controls or plain text when `$read_only` is
`true`, because configuration read views reuse the same tab providers.

## Registry

```php
StoreAccountant\Export\Configuration\ExportConfigurationTabProviderRegistry
```

## Hook

```php
storeaccountant_export_configuration_tab_provider
```

The hook is intentionally not order-specific. Providers decide through
`supports()` whether they apply to the current saved configuration, usually by
checking the stored export adapter ID such as `orders`.

## Registration

```php
add_filter(
	'storeaccountant_export_configuration_tab_provider',
	static function ( array $providers ) use ( $tab_provider ): array {
		$providers[ $tab_provider->get_id() ] = $tab_provider;

		return $providers;
	},
	110
);
```

Important: the registered value must be a concrete instance of
`ExportConfigurationTabProviderInterface`.

## Example Implementation

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Export\Contract\ExportConfigurationTabProviderInterface;

final class ConfigurationDiagnosticsTabProvider implements ExportConfigurationTabProviderInterface {
	public function get_id(): string {
		return 'acme_configuration_diagnostics';
	}

	public function supports( \WP_Post $configuration ): bool {
		return 'orders' === get_post_meta( $configuration->ID, '_storeaccountant_config_export_adapter', true );
	}

	public function get_tabs( \WP_Post $configuration ): array {
		return [
			'acme_diagnostics' => __( 'Diagnostics', 'acme-storeaccountant' ),
		];
	}

	public function render( string $tab, \WP_Post $configuration, bool $read_only = false ): void {
		if ( 'acme_diagnostics' !== $tab ) {
			return;
		}

		echo '<p>' . esc_html__( 'Render configuration diagnostics here.', 'acme-storeaccountant' ) . '</p>';
	}
}
```
