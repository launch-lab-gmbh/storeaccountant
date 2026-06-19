# Architecture

This document describes the current technical state of StoreAccountant.

## Pre-1.0 Development Status

StoreAccountant is still below version `1.0`. The architecture, extension APIs,
hooks, stored meta keys, and service boundaries may change during this
development phase without backward-compatibility guarantees.

## Bootstrap and Container

`storeaccountant.php` is only the bootstrap:

- Plugin metadata and constants.
- WooCommerce HPOS compatibility declaration for custom order tables.
- `StoreAccountant::PLUGIN_VERSION`.
- `StoreAccountant::PHP_VERSION`, currently `8.2`.
- Composer autoloader.
- Activation and deactivation hooks.
- Start of `StoreAccountant\Plugin`.

`StoreAccountant\Plugin` builds the container through
`StoreAccountant\ContainerBuilder` and registers all services from
`ContainerBuilder::HOOK_SERVICES` when they implement `HookRegistrarInterface`.

Value objects such as `ExportPeriod` are intentionally not registered as
container services.

## WooCommerce HPOS Compatibility

StoreAccountant declares compatibility with WooCommerce High-Performance Order
Storage (HPOS) through `FeaturesUtil::declare_compatibility()` for the
`custom_order_tables` feature. Future order-related features must remain HPOS
compatible by using WooCommerce order APIs instead of direct WordPress post or
postmeta access for order data.

Order data must be loaded through APIs such as `wc_get_order()`,
`wc_get_orders()`, `WC_Order_Query`, or `WC_Order` methods. Order metadata must
be read and written through the WooCommerce order object, for example
`$order->get_meta()`, `$order->update_meta_data()`, and `$order->save()`.
Direct `get_post_meta()`, `update_post_meta()`, `WP_Query`, direct SQL, or
`shop_order` post assumptions are acceptable only for StoreAccountant's own
custom post types and never for WooCommerce order records.

## Main Areas

- `src/Admin`: shared WordPress admin shell code such as the top-level menu,
  header bar, and admin assets.
- `src/*/Admin`: feature-specific admin pages, forms, settings sections, tabs,
  notices, and field providers. Removing a feature namespace should also remove
  its admin UI.
- `src/Export`: export custom post type, export orchestration, export adapter
  registry, renderer registry, field provider registry, export repository,
  datasets, and export value objects.
- `src/Queue`: Symfony Messenger bus adapter and transport infrastructure.
- `src/Export/Queue`: export-specific queue messages, handlers, and cleanup.
- `src/Schedule`: reserved for future recurring export setup and cron-style
  execution. Scheduled exports are not currently offered in the admin UI and are
  not configured on export configuration records.
- `src/Order/Export/Adapter`: concrete order data adapters.
- `src/Export/Filter`: export source filters, filter field provider registries,
  period resolvers used by date-like filters, and filter selection value
  objects.
- `src/Export/Field`: field definitions, field values, field mappings,
  reusable metadata field helpers, and type-specific field providers and
  mutators. Current order-specific classes live below `src/Order/Export`; the
  customer export lives below `src/Customer/Export`.
- `src/Export/Renderer`: concrete export renderers such as CSV and serializer-backed formats.
- `src/Export/Configuration`: export configuration custom post type,
  repository, and additional configuration field providers.
- `src/Invoice`: invoice plugin integrations and invoice-related export
  providers. Order-facing invoice providers live below
  `src/Invoice/Export/Order` and register into the main export hooks.
- `src/Storage`: storage adapter registry, storage adapter contracts, and
  Flysystem-backed storage adapters.

## Data Model

### Accounting Exports

Saved exports use the custom post type `storeacct_export`.

Important meta fields:

- `_storeaccountant_exported_at`
- `_storeaccountant_status`
- `_storeaccountant_filters`
- `_storeaccountant_storage_engine`
- `_storeaccountant_export_adapter`
- `_storeaccountant_export_writer`
- `_storeaccountant_batch_size`
- `_storeaccountant_path`
- `_storeaccountant_triggered_by`
- `_storeaccountant_configuration_id`
- `_storeaccountant_download_token`
- `_storeaccountant_download_password`
- `_storeaccountant_download_password_hash`
- `_storeaccountant_total_items`
- `_storeaccountant_processed_items`
- `_storeaccountant_total_batches`
- `_storeaccountant_processed_batches`
- `_storeaccountant_failed_batches`
- `_storeaccountant_current_step`
- `_storeaccountant_error_message`
- `_storeaccountant_started_at`
- `_storeaccountant_finished_at`

Export lifecycle statuses are stored in `_storeaccountant_status`:

