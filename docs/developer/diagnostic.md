# Diagnostic Logging

StoreAccountant uses diagnostic logging for admin-facing failures where the UI should keep a short, user-friendly error
message, but support and developers still need the underlying technical context.

The diagnostic system creates plugin-local incident files and also forwards the same redacted incident payload to the
WordPress debug log mechanism. Incident files are intended for support packages that can be downloaded from the admin
UI when diagnostic logging is enabled and the current user has permission to download diagnostic packages.

## Components

- `StoreAccountant\Diagnostic\DiagnosticSettings` reads whether diagnostic logging is enabled.
- `StoreAccountant\Diagnostic\DiagnosticIncidentLogger` is the main service feature code should call.
- `StoreAccountant\Diagnostic\DiagnosticIncidentRepository` persists incident files below the protected upload
  directory.
- `StoreAccountant\Diagnostic\Admin\DiagnosticIncidentDownloadController` streams a single incident package through an
  authenticated admin action.
- `StoreAccountant\Diagnostic\Admin\DiagnosticSettingsTabProvider` adds the plugin settings tab for diagnostic logging.
- `StoreAccountant\Storage\ProtectedUploadDirectory` prepares upload subdirectories with deny rules and an empty
  `index.html` file.

Incident files are stored below:

```text
wp-content/uploads/storeaccountant/logging/
```

The directory is protected with a `.htaccess` deny rule and an empty `index.html`. Downloads must go through the admin
controller so WordPress capabilities and nonces can be checked before the package is streamed.

## Runtime Flow

1. A user enables diagnostic logging in the plugin settings.
2. An admin workflow catches an exception or receives a `WP_Error`.
3. The workflow keeps showing its normal user-facing error message.
4. The workflow calls `DiagnosticIncidentLogger::error()` with a stable source, a short message, context, and optionally
   the original exception or `WP_Error`.
5. The logger creates a unique support ID and stores a JSON incident file.
6. The logger also forwards the incident payload through WordPress debug logging as a fallback.
7. If the current user can download diagnostic packages, the UI can show the support ID and a download link.
8. The download controller verifies the nonce and diagnostic package permission before returning the exact incident file.

WordPress debug output only reaches `debug.log` when the WordPress installation is configured to write debug logs. The
plugin incident file is independent from that setting and is controlled by the StoreAccountant diagnostic setting.

## Logging From Feature Code

Inject `DiagnosticIncidentLogger` into the service or admin page that handles the failure. Register the dependency in
the container next to the other constructor arguments for that class.

```php
use StoreAccountant\Diagnostic\DiagnosticIncidentLogger;

final class ExampleAdminPage {
	public function __construct(
		private readonly DiagnosticIncidentLogger $diagnostics,
	) {
	}

	private function handle_failure( \Throwable $exception ): string {
		return $this->diagnostics->error(
			'example_admin_page',
			__( 'The example action could not be completed.', 'storeaccountant' ),
			[
				'action' => 'save_example',
				'entity_id' => 123,
			],
			null,
			$exception
		);
	}
}
```

Use a stable source identifier such as `export_configuration_save` or `accounting_export_run`. The support ID returned
by `error()` can be passed through a redirect argument or kept in local request state so the later admin notice can
include the diagnostic download link.

## Context Guidelines

Keep diagnostic context useful, small, and safe:

- Include technical identifiers such as export configuration IDs, adapter IDs, selected action names, error codes, and
  class names.
- Prefer IDs and state names over full business records.
- Do not include passwords, API keys, nonces, authentication cookies, access tokens, or raw request bodies.
- Do not include generated export file contents.
- Avoid customer or order personal data unless a specific support workflow truly requires it.

The logger normalizes values before writing the payload, but callers are still responsible for deciding which context is
appropriate to collect.

## Permissions

Diagnostic logging uses the normal StoreAccountant permission system:

- `PermissionActionIds::DIAGNOSTIC_LOGGING_MANAGE` controls access to the setting that enables or disables diagnostic
  logging.
- `PermissionActionIds::DIAGNOSTIC_PACKAGE_DOWNLOAD` controls whether a user can see and download diagnostic packages
  from admin error notices.

Administrators are allowed by the existing permission checker behavior. Other roles can be granted the actions through
the plugin permission settings.

## Activation Setup

`PluginActivator` prepares the diagnostic log directory during activation by using `ProtectedUploadDirectory`. The same
directory preparation service is also used for local storage so upload-directory protection logic stays in one place.

If a diagnostic incident cannot be written to the protected directory, the logger still attempts to write the incident
payload and the storage error details to the WordPress debug log mechanism.
