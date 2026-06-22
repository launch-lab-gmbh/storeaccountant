---
title: Download Password Protection
description: Understand global, configuration-specific, and export-specific download passwords.
---

# Download Password Protection

StoreAccountant protects public export downloads with a download token and a password. The token locates the export
record, but it does not replace the password. Access requires the matching download password.

## Where Passwords Are Managed

There are three levels:

- Global password: `Plugins > Installed Plugins > StoreAccountant > Settings > Security`.
- Configuration password: `Accounting > Exports > Export Configurations > open configuration`.
- Quick export password: in the second step of the quick export form.

## Global Download Password

The global password is used as the default. It is created during activation or during the first settings
initialization.

Change it under:

`Plugins > Installed Plugins > StoreAccountant > Settings > Security`

Enter a new password in the `Global Download Password` field and save the settings. If you leave the field empty, the
existing password remains unchanged.

Users with permission to view download passwords can see the current password in the backend.

## Passwords in Export Configurations

An export configuration stores its own download password. If you do not enter a password when saving a configuration,
StoreAccountant stores the current global download password on that configuration.

Important: Later changes to the global password do not automatically change already stored configuration passwords.
Open and save the configuration with a new password if it should use another password.

## Passwords for Quick Exports

For quick exports, you can enter a password directly in the export form. If you leave the field empty, StoreAccountant
uses the current global download password for that export.

## Exports Store Password Snapshots

Every export stores the effective password when it is started. This keeps older downloads predictable and independent
from later password changes.

Examples:

- You change the global password today. A quick export created yesterday still uses the old password.
- You change the password of an export configuration. Exports already created from that configuration keep their old
  password.
- A new export from the changed configuration uses the new configuration password.

## View Passwords

Whether a user may view stored passwords in the backend is controlled by the `View download passwords` permission. You
can find this setting under `Plugins > Installed Plugins > StoreAccountant > Settings > Permissions`.

Without this permission, StoreAccountant shows passwords as protected. Downloads can still work when the user knows the
password.

## Technical Requirement

Password-protected downloads require Sodium or OpenSSL on the server. If neither is available, StoreAccountant disables
password fields and blocks public download creation instead of creating unprotected export links.
