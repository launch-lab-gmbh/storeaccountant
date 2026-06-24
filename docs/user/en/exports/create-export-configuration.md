---
title: Create an Export Configuration
description: Create and edit reusable export templates for orders, customers, and products.
---

# Create an Export Configuration

Export configurations are templates for recurring exports. They store the business settings for an export without
immediately creating an export file.

You can find configurations under `Accounting > Exports > Export Configurations`.

## Create a New Configuration

1. Open `Accounting > Exports`.
2. Go to the `Export Configurations` tab.
3. Click `Create Export Configuration`.
4. Enter a `Configuration Title`.
5. Choose the `Export Type`.
6. Click `Save Configuration`.

[Screenshot: New export configuration]

After the first save, StoreAccountant opens the detail view for the new configuration. Only then do type-specific
settings such as filters, export format, storage location, download password, and field mapping appear.

The export type cannot be changed after creation. If you need another export type, create a new configuration.

## General Settings

These fields are available depending on the export type:

- `Configuration Title`: name of the template in the configuration list and export selector.
- `Export Type`: orders, customers, or products. Locked after the first save.
- `Export Format`: output format, for example CSV or JSON.
- `Storage Location`: target where StoreAccountant stores the export file.
- `Download Password`: password for later downloads from exports started with this configuration.
- `Batch Size`: number of records per processing step. The default value is `50`, the minimum value is `10`.

If no storage location is available, open `Plugins > Installed Plugins > StoreAccountant > Settings >
Storage Locations` and enable at least one storage location.

## Order Configuration

For orders, define:

- which order date field is used for the period
- which period is exported
- which order statuses are included
- how tax fields are structured
- whether invoice fields or invoice attachments are available, if a supported invoice provider is active

Depending on your installation, `Tax Fields` can offer simple or extended tax fields. The extended option can split tax
values by WooCommerce tax rate.

## Customer Configuration

For customers, define:

- which period based on customer creation date is used
- whether billing country or shipping country is used as the country filter
- which countries are included

The country selector only shows countries that occur in the existing WooCommerce customer data.

## Product Configuration

For products, define:

- which period based on product creation date is used
- whether only parent products are exported
- whether variations are exported as separate export rows

## Edit Field Mapping

After saving, StoreAccountant shows additional tabs for the configuration. The built-in export types include a
`Field Mapping` tab. There you define which columns are exported and in which order they appear in the file.

For details, see [Field Mapping](field-mapping.md).

## Use a Configuration

Start a saved configuration from `Accounting > Exports` in the `Create New Export` area. Choose the configuration,
enter an export title, and start the export.

Existing exports are kept even if the related configuration is deleted later. In the export details, StoreAccountant
then shows that the configuration was deleted.
