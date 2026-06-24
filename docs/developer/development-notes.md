# Development Notes

These notes describe the current development approach and technical decisions in
the plugin.

## Code Style

- New PHP files belong below `src`.
- New PHP files need a file docblock, `declare(strict_types=1);`, and the
  WordPress direct-access guard after namespace/use statements.
- PHP 8.2 features may be used.
- Service dependencies are injected through constructor property promotion.
- Services use private properties and no trivial getters/setters.
- Value objects and DTOs should preferably be `readonly final class` with public
  promoted properties.
- When a class is already `readonly`, do not mark promoted constructor
  properties as `readonly` again.
- Use short array syntax `[]` in new code.
- User-facing strings in code stay in English and use the `storeaccountant` text
  domain.
- For normal UI strings, pass the natural English text to WordPress translation
  functions, for example `__( 'Export Format', 'storeaccountant' )`. Do not
  replace regular labels with opaque source keys such as `export_format_label`.
- Synthetic translation keys are allowed only for values that are selected by
  runtime IDs, for example `export_adapter_` plus a registered adapter ID,
  `exporter_` plus a renderer ID, or `storage_adapter_` plus a storage adapter
  ID. Built-in dynamic keys must be kept discoverable through static translation
  calls such as the catalogue entries in `src/I18n.php`.
- Keep `languages/storeaccountant-en_US.po` and
  `languages/storeaccountant-en_US.mo` complete alongside the German files.
  English source strings still belong in PHP, but the English catalogue gives
  dynamic keys readable labels and gives translators a complete source-aligned
  file to work from.
- Namespaced PHP files should import global functions with `use function ...;`.
  Keep using this convention for PHP built-ins and WordPress/WooCommerce global
  functions instead of relying on implicit namespace fallback or leading-slash
  calls.
- `use function` only resolves the function name; it does not load optional
  WordPress helper files. Queue, cron, REST, AJAX, and CLI code must explicitly
  load optional admin includes before calling helpers that live there, such as
  `wp-admin/includes/file.php` for `wp_tempnam()`.
- Prefer WordPress helper APIs over direct PHP alternatives when a suitable
  WordPress API exists. This applies especially to filesystem access,
  sanitization, escaping, redirects, uploads, HTTP requests, dates, and database
  access. For example, use the WordPress Filesystem API for regular file
  contents and deletion instead of direct `file_put_contents()` or `unlink()`.
- Treat `phpcs:ignore` as a last resort. Before adding one, verify whether the
  warning can be resolved through a WordPress API, input validation,
  sanitization, escaping, or a small code structure change. If an ignore is
  still necessary, document why the warning cannot be fixed directly and ask the
  project owner for confirmation before committing the ignore.

## WooCommerce HPOS Compatibility

All future order-related features must stay compatible with WooCommerce
High-Performance Order Storage (HPOS). StoreAccountant declares compatibility with
WooCommerce custom order tables in the plugin bootstrap, so new code must not
introduce direct WordPress post or postmeta access for WooCommerce orders.

Use WooCommerce APIs for order data:

- Query orders with `wc_get_orders()` or `WC_Order_Query`.
- Load a single order with `wc_get_order()`.
- Read order fields through `WC_Order` getters.
- Read custom order metadata with `$order->get_meta()`.
- Write custom order metadata with `$order->update_meta_data()` or related
  `WC_Order` meta methods, followed by `$order->save()` when persistence is
  required.

Before declaring a new order feature done, audit it against WooCommerce's HPOS
search terms such as `wpdb`, `get_post_meta`, `update_post_meta`, `get_posts`,
`WP_Query`, and `shop_order`. Matches are allowed for StoreAccountant's own custom
post types, export records, and configuration records, but not for WooCommerce
order records.

## Container and Services

The container is configured in `src/ContainerBuilder.php`. Not every class is a
service:

- Services with behavior and dependencies belong in the container.
- Value objects such as `ExportPeriod` do not belong in the container.
- Classes that register WordPress hooks implement `HookRegistrarInterface` and
  are listed in `ContainerBuilder::HOOK_SERVICES`.

## Admin UI

StoreAccountant registers its own top-level `Accounting` admin menu. The visible
sidebar item is `Exports`, which links to the generated export records and keeps
the existing `Exports` / `Export Configurations` tab navigation intact.

StoreAccountant intentionally uses native WordPress custom post type list tables
for exports and export configurations. This keeps search, pagination, trash, and
bulk deletion available.