- `queued`
- `processing`
- `completed`
- `failed`

Exports are conceptually immutable: edit and quick-edit actions are removed,
bulk edit is removed, restoring from trash sets the post back to `publish`, and
native draft state labels are hidden.

Export title and row view links point to a custom read-only StoreAccountant
admin page. Its tabs are provided through
`storeaccountant_export_read_tab_provider`; providers decide whether they
support the current export by inspecting stored metadata such as the export
adapter ID. The built-in read tabs are `Export Details` and `Raw Data`.

When an export is permanently deleted, StoreAccountant resolves the saved storage
adapter and asks it to delete the stored path. Missing or already removed files
are ignored so the export record can still be deleted cleanly.

Each export run stores a random public download token and a snapshot of the
effective download password. The snapshot stores both an encrypted reversible
value for authorized backend reveal screens and a separate verification hash for
frontend password checks. Configuration records store their own download
password snapshot; saving a configuration with an empty password field stores
the current global download password on that configuration.

### Export Configurations

Saved export configurations use the custom post type `storeacct_config`.

Important meta fields:

- `_storeaccountant_config_filters`
- `_storeaccountant_config_export_adapter`
- `_storeaccountant_config_export_writer`
- `_storeaccountant_config_storage_engine`
- `_storeaccountant_config_batch_size`
- `_storeaccountant_config_order_tax_field_provider`
- `_storeaccountant_config_additional_settings`
- `_storeaccountant_config_field_mapping`
- `_storeaccountant_config_download_password`
- `_storeaccountant_config_download_password_hash`

Export configurations can be edited through the StoreAccountant configuration
form. They store source filters as JSON in `_storeaccountant_config_filters`.
Date ranges are not part of the generic configuration model; they are optional
filter settings for export types that need them. When a configuration is used
to start an export, dynamic filter settings such as `Last Month` are resolved
and snapshotted into the generated export's `_storeaccountant_filters` metadata.
They can also be deleted. When an export configuration is deleted, existing
exports are kept and retain their stored relation so the export overview can
show that the related configuration was deleted.

Saved configurations can have type-specific detail tabs. Additional tabs are
registered through `storeaccountant_export_configuration_tab_provider`; providers
decide whether they support the current configuration by inspecting stored
metadata such as the export adapter ID. The built-in order configuration tab is
`Field Mapping`; the built-in customer export has its own `Field Mapping` tab
using the same shared mapping repository.

Field mapping is stored as JSON in `_storeaccountant_config_field_mapping`. Each
mapping item stores at least `field_id`, `enabled`, `label`, and `options`.
When no mapping exists yet, all available fields are treated as enabled and use
their provider-defined labels. Newly added provider fields that are missing from
an existing mapping are also enabled by default. Stored mapping entries whose
field IDs are no longer available are ignored at render and export time. This
keeps old configurations safe when a provider is disabled or an integration is
no longer active; the stored mapping can become effective again if the provider
later becomes available.

## Admin UI

StoreAccountant registers its own top-level admin menu named `Accounting`,
translated as `Buchhaltung` in German. Its first visible submenu item is
`Exports`, translated as `Exporte` in German.

StoreAccountant admin access is controlled through permission actions backed by
WordPress capabilities. The administrator role always has all StoreAccountant
permissions. Other backend roles can be assigned to individual actions through
the `Permissions` tab on the plugin settings page. See
[Permissions](permissions.md) for the action model, role assignment rules, and
extension examples.

StoreAccountant uses the native WordPress custom post type list tables so search,
pagination, trash, and bulk actions remain available. Above those tables,
StoreAccountant renders custom action areas and tabs:

- Tab `Exports`: native list for `storeacct_export`.
- Tab `Export Configurations`: native list for `storeacct_config`, but
  without its own visible sidebar menu item.
- `Create New Export`: selector on the export overview with `Quick Export` as
  the first option and saved export configurations below it. Choosing
  `Quick Export` opens the quick export form. Choosing a saved configuration
  shows an export name field and starts an export from that configuration after
  submitting a unique name.
- `Create Export Configuration`: custom admin page with a form. After saving,
  the user lands on the saved configuration detail page with `Configuration`
  and any type-specific tabs such as `Field Mapping`.

Both quick exports and exports started from a saved configuration create a new
export record. Configuration-based exports require a unique user-entered export
title. The export list shows the selected configuration name; quick exports
without a configuration relation are shown as `Quick Export`.

The list view polls queued and processing exports. It also contains preparatory
polling support for future scheduled export run records that store
`_storeaccountant_scheduled_for`; no current admin workflow creates those
records.

