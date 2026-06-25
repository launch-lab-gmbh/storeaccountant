---
title: Create an Export
description: Create a quick export or start an export from a saved export configuration.
---

# Create an Export

You start new exports under `Accounting > Exports`. Above the export list, you will find the `Create New Export` area.

StoreAccountant can start an export in two ways:

- `Quick Export`: a one-time export with settings entered directly in the form.
- saved export configuration: an export from a prepared template.

Every started export is saved as a record in the export list. Processing runs in the background. You can follow the
status in the list and download the file after the export is completed.

## Create a Quick Export

A quick export is useful for one-time reports or tests.

1. Open `Accounting > Exports`.
2. In the `Create New Export` area, choose `Quick Export`.
3. Click `Select` or the corresponding start action.
4. Enter a unique name in the `Title` field.
5. In the `Export Type` field, choose whether to export orders, customers, or products.
6. Click `Continue`.

[Screenshot: Quick export with title and export type]

In the second step, configure the details:

1. Choose the filters for the export type.
2. Choose the `Export Format`, for example CSV or JSON.
3. Choose the `Storage Location`.
4. Optionally enter a `Download Password`. If you leave the field empty, the current global download password is used.
5. Check the `Batch Size`. If you are unsure, keep the default value `50`.
6. Click `Start Quick Export`.

[Screenshot: Quick export details]

You will then return to the export list. The new export appears with a status such as `Queued`, `Processing`,
`Completed`, or `Failed`.

## Filters in a Quick Export

The displayed filters depend on the selected export type.

For order exports, choose:

- `Order Date Field`: defines whether the period is applied to created date, modified date, completed date, or paid
  date.
- `Month`: allows `This month`, `Last month`, `All time`, or a concrete month.
- `Year`: appears for concrete months.
- `Order Status`: at least one status must be selected.

For customer exports, choose:

- `Month`: period based on customer creation date.
- `Customer Country Field`: billing country or shipping country.
- `Customer Countries`: all countries, unassigned customers, or selected countries with existing customer orders.

For product exports, choose:

- `Month`: `This month`, `Last month`, or `All time`.
- `Product Variants`: either parent products only or variants as separate export rows.

## Start an Export From a Configuration

Use this path when you want to generate the same export repeatedly with the same columns, filters, and security
settings.

1. Open `Accounting > Exports`.
2. In the `Create New Export` area, choose a saved export configuration.
3. Enter a unique name in the `Title` field for the new export.
4. Start the export.

[Screenshot: Start export from saved configuration]

StoreAccountant takes export type, filters, export format, storage location, batch size, download password, and field
mapping from the configuration. Dynamic periods such as `This month` or `Last month` are resolved and snapshotted when
the export is started.

If no suitable template exists yet, create an [Export Configuration](create-export-configuration.md) first.
