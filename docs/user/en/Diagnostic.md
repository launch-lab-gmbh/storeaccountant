---
title: Diagnostic Packages
description: Learn how to enable StoreAccountant diagnostic logging and send a diagnostic package to support.
---

# Diagnostic Packages

Diagnostic packages help support investigate technical StoreAccountant errors without showing detailed internal errors
directly in the admin interface.

Diagnostic logging is disabled by default. A user with the required StoreAccountant permission can enable it in the
plugin settings.

## Enable Diagnostic Logging

1. Open `Plugins > Installed Plugins` in the WordPress admin.
2. Click `Settings` for StoreAccountant.
3. Go to the `Diagnostics` tab.
4. Enable diagnostic logging.
5. Save the settings.

After diagnostic logging is enabled, StoreAccountant can create a diagnostic package when a supported admin action fails,
for example when saving an export configuration or running an export.

## What You Will See

When an error happens, StoreAccountant still shows the normal short error message. If a diagnostic package was created
and your user account is allowed to download diagnostic packages, the notice also shows:

- a support ID for the logged error
- a link to download the diagnostic package

Send the downloaded file and the support ID to your StoreAccountant support contact.

If you only see the normal error message, diagnostic logging may be disabled, your user account may not have permission
to download diagnostic packages, or no diagnostic package was created for that specific error.

You manage diagnostic permissions under
`Plugins > Installed Plugins > StoreAccountant > Settings > Permissions`.

## What the Package Contains

The package contains technical information about the failed StoreAccountant action, such as the error source, support ID,
time, plugin context, error codes, and exception details.

The package is not intended to contain passwords, access tokens, nonces, authentication cookies, or generated export file
contents. Depending on the failed action, it may include technical IDs such as export configuration IDs or adapter IDs.
Review the file according to your company policy before sending it outside your organization.

## WordPress Debug Log

StoreAccountant also sends the diagnostic incident to the WordPress debug log mechanism as a fallback. This only appears
in the WordPress debug log if the WordPress installation is configured to write debug logs.

## Further Reading

- [Plugin Configuration](plugin/configuration.md)
- [Permissions](plugin/permissions.md)
- [Manage and Download Exports](exports/manage-exports.md)