Filter fields are rendered by providers registered through
`storeaccountant_export_filter_field_provider`. Providers decide support per
export type, so an order export can require date and status filters while a
future customer export may offer no date filter at all.

Form content is displayed in white panels with a grey WordPress-style border.
For native tabs, the top panel border is removed to avoid a double line.

## Export Generation

`StoreAccountant\Export\Exporter` orchestrates export generation:

1. Read the selected export adapter and saved filter selections from export
   meta.
2. Resolve the selected export adapter through `ExportAdapterRegistry`.
3. Resolve the export renderer through `ExportRendererRegistry`.
4. Resolve the storage adapter through `StorageAdapterRegistry`.
5. Pass an `ExportPayload` with export type and filter selections to the export
   adapter.
6. Ask `ExportDatasetBuilder` to build an `ExportDataset` from the export adapter.
7. Ask the renderer to turn that dataset into an `ExportArtifact`.
8. Build the storage reference through `ExportStoragePathGenerator`.
9. Ask the storage adapter to persist the generated file and attachments with a
   `StorageFileConfiguration`.
10. Store the internal storage path on the export record.

The built-in order export adapter is `OrderExportAdapter`; it has the adapter ID
`orders`, loads WooCommerce orders, and provides an `ExportContext`, additional
tax context such as known tax rates, and record IDs. The built-in customer export adapter is
`CustomerExportAdapter`; it has the adapter ID `customers` and loads
WooCommerce customers through customer/user APIs. The built-in product export
adapter is `ProductExportAdapter`; it has the adapter ID `products`, loads
WooCommerce products through WordPress/WooCommerce product APIs, and can include
product variations as separate rows when the product variant export setting is
enabled. `ExportContext` carries typed
runtime data such as `export_type`, `configuration_id`, source items, and
optional adapter values readable through `$context->get( 'tax_rates', [] )`.
The shared field pipeline lives above concrete adapters in
`ExportDatasetBuilder`. It collects hookable field providers through
`ExportFieldResolver` and
`FieldProviderRegistry`; providers decide support through `supports()`. Values
are resolved separately through field value providers collected through
`FieldValueProviderRegistry`; value providers also decide support per context
and field. Field value mutators are collected through
`FieldValueMutatorRegistry` and run after value providers. Mutators are
chain-style: each mutator receives the `FieldValue` returned by the previous
mutator.

The dataset builder prepares a `FieldCollection` once, applies any saved field
mapping for configuration-based exports, and passes the mapped collection with
each source item through the value provider chain. Field mapping can disable
fields and override labels; records still only carry field IDs and values. This
keeps CSV, XML, XLSX, and future renderers independent from WooCommerce-specific
data collection. The bundled CSV renderer turns the dataset into a temporary CSV
file.

Order source querying uses `WC_Order_Query`. Runtime filters are registered
through `storeaccountant_export_filter` and receive the query object through an
`apply()` method. The built-in `order_date` filter applies a selected month/year
period to one WooCommerce date query field such as `date_created`,
`date_modified`, `date_completed`, or `date_paid`. The built-in `order_status`
filter applies selected WooCommerce order statuses. Periods used by date-like
filters are selected in the shop timezone, resolved to UTC, and converted to
timestamps for WooCommerce order query arguments.

Export attachment providers are collected through
`ExportAttachmentProviderRegistry` via `storeaccountant_export_attachment_provider`.
They run while the dataset builder still has access to each source item and add
streamed `ExportAttachment` files to the dataset. Renderers ignore attachments;
storage adapters receive them through `StorageFileConfiguration`. The bundled
local storage adapter stores attachments as additional entries inside the same
zip archive as the generated export file. Attachment providers declare a base
directory for their files and can place concrete attachments in nested
subdirectories.

Reusable tax provider contracts and built-in tax field providers live below the
top-level `StoreAccountant\Tax` namespace so the module can grow beyond order
exports later. The current order tax field providers live below
`StoreAccountant\Tax\Field\Provider`. Order-specific selection and WooCommerce
order tax rate discovery live below `StoreAccountant\Order\Tax` through
`OrderTaxFieldProviderRegistry` and `OrderTaxRateResolver`. Selectable tax field
providers are collected through the registry and also register into the regular
export field provider hook. The built-in `extended` provider creates separate
tax item, shipping, and total fields per WooCommerce tax rate. The built-in
`simple` provider aggregates tax values into two compact columns. Saved order
export configurations store the selected tax field provider, the admin tax field
provider field renders that selection, and `OrderTaxFieldValueProvider` resolves
the selected provider's values through the normal field value provider pipeline.

