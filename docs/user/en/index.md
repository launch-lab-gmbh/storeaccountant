---
title: User Guide
description: Learn how to use StoreAccountant to seamlessly export your WooCommerce data and integrate it into accounting, reporting, CRM systems, and other business workflows.
---

![Header Image](images/header.png)

# StoreAccountant User Guide

> Welcome to the English user guide for the StoreAccountant WordPress plugin.

StoreAccountant extends WooCommerce with flexible export capabilities for orders, customers, and products. Every export
is saved as its own record, processed in the background, and then shown in the WordPress admin area with status,
details, and a protected download.

The most important areas in the WordPress admin are:

- `Accounting > Exports`: export overview, quick exports, and exports from saved configurations.
- `Accounting > Exports > Export Configurations`: reusable export templates with filters, format, storage location,
  password, and field mapping.
- `Plugins > Installed Plugins > StoreAccountant > Settings`: storage locations, invoice providers, background
  processing, permissions, security, and diagnostics.

## Quick Start

1. Install and activate StoreAccountant as described in [Installation](installation.md).
2. Check under [Plugin Configuration](plugin/configuration.md) that at least one storage location is enabled.
3. Create a [Quick Export](exports/create-export.md) for one-time tasks.
4. Create an [Export Configuration](exports/create-export-configuration.md) for reusable workflows.
5. Adjust the [Field Mapping](exports/field-mapping.md) if needed.
6. Download completed files from the [Export Overview](exports/manage-exports.md).

## Supported Features

- Exports for WooCommerce orders, customers, and products.
- CSV and JSON export formats.
- Filters by period, order status, customer country, and product-related settings.
- Configurable columns and column order through field mappings.
- Tax fields for order exports in simple or extended form.
- Invoice fields and invoice attachments when a supported invoice plugin is active.
- Password-protected download links for generated export archives.
- Background processing with visible progress in the export list.
- Role-based StoreAccountant permissions for backend users.
- Diagnostic packages for support cases.

## Documentation

- [Installation](installation.md)
- [Exports](exports/index.md)
- [Create an Export](exports/create-export.md)
- [Create an Export Configuration](exports/create-export-configuration.md)
- [Field Mapping](exports/field-mapping.md)
- [Manage and Download Exports](exports/manage-exports.md)
- [Download Password Protection](exports/download-password-protection.md)
- [Plugin Configuration](plugin/configuration.md)
- [Permissions](plugin/permissions.md)
- [Diagnostic Packages](Diagnostic.md)
