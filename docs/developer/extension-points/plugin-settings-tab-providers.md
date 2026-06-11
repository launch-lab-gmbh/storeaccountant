# Plugin Settings Tab Providers

Plugin settings tab providers add tabs to the StoreAccountant plugin settings
page. They are intended for add-ons that need their own settings screens, for
example future premium features, integrations, licensing, or feature-specific
configuration.

Provider tabs are appended after the core settings tabs. Core tab identifiers
are reserved and cannot be replaced by providers.

## Contract

```php
StoreAccountant\Settings\Contract\PluginSettingsTabProviderInterface
```

Methods:

- `get_id(): string`
- `get_tabs(): array`
- `render(string $tab): void`
- `save(string $tab, array $request): void`

The settings page verifies the normal StoreAccountant settings capability before
rendering or saving provider-backed tabs. Providers should still validate and
sanitize their own submitted fields inside `save()`, and they should perform
additional permission checks when a custom tab exposes sensitive operations.

## Registry

```php
StoreAccountant\Settings\Admin\PluginSettingsTabProviderRegistry
```

## Hook

```php
storeaccountant_plugin_settings_tab_provider
```

The hook is intentionally not order-specific. Providers can return one or more
tabs from `get_tabs()`, keyed by stable tab identifiers.

## Registration

```php
add_filter(
	'storeaccountant_plugin_settings_tab_provider',
	static function ( array $providers ) use ( $tab_provider ): array {
		$providers[ $tab_provider->get_id() ] = $tab_provider;

		return $providers;
	},
	110
);
```

Important: the registered value must be a concrete instance of
`PluginSettingsTabProviderInterface`.

## Example Implementation

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Settings\Contract\PluginSettingsTabProviderInterface;

final class AcmeSettingsTabProvider implements PluginSettingsTabProviderInterface {
	public function get_id(): string {
		return 'acme_settings';
	}

	public function get_tabs(): array {
		return [
			'acme' => __( 'Acme', 'acme-storeaccountant' ),
		];
	}

	public function render( string $tab ): void {
		if ( 'acme' !== $tab ) {
			return;
		}

		$value = (string) get_option( 'acme_storeaccountant_endpoint', '' );
		echo '<input type="url" name="acme_storeaccountant_endpoint" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}

	public function save( string $tab, array $request ): void {
		if ( 'acme' !== $tab ) {
			return;
		}

		$value = isset( $request['acme_storeaccountant_endpoint'] )
			? esc_url_raw( wp_unslash( $request['acme_storeaccountant_endpoint'] ) )
			: '';

		update_option( 'acme_storeaccountant_endpoint', $value );
	}
}
```