Field and field value providers use shared extension hooks, even though the
built-in adapter ID is plural `orders`:

```text
storeaccountant_export_adapter
storeaccountant_export_attachment_provider
storeaccountant_export_configuration_form_field_provider
storeaccountant_export_configuration_tab_provider
storeaccountant_export_read_tab_provider
storeaccountant_export_filter
storeaccountant_export_filter_field_provider
storeaccountant_export_filter_period_provider
storeaccountant_export_field_provider
storeaccountant_export_field_value_provider
storeaccountant_export_field_value_mutator
storeaccountant_export_order_tax_field_provider
storeaccountant_export_period_field_provider
storeaccountant_export_period_view_provider
storeaccountant_export_renderer
storeaccountant_export_batch_size
storeaccountant_export_queue_debug_delay_seconds
storeaccountant_export_log_entry_limit
storeaccountant_export_polling_scheduled_window_seconds
storeaccountant_invoice_plugin
storeaccountant_permission_action
storeaccountant_plugin_settings_tab_provider
storeaccountant_queue_transport_provider
storeaccountant_storage_adapter
```

Field value mutators use the shared hook
`storeaccountant_export_field_value_mutator`, because their `supports()` method
decides whether they apply to a mapped field.

The field resolver always moves metadata-backed custom fields to the end of the
available field collection before applying saved field mappings. For order
exports this means order tax fields remain before custom order fields. For
customer and product exports it means custom metadata fields are also appended
after all fixed fields.

The shared metadata helper classes live below `StoreAccountant\Export\Field\Meta`.
Order, customer, and product metadata providers use these helpers to discover
scalar WooCommerce metadata keys from the current export context, create stable
field IDs with type-specific prefixes such as `order_meta_`, `customer_meta_`,
and `product_meta_`, and format metadata values. Entity-specific providers still
own their reserved metadata key lists because built-in fields differ.

The order dataset contains accounting-relevant order, billing, total, fee,
shipping, tax, and custom metadata fields. Invoice fields are intentionally outside the core
order field provider and live in `StoreAccountant\Invoice\Export\Order\Field`.
The bundled
WooCommerce PDF Invoices & Packing Slips integration registers itself through
`storeaccountant_invoice_plugin` with the plugin ID
`woocommerce-pdf-invoices-packing-slips`. Administrators enable at most one
active invoice plugin integration on the plugin settings page. The generic
invoice field provider contributes `invoice_number`, `invoice_date`, and
`invoice_file_name` for the `orders` export type only when an invoice plugin
integration is enabled, and its value provider reads through that enabled
integration. The bundled integration reads the invoice document API without
creating a missing invoice, with stored order metadata as fallback. It also
exposes invoice files through `get_invoice_file()` for workflows that need the
source document rather than a CSV-safe field value. The WooCommerce PDF Invoices
& Packing Slips integration offers PDF files and, when the plugin's EDI/XML
feature is available, XML e-invoices. Invoice file types are selected per export
configuration.
Customer exports include customer identity fields, billing and shipping address
fields, order count, total spent, date fields, and scalar custom customer
metadata fields not already covered by dedicated built-in fields. Customer date
filters use the customer creation date. The shared month/year period selector
supports all time, this month, last month, and concrete month/year selections
for order and customer exports. The customer country filter can restrict exports
by billing or shipping country; selecting all countries leaves the export
restricted to customers with an assigned country code, and selecting
`Unassigned` also includes customers where the selected country field is missing
or empty. Combining all countries with unassigned covers all WooCommerce
customers in the export period. The country selector only lists countries found
on WooCommerce customers with at least one order and promotes the shop base
country when available. Customer queries are intentionally not limited to the WordPress
`customer` role, because WooCommerce customers can also have roles such as
administrator or shop manager. Instead, customer exports include only registered
users whose WooCommerce customer record has at least one order.

Product exports include product identity fields, parent product IDs and SKUs,
catalog status, prices, tax settings, stock data, dimensions, categories, tags,
attributes, variation attributes, descriptions, and scalar custom product
metadata fields not already covered by dedicated built-in fields. Product date
filters use the product creation date and currently expose all time, this month,
and last month selections. The product variant export setting defaults to parent
products only; when enabled, WooCommerce `product_variation` records are loaded
as separate export rows alongside products.

Selected invoice files are written below the localized invoice attachment
directory in the generated zip archive. In English this is `Invoices/{type}/`;
in German it is `Rechnungen/{type}/`. The `invoice_file_name` export field
lists the same file names used for those attachments. Extended tax
columns are based on all configured WooCommerce tax rates, not only rates
present in the exported orders. Their headers follow
`tax_{rate}_{tax-name-slug}_{country}_{items|shipping|total}`.

