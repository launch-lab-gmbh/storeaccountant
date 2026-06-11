# Export Renderers

Export renderers turn an `ExportDataset` into an `ExportArtifact`. They should
not know whether the selected storage adapter is local, S3, SFTP, or another
destination.

## Contract

```php
StoreAccountant\Export\Contract\ExportRendererInterface
```

Methods:

- `get_id(): string`
- `get_file_extension(): string`
- `get_mime_type(): string`
- `render(ExportDataset $dataset, ExportPayload $payload): ExportArtifact|\WP_Error`

## Registry

```php
StoreAccountant\Export\ExportRendererRegistry
```

## Hook

```php
storeaccountant_export_renderer
```

User-facing labels are resolved from renderer IDs through the translation key
`exporter_{id}`, for example `exporter_csv`.

## Registration

```php
add_filter(
	'storeaccountant_export_renderer',
	static function ( array $renderers ) use ( $xlsx_renderer ): array {
		$renderers[ $xlsx_renderer->get_id() ] = $xlsx_renderer;

		return $renderers;
	},
	110
);
```

## Example Implementation

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\ExportArtifact;
use StoreAccountant\Export\ExportPayload;

final class XlsxExportRenderer implements ExportRendererInterface {
	public function get_id(): string {
		return 'xlsx';
	}

	public function get_file_extension(): string {
		return 'xlsx';
	}

	public function get_mime_type(): string {
		return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
	}

	public function render( ExportDataset $dataset, ExportPayload $payload ): ExportArtifact|\WP_Error {
		// Implement rendering and return the storage-ready artifact.
	}
}
```

Important: the registered value must be a concrete instance of
`ExportRendererInterface`, not only a label string.

## Attachments

Export renderers that want export attachments to be collected and persisted should
also implement:

```php
StoreAccountant\Export\Contract\ExportRendererSupportsAttachmentsInterface
```

Renderers that do not implement this marker interface receive the generated
`ExportDataset` without collected attachments. This keeps formats such as JSON
focused on their own serialized payload while formats such as CSV can still be
stored together with additional export files.

## Serializer Renderers

Serializer-backed renderers can use:

```php
StoreAccountant\Export\Renderer\SerializerExportRenderer
```

The renderer receives an `ExportTemplateNormalizerInterface`, serializes the
normalized data through Symfony's serializer component, and passes a serializer
format such as `json`.

When several formats share the same rendering implementation, register them
through a dedicated hook registrar instead of making the renderer register
itself. This keeps the renderer as one configured format instance, while the
registrar can publish as many instances as needed.

Example:

```php
use Symfony\Component\Serializer\SerializerInterface;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\ExportTemplateNormalizerInterface;
use StoreAccountant\Export\Renderer\SerializerExportRenderer;

final readonly class AcmeSerializerExportRendererRegistrar implements HookRegistrarInterface {
	public function __construct(
		private ExportTemplateNormalizerInterface $template_normalizer,
		private SerializerInterface $serializer
	) {}

	public function register(): void {
		add_filter(
			'storeaccountant_export_renderer',
			function ( array $renderers ): array {
				$renderers['json'] = new SerializerExportRenderer(
					$this->template_normalizer,
					$this->serializer,
					'json',
					'json',
					'json',
					'application/json'
				);

				// Enable when the XML template shape is ready.
				// $renderers['xml'] = new SerializerExportRenderer(
				// 	$this->template_normalizer,
				// 	$this->serializer,
				// 	'xml',
				// 	'xml',
				// 	'xml',
				// 	'application/xml'
				// );

				return $renderers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}
}
```

Register the registrar as a hook service in the container. Do not register a
single `SerializerExportRenderer` instance directly when multiple formats should
use the same renderer class with different format metadata.
