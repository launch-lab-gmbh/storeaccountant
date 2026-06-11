# Extension Points

StoreAccountant is structured so the base plugin can remain the stable base while
third party features can later be added through a separate Pro add-on or a similar
extension model.

The base codebase should not need to be maintained twice. Third party features
should extend the system through interfaces, registries, and WordPress hooks
where possible.

Domain lifecycle events are documented separately in [Events](events.md).

## Pre-1.0 Development Status

StoreAccountant is still below version `1.0`. Extension APIs, hook names,
interfaces, service contracts, and stored configuration structures may change
until the first stable release. During this phase these changes are not treated
as breaking changes.

## Base features

- Month-based order date filtering.
- Order export adapter.
- Customer export adapter.
- Persisted export configurations.
- Hookable field providers for export datasets.
- Hookable field value providers for export datasets.
- Hookable field value mutators.
- WooCommerce PDF Invoices & Packing Slips invoice plugin integration.
- CSV, JSON, and XML export renderers.
- Local storage adapter backed by a protected Flysystem zip archive.
- Password-protected frontend export downloads.
- Persisted exports.
- Plugin settings for enabled storage locations.
- Selectable queue transports with Action Scheduler loopback processing.

## Registry Rules

All hook-backed registries extend `StoreAccountant\Registry` and implement
`StoreAccountant\Contract\RegistryInterface`.

A registry exposes two methods:

- `get(string $id): ?object` for one specific object.
- `get_all(): array` for all known objects keyed by ID.

Because values may come from external code through WordPress hooks, registries
validate types and only accept objects that implement
`StoreAccountant\Contract\RegistryItemInterface`, implement the expected concrete
interface, and return a non-empty ID.

Core registers extension hooks with priority `100`. Lower priorities run before
core, higher priorities run after core. When registering services into array
based registries, add-ons should append or replace by ID:

```php
$items['my_id'] = $my_service;

return $items;
```

For chain-style providers, all registered services are processed in priority
order. If two providers define the same field ID or value, the later provider
can override the earlier value. For single-resolution lookups, StoreAccountant
chooses the last matching service so the highest relevant priority wins.

All ID-based registries keep only one service per ID. If a higher-priority
filter registers the same ID again, that later service replaces the earlier one
and is moved to the later priority position.

## Hook Naming Conventions

Public extension hooks are prefixed with `storeaccountant_` and use singular
domain names for the object being extended. Field providers and field value
providers use shared hooks because providers decide applicability through
`supports()` and can be reused across export types:

```text
storeaccountant_export_field_provider
storeaccountant_export_field_value_provider
```

Adapter IDs may still use plural nouns such as `orders`, `customers`, or
`products`.

Field value mutators use the shared field-level hook
`storeaccountant_export_field_value_mutator` for the same reason.

Export attachment providers use the shared hook
`storeaccountant_export_attachment_provider`. They can add files to the generated
export archive without changing the main dataset renderer.

Export filters use shared hooks because filters decide applicability through
`supports()` and can be reused across export types. Runtime filters register on
`storeaccountant_export_filter`; matching admin fields register on
`storeaccountant_export_filter_field_provider`. Period resolvers used by
date-like filters register on `storeaccountant_export_filter_period_provider`.

Invoice plugin integrations use the shared invoice hook
`storeaccountant_invoice_plugin`. Invoice-related field providers live below the
invoice namespace and register into the main export field hooks, so invoice
features can contribute fields to `orders`, `customers`, or future export types
without living inside those export adapters. StoreAccountant exposes only one
enabled invoice plugin integration at a time from plugin settings.

Hooks that extend saved export configuration screens use
`storeaccountant_export_configuration_*`. Storage hooks intentionally use
`storeaccountant_storage_*` because storage adapters are shared infrastructure
rather than dataset export adapters.

Hooks that extend saved export read views use
`storeaccountant_export_read_*`. They are shared across export types; individual
providers decide whether they support the current export record.

Hooks that extend the plugin settings page use
`storeaccountant_plugin_settings_*`. Plugin settings tab providers are intended
for add-ons that need their own settings pages in the StoreAccountant settings
area.

Permission actions use the shared hook `storeaccountant_permission_action`.
Actions should describe a concrete admin operation such as viewing a page,
saving a tab, or running a custom export button. Role lists shown in the
settings UI can be adjusted with `storeaccountant_assignable_permission_roles`;
only roles intended for wp-admin access should be exposed there.

## Hook Overview