Custom actions are injected above the native list through `AccountingHeaderBar`.
The custom post types themselves are hidden from the default sidebar placement
and are surfaced through the dedicated accounting menu entries.

The export overview exposes export creation through a server-side form. The
first selector option opens the `Quick Export` page; saved export configuration
options submit directly and create an export from the selected configuration.
Plugin settings currently include `Storage Locations`, `Transports`,
`Integrations`, `Security`, and `Permissions` tabs.

The export overview polls visible saved export run rows through
`admin-ajax.php` while a run can still change soon. Polling is scoped to
`storeacct_export` records, not export configurations. Rows with `queued` or
`processing` status are always pollable. Rows with `scheduled` status are only
pollable when the export run stores `_storeaccountant_scheduled_for` and that
time is within the configured scheduled polling window. Completed and failed
exports are terminal and are removed from polling.

The polling endpoint returns row DTOs with translated status and progress labels,
frontend download URLs, and pollability decisions. PHP remains responsible for
download capabilities; `assets/js/export-list-polling.js` only updates marked
DOM cells. When a polled export becomes completed and a generated file is still
available in storage, the list row shows the Download button without requiring a
page refresh.

Export download URLs contain only the public download token. Frontend downloads
must verify the export password against the stored password hash and stream the
file returned by `StorageAdapterInterface::get_file()`. Download code must stay
adapter-agnostic and must not infer local uploads paths from stored references.

## Export Filters

Exports and export configurations store source filters as JSON. Date ranges are
not generic export metadata. Use filter providers for export-type specific
selection, for example order date and order status filters for the `orders`
export adapter.

Runtime filters register through `storeaccountant_export_filter`. Admin field
providers register through `storeaccountant_export_filter_field_provider`. Period
resolvers used by date-like filters register through
`storeaccountant_export_filter_period_provider`.

## Server-side Validation

Forms must not rely on client-side validation.

Current state:

- Export configurations require a title server-side.
- Exports started from a saved configuration require a non-empty, unique title
  submitted from the export overview form.
- Filter values are validated server-side by filter field providers.
- Order status filters are loaded from `wc_get_order_statuses()` and validated
  server-side against the available WooCommerce statuses.
- Export format must exist in the export adapter registry.
- Storage adapter must be registered and enabled.

The free month/year period resolver used by the order date filter accepts only:

- `all_time`
- `current_month`
- `last_month`
- month values `1` to `12`
- years inside the calculated range from the last ten years up to the current
  year

Future month values in the current year are rejected.
The `all_time` selection covers the beginning of the selectable ten-year window
through the current shop time.

Date filter periods are selected in the shop timezone from `wp_timezone()` and
resolved as UTC datetimes. During export generation those UTC datetimes are
converted to timestamps on `WC_Order_Query`, so WooCommerce compares the
selected date field against GMT storage columns with second precision.

## Export and Configuration Relations

An export can have a nullable relation to an export configuration:

- Quick export: do not store a configuration relation.
- Export by configuration: store the configuration ID on the export.

The export overview renders that relation in the `Configuration` column.
Existing configurations are linked to their edit form. Quick exports without a
configuration relation are displayed as `Quick Export`; relations to deleted
configurations are retained and displayed as deleted.

Quick exports and saved export configurations store filter selections. When a
configuration is used to start an export, its filter selection is copied to the
generated export record and dynamic period selections are snapshotted.

When an export configuration is deleted, existing exports must be kept. The
stored relation on the export is retained so the export overview can indicate
that the related configuration was deleted.

Export configurations are edited through the custom StoreAccountant configuration
form so title, filters, export format, storage adapter, and additional provider
settings stay in sync. Native list edit links are redirected to that custom
form.

When an export is deleted, its export configuration must not be deleted.

Manual export starts must create a `storeacct_export` record and dispatch queue
work through Symfony's `MessageBusInterface`. Admin form handlers should not
call `StoreAccountant\Export\Exporter::export()` directly. Export-specific queue
messages and handlers belong below `StoreAccountant\Export\Queue`, while
reusable queue infrastructure and transports belong below
`StoreAccountant\Queue`. Queue messages should be plain serializable DTOs, and
handlers should be invokable callables registered in Symfony's
`HandlersLocatorInterface`.

Batch-capable export adapters implement
`StoreAccountant\Export\Contract\BatchExportAdapterInterface`. Batch handlers
load deterministic source slices, normalize them, and store temporary fragments
through `StoreAccountant\Export\Queue\BatchExportStore`. Finalization streams
those fragments into the configured renderer and persists the resulting artifact
through the selected storage adapter. Do not add custom database tables for
batch state.