If an export configuration contains old invoice mapping entries but the selected
invoice plugin integration is no longer active or no longer enabled,
`InvoiceFieldProvider::supports()` returns false. The invoice fields are then no
longer part of the available `FieldCollection`, so the field mapping repository
omits those stored entries from the form and from generated export datasets.

Manual export creation now stores the export record first and dispatches a
queued background message instead of generating the file directly inside an
admin request. The queue infrastructure lives below
`StoreAccountant\Queue`; export-specific queue messages and handlers live below
`StoreAccountant\Export\Queue`.

Export start actions dispatch through Symfony's `MessageBusInterface`. The
StoreAccountant bus implementation sends normal message DTOs to the configured
transport. The default free transport is an
`action_scheduler://exports`-style Action Scheduler transport implemented below
`StoreAccountant\Queue\Transport`. It uses Symfony Messenger's transport
serializer to encode and decode envelopes. When Action Scheduler is unavailable,
the same transport falls back to WordPress cron for local development and
minimal environments. Export orchestration depends on Messenger and
Symfony's handler locator rather than direct Action Scheduler calls.

The current batch implementation uses:

- `StartExportMessage` to count source records and enqueue batches.
- `ProcessExportBatchMessage` to load deterministic order/customer slices,
  normalize those slices, persist temporary batch fragments, and update progress
  counters.
- `FinalizeExportMessage` to stream saved batch fragments into the configured
  renderer and persist the final export file through the selected storage
  adapter.

Orders and customers expose batch access through
`BatchExportAdapterInterface`. Batch queries remain HPOS-compatible by using
WooCommerce APIs for orders and WordPress/WooCommerce customer APIs for
customers. Temporary fragments are stored in a protected upload subdirectory and
are deleted after successful finalization or after stale processing jobs time
out. Final export records and generated files are never removed by automatic
queue cleanup.

Each export stores its own `_storeaccountant_batch_size`; saved export
configurations store `_storeaccountant_config_batch_size` and copy that value to
new exports created from the configuration. Quick
exports store the submitted batch size directly on the export record. The
minimum batch size is `10`; the default batch size is `100`.

Scheduled exports are not configured on export configuration records. Future
scheduled export workflows should use a focused scheduling model instead of
adding schedule controls back to the configuration form. Cleanup never deletes
completed export records or generated final files; it only marks stale
processing jobs as failed.

## Storage

The bundled local storage adapter keeps the ID `local` and writes through
`StoreAccountant\Storage\Adapter\LocalStorageAdapter`. Public storage adapters
only need to provide ID, persistence, deletion, download file retrieval,
file existence checks, and readiness checks.
`get_file()` returns a `StorageFile` stream descriptor so download handling can
stay storage-agnostic.
Folder, file names, and display paths are generated before the storage adapter is called.
The local adapter receives its root and display paths through
`LocalStorageConfiguration`.
Local zip storage references point to the archive itself, for example
`exports/{download-token}.zip`. The path of the generated CSV/XML inside the
archive is carried separately while persisting the file. Remote storage
references use the same token as the object key base, for example
`exports/{download-token}.csv`.
The local adapter additionally implements Flysystem's adapter interface
internally so it can decorate League's ZipArchive adapter.

The local storage root is:

```text
wp-content/uploads/storeaccountant
```

The local Flysystem adapter decorates League's ZipArchive adapter and stores
generated export files inside the zip archive referenced by the generated
storage path:

```text
wp-content/uploads/storeaccountant/exports/{download-token}.zip
```

Generated export files are downloaded through the frontend
`/storeaccountant/export-download/{token}/` endpoint or its query-argument
fallback. The endpoint resolves the export by token, verifies completed status,
checks the configured storage adapter, requires the export password, and then
streams the `StorageFile` returned by `StorageAdapterInterface::get_file()`.
It never exposes direct upload URLs or assumes local filesystem paths.

Saved export titles are validated as unique before the export custom post type
record is created. Storage file names are independent from titles and use the
download token so stored paths do not reveal accounting export names.

When the storage root is created, protection files similar to WooCommerce are
created by the local adapter:

- `.htaccess`
- `index.html`

Absolute server paths must not be displayed in admin error messages. Internal
export meta stores paths inside the storage adapter, for example
`exports/accounting-may-2026.zip`; admin display paths start with
`wp-content/...`.

On plugin deactivation, the directory is deleted only when it does not contain
foreign files or subdirectories. Managed protection files may be removed.
