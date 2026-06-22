---
title: Field Mapping
description: Configure columns, column labels, order, and field-specific options for your exports.
---

# Field Mapping

Field mapping controls the columns of an export configuration. Open it through
`Accounting > Exports > Export Configurations` by opening a configuration and selecting the `Field Mapping` tab.

Field mappings apply to exports started from that configuration. Quick exports use the default fields of the selected
export type.

## What You Can Configure

Depending on the export type, field mapping lets you:

- enable or disable fields
- change column headings
- adjust the order of columns
- set field options, for example date, time, or amount formatting
- include custom fields when they are detected by the export type
- use tax and invoice fields when they are available for the configuration

[Screenshot: Field mapping with columns and order]

## Recommended Workflow

1. Open `Accounting > Exports > Export Configurations`.
2. Open the configuration you want to edit.
3. Go to the `Field Mapping` tab.
4. Disable columns your accounting workflow does not need.
5. Rename columns to match the target system.
6. Sort columns into the required order.
7. Save the configuration.
8. Start an export from this configuration.

## New or Missing Fields

StoreAccountant handles field mappings defensively:

- New fields added later by StoreAccountant or an extension are enabled by default.
- Fields that are no longer available are ignored when displayed and exported.
- If a provider becomes available again later, a previously stored mapping can become effective again.

This is especially important for optional integrations such as invoice plugins or additional export fields.

## Tax Fields for Orders

Order exports can include tax fields in simple or extended form. You choose this in the configuration field
`Tax Fields`. If you change this setting, the available field list can change. Check the field mapping again
afterwards.

## Invoice Fields and Attachments

If a supported invoice plugin is active and selected in the StoreAccountant settings, invoice-related fields or
attachments can become available for order exports. Check
`Plugins > Installed Plugins > StoreAccountant > Settings > Invoice Providers`.
