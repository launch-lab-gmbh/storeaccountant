---
title: Plugin Configuration
description: Configure storage locations, invoice providers, background processing, security, permissions, and diagnostics.
---

# Plugin Configuration

Open the StoreAccountant settings through:

`Plugins > Installed Plugins > StoreAccountant > Settings`

The visible tabs depend on your permissions. Administrators always have access to all StoreAccountant areas.

## Storage Locations

Backend path:

`Plugins > Installed Plugins > StoreAccountant > Settings > Storage Locations`

Here you choose which storage locations can be selected for new exports and export configurations. The built-in storage
type is `Local`. It stores export archives in a protected location below `wp-content/uploads/storeaccountant`.

If only one storage location is available, it stays active automatically and cannot be disabled.

At least one storage location must be active before exports can be started and configurations can be saved.

## Invoice Providers

Backend path:

`Plugins > Installed Plugins > StoreAccountant > Settings > Invoice Providers`

Here you choose which active invoice provider may add invoice fields or invoice attachments to exports. StoreAccountant
currently supports `PDF Invoices & Packing Slips for WooCommerce` when it is active in the shop.

Only one invoice provider can be enabled at a time. If no supported invoice plugin is active, StoreAccountant shows a
notice.

## Transports

Backend path:

`Plugins > Installed Plugins > StoreAccountant > Settings > Transports`

Transports control how StoreAccountant hands background jobs over to processing. Exports are not run as one long
browser request. They are processed as background jobs.

In most cases, the Action Scheduler transport is the right choice because it works with the free WooCommerce and
WordPress infrastructure. A synchronous transport can be useful for simple local tests, but it is usually not the best
choice for real shops.

## Permissions

Backend path:

`Plugins > Installed Plugins > StoreAccountant > Settings > Permissions`

Here you assign individual StoreAccountant actions to backend roles, for example viewing exports, creating exports,
downloading export files, or editing export configurations.

For details, see [Permissions](permissions.md).

## Security

Backend path:

`Plugins > Installed Plugins > StoreAccountant > Settings > Security`

Here you manage the global download password for protected export downloads. Leave the field empty if the current
password should remain unchanged.

For details, see [Download Password Protection](../exports/download-password-protection.md).

## Diagnostics

Backend path:

`Plugins > Installed Plugins > StoreAccountant > Settings > Diagnostics`

Diagnostic logging is disabled by default. Enable it only when you need to investigate technical errors or provide a
diagnostic package to support.

For details, see [Diagnostic Packages](../Diagnostic.md).

## Back to the Export Overview

On the settings page, the `Back to Accounting` button takes you back to `Accounting > Exports`.