| Hook | Purpose | Documentation |
| --- | --- | --- |
| `storeaccountant_export_completed` | Fires after a saved export has been successfully finalized and marked completed. | [Events](events.md) |
| `storeaccountant_export_adapter` | Registers dataset-producing export adapters. | [Export Adapters](extension-points/export-adapters.md) |
| `storeaccountant_export_configuration_form_field_provider` | Registers additional form fields for saved export configurations. | [Export Configuration Form Field Providers](extension-points/export-configuration-form-field-providers.md) |
| `storeaccountant_export_configuration_tab_provider` | Registers additional tabs for saved export configurations. | [Export Configuration Tab Providers](extension-points/export-configuration-tab-providers.md) |
| `storeaccountant_export_read_tab_provider` | Registers additional tabs for saved export read views. | [Export Read Tab Providers](extension-points/export-read-tab-providers.md) |
| `storeaccountant_plugin_settings_tab_provider` | Registers additional tabs for the plugin settings page. | [Plugin Settings Tab Providers](extension-points/plugin-settings-tab-providers.md) |
| `storeaccountant_export_attachment_provider` | Registers additional files for generated export archives. | [Export Attachment Providers](extension-points/export-attachment-providers.md) |
| `storeaccountant_export_filter` | Registers runtime filters for export source queries. | [Export Filters](extension-points/export-filters.md) |
| `storeaccountant_export_filter_field_provider` | Registers admin form fields for export filters. | [Export Filters](extension-points/export-filters.md) |
| `storeaccountant_export_filter_period_provider` | Registers reusable period resolvers used by date filters. | [Export Filters](extension-points/export-filters.md) |
| `storeaccountant_export_field_provider` | Registers field definitions for export datasets. | [Field Providers](extension-points/field-providers.md) |
| `storeaccountant_export_field_value_provider` | Registers value resolvers for export datasets. | [Field Value Providers](extension-points/field-value-providers.md) |
| `storeaccountant_export_field_value_mutator` | Registers reusable field value mutators for export datasets. | [Field Value Mutators](extension-points/field-value-mutators.md) |
| `storeaccountant_export_order_tax_field_provider` | Registers selectable tax field strategies for WooCommerce order exports. | [Order Tax Field Providers](extension-points/order-tax-field-providers.md) |
| `storeaccountant_export_period_field_provider` | Replaces the period form provider. | [Period Providers](extension-points/period-providers.md) |
| `storeaccountant_export_period_view_provider` | Replaces the period display provider. | [Period Providers](extension-points/period-providers.md) |
| `storeaccountant_export_renderer` | Registers renderers for export formats such as CSV, JSON, or XML. | [Export Renderers](extension-points/export-writers.md) |
| `storeaccountant_invoice_plugin` | Registers invoice plugin integrations. | [Invoice Plugins](extension-points/invoice-plugins.md) |
| `storeaccountant_storage_adapter` | Registers storage destinations such as local zip, S3, or SFTP. | [Storage Adapters](extension-points/storage-adapters.md) |
| `storeaccountant_permission_action` | Registers permission-controlled admin actions. | [Permissions](permissions.md) |
| `storeaccountant_assignable_permission_roles` | Filters backend roles shown in the permission assignment UI. | [Permissions](permissions.md) |
| `storeaccountant_queue_transport_provider` | Registers Symfony Messenger queue transport providers for background work. | [Queue Transports](extension-points/queue-transports.md) |
| `storeaccountant_export_batch_size` | Filters the saved batch size before export queue batches are enqueued. The configured export value remains the default. | [Queue Transports](extension-points/queue-transports.md) |
| `storeaccountant_export_queue_debug_delay_seconds` | Adds an optional per-step delay for export queue debugging and polling tests. Default is `0`; production code should leave this unset. | [Queue Transports](extension-points/queue-transports.md) |
| `storeaccountant_export_polling_scheduled_window_seconds` | Filters how close a scheduled export run must be before the admin export overview polls it. Default is five minutes. | [Queue Transports](extension-points/queue-transports.md) |
| `storeaccountant_export_log_entry_limit` | Filters how many export log entries are retained on each export record. | [Events](events.md) |

## Extension Point Reference

- [Export Adapters](extension-points/export-adapters.md)
- [Export Configuration Form Field Providers](extension-points/export-configuration-form-field-providers.md)
- [Export Configuration Tab Providers](extension-points/export-configuration-tab-providers.md)
- [Export Read Tab Providers](extension-points/export-read-tab-providers.md)
- [Plugin Settings Tab Providers](extension-points/plugin-settings-tab-providers.md)
- [Export Attachment Providers](extension-points/export-attachment-providers.md)
- [Export Filters](extension-points/export-filters.md)
- [Export Renderers](extension-points/export-writers.md)
- [Field Providers](extension-points/field-providers.md)
- [Field Value Providers](extension-points/field-value-providers.md)
- [Field Value Mutators](extension-points/field-value-mutators.md)
- [Invoice Plugins](extension-points/invoice-plugins.md)
- [Order Tax Field Providers](extension-points/order-tax-field-providers.md)
- [Period Providers](extension-points/period-providers.md)
- [Queue Transports](extension-points/queue-transports.md)
- [Storage Adapters](extension-points/storage-adapters.md)
- [Permissions](permissions.md)