Batch size is configured per export configuration and copied to each export
record when the export is created. Quick exports store their submitted batch
size directly on the export record. The queue start handler must use the saved
export batch size rather than a global setting so retries remain stable.
User-submitted batch sizes must be numeric and at least `10`; the default is
`50`.

Scheduled exports are not currently offered in the admin UI and are not
configured on export configuration records. When scheduled export workflows
return, they should use a focused scheduling model. A scheduled run should still
create a normal `storeacct_export` record, snapshot the configuration state
needed for execution, and dispatch the same export queue message used by manual
exports.

## Storage and Paths

Storage adapters expose a small StoreAccountant API: ID, `persist()`,
`delete_file()`, directory operations, visibility operations, `file_exists()`,
file metadata getters, `get_file()`, and readiness checks. Export adapters generate temporary files; storage adapters
decide how those files are persisted, checked, and later opened for downloads.
They do not need to expose Flysystem methods or derive folder/file names from
export titles. `ExportStoragePathGenerator` builds the storage reference before
the adapter is called and formats display paths. `persist()` receives a
`StorageFileConfiguration` value object with the generated source path, storage
reference, target file name, and optional internal archive path.

The local adapter receives `LocalStorageConfiguration` for backend-specific
root, archive, and display paths instead of resolving upload paths itself.
Local zip storage references point to the archive itself, for example
`exports/accounting-may-2026.zip`. The optional internal archive path is used
only while writing the generated CSV/XML into the archive.
Downloads for local exports return the generated zip archive, not the inner CSV
or XML file stored inside the archive.

Local exports use the `local` storage adapter and are stored in a Flysystem zip
archive below. The local adapter implements Flysystem internally and decorates
League's ZipArchive adapter. The archive file name is part of the generated
storage reference:

```text
wp-content/uploads/storeaccountant/exports/accounting-may-2026.zip
```

Saved export titles must be unique across export custom post type records. The
admin create flow validates this before creating the export record so local zip
generated file names do not need an ID suffix.

Absolute server paths must not be printed in admin notices. Saved export meta
stores paths inside the selected storage adapter, such as
`exports/accounting-may-2026.zip`;
user-facing local display paths start with `wp-content/...`.

When the local storage root is created, `LocalStorageAdapter` must create
`.htaccess` and `index.html`. On plugin deactivation, the directory may only be
removed when it does not contain foreign files, subdirectories, or the generated
zip archive.

## wp-env Setup

The local WordPress environment is configured in `.wp-env.json` in the plugin
repository root. The plugin is mapped into the container at:

```text
/var/www/html/wp-content/plugins/storeaccountant
```

Run `wp-env` commands from the plugin repository root. The development
environment uses the `cli` environment for WP-CLI, PHP, and Composer commands.

Start the WordPress environment:

```sh
wp-env start --config=.wp-env.json
```

Stop the WordPress environment:

```sh
wp-env stop
```

Run a WP-CLI command:

```sh
wp-env run cli wp plugin list
```

Activate the StoreAccountant plugin:

```sh
wp-env run cli wp plugin activate storeaccountant
```

Run Composer for this plugin:

```sh
wp-env run cli composer install --working-dir=/var/www/html/wp-content/plugins/storeaccountant
```

Require a Composer package for this plugin:

```sh
wp-env run cli composer require vendor/package --working-dir=/var/www/html/wp-content/plugins/storeaccountant
```

## Verification

After PHP changes, run at least `php -l` through the `wp-env` CLI environment
from the plugin repository root:

```sh
wp-env run cli php -l /var/www/html/wp-content/plugins/storeaccountant/src/ChangedFile.php
```

When adding or changing translatable strings:

```sh
wp-env run cli wp i18n make-pot /var/www/html/wp-content/plugins/storeaccountant /var/www/html/wp-content/plugins/storeaccountant/languages/storeaccountant.pot --exclude=vendor,languages --skip-js
wp-env run cli wp i18n update-po /var/www/html/wp-content/plugins/storeaccountant/languages/storeaccountant.pot /var/www/html/wp-content/plugins/storeaccountant/languages/storeaccountant-de_DE.po
wp-env run cli wp i18n make-mo /var/www/html/wp-content/plugins/storeaccountant/languages /var/www/html/wp-content/plugins/storeaccountant/languages
```
