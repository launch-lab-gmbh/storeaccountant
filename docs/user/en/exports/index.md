---
title: Exports
description: Overview of quick exports, export configurations, saved export records, and downloads.
---

# Exports

StoreAccountant distinguishes between exports and export configurations.

An export is a concrete run. It has a title, a trigger time, a status, an export file, and a protected download. You can
find these records under `Accounting > Exports`.

An export configuration is a reusable template. It stores export type, filters, format, storage location, download
password, batch size, and field mapping. You can find these templates in the `Export Configurations` tab on the export
overview.

## Which Export Types Are Available?

StoreAccountant includes these export types:

- `Orders`: WooCommerce orders with period, order status, tax fields, and optional invoice data.
- `Customers`: WooCommerce customers with period and country filter.
- `Products`: WooCommerce products with period and optionally separately exported variations.

## Which Export Formats Are Available?

Exports can currently be generated as `CSV` or `JSON`. The available formats appear in the `Export Format` field.

## Which Workflow Should I Use?

- [Create an Export](create-export.md): for a one-time quick export or an export from a saved configuration.
- [Create an Export Configuration](create-export-configuration.md): for reusable export templates.
- [Field Mapping](field-mapping.md): for columns, column labels, order, and field-specific options.
- [Manage and Download Exports](manage-exports.md): for status, details, download, and error analysis.
- [Download Password Protection](download-password-protection.md): for global, configuration-specific, and
  export-specific download passwords.

## Not Currently Available in the UI

Scheduled, automatically recurring exports are prepared internally but are not currently offered in the user interface.
Reusable workflows are currently prepared through export configurations and started manually from
`Accounting > Exports`.
