# Storage Adapters

Storage adapters persist generated export files. They receive a complete storage
reference from `ExportStoragePathGenerator` and should not derive folder names or
file names from export titles themselves.

## Contract

```php
StoreAccountant\Storage\Contract\StorageAdapterInterface
```

Methods:

- `get_id(): string`
- `persist(StorageFileConfiguration $configuration): string|\WP_Error`
- `delete_file(string $storage_path): void`
- `delete_directory(string $storage_path): void`
- `create_directory(string $storage_path): void`
- `directory_exists(string $storage_path): bool`
- `file_exists(string $storage_path): bool`
- `set_visibility(string $storage_path, string $visibility): void`
- `get_visibility(string $storage_path): string|\WP_Error`
- `get_mime_type(string $storage_path): string|\WP_Error`
- `get_last_modified(string $storage_path): int|\WP_Error`
- `get_file_size(string $storage_path): int|\WP_Error`
- `get_file(string $storage_path): StorageFile|\WP_Error`
- `ensure(): true|\WP_Error`

## Registry

```php
StoreAccountant\Storage\StorageAdapterRegistry
```

## Hook

```php
storeaccountant_storage_adapter
```

User-facing labels are resolved from adapter IDs through the translation key
`storage_adapter_{id}`, for example `storage_adapter_local`.

## Registration

```php
<?php

add_filter(
	'storeaccountant_storage_adapter',
	static function ( array $adapters ) use ( $s3_adapter ): array {
		$adapters[ $s3_adapter->get_id() ] = $s3_adapter;

		return $adapters;
	},
	110
);
```

## Example Implementation

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageFile;
use StoreAccountant\Storage\StorageFileConfiguration;

final class S3StorageAdapter implements StorageAdapterInterface {
	public function get_id(): string {
		return 's3';
	}

	public function persist( StorageFileConfiguration $configuration ): string|\WP_Error {
		// Implement upload and return the storage path.
	}

	public function delete_file( string $storage_path ): void {
		// Implement file deletion.
	}

	public function delete_directory( string $storage_path ): void {
		// Implement directory deletion.
	}

	public function create_directory( string $storage_path ): void {
		// Implement directory creation.
	}

	public function directory_exists( string $storage_path ): bool {
		// Implement directory lookup.
	}

	public function file_exists( string $storage_path ): bool {
		// Implement file lookup.
	}

	public function set_visibility( string $storage_path, string $visibility ): void {
		// Implement visibility update.
	}

	public function get_visibility( string $storage_path ): string|\WP_Error {
		// Implement visibility lookup.
	}

	public function get_mime_type( string $storage_path ): string|\WP_Error {
		// Implement MIME type lookup.
	}

	public function get_last_modified( string $storage_path ): int|\WP_Error {
		// Implement last modified lookup.
	}

	public function get_file_size( string $storage_path ): int|\WP_Error {
		// Implement file size lookup.
	}

	public function get_file( string $storage_path ): StorageFile|\WP_Error {
		// Implement readable stream creation.
	}

	public function ensure(): true|\WP_Error {
		// Implement setup checks.
	}
}
```

`get_file()` returns a `StorageFile` value with a readable stream, file name, and
MIME type so download controllers do not need to know which storage backend is
used. List views should use `file_exists()` when they only need to know whether
a download button can be enabled.
