# Export Configuration Form Field Providers

Export configuration form field providers render additional form fields for
saved export configurations. Providers can optionally implement
`ExportTypeAwareInterface` when they only apply to specific export types. Values
are sanitized, validated, and stored as JSON under
`_storeaccountant_config_additional_settings`.

## Contract

```php
StoreAccountant\Export\Contract\ExportConfigurationFormFieldProviderInterface
```

Methods:

- `get_id(): string`
- `render_fields(array $settings, bool $read_only = false): void`
- `sanitize_settings(array $request): array`
- `validate_settings(array $settings): true|\WP_Error`

Providers should render disabled controls or plain text when `$read_only` is
`true`, because configuration read views reuse the same form field providers.

Optional export type awareness:

```php
StoreAccountant\Export\Contract\ExportTypeAwareInterface
```

Methods:

- `supports_export_type(string $export_type): bool`

## Registry

```php
StoreAccountant\Export\Configuration\ExportConfigurationFormFieldProviderRegistry
```

## Hook

```php
storeaccountant_export_configuration_form_field_provider
```

## Registration

```php
add_filter(
	'storeaccountant_export_configuration_form_field_provider',
	static function ( array $providers ) use ( $provider ): array {
		$providers[ $provider->get_id() ] = $provider;

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

use StoreAccountant\Export\Contract\ExportConfigurationFormFieldProviderInterface;
use StoreAccountant\Export\Contract\ExportTypeAwareInterface;

final class CustomConfigurationFormFieldProvider implements ExportConfigurationFormFieldProviderInterface, ExportTypeAwareInterface {
	public function get_id(): string {
		return 'custom_configuration';
	}

	public function supports_export_type( string $export_type ): bool {
		return 'orders' === $export_type;
	}

	public function render_fields( array $settings, bool $read_only = false ): void {
		// Implement admin field rendering.
	}

	public function sanitize_settings( array $request ): array {
		// Implement request sanitization.
	}

	public function validate_settings( array $settings ): true|\WP_Error {
		// Implement validation.
	}
}
```

This structure is prepared for premium and third-party features, because data
exporters and export renderers may later need their own options.
