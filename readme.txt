=== StoreAccountant ===
Contributors: launchlab
Tags: woocommerce, accounting, export, bookkeeping
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 0.3.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCommerce accounting workflow exports with persisted records and background processing.

== Description ==

StoreAccountant is a WordPress/WooCommerce plugin for accounting export workflows.
It creates saved export records for WooCommerce orders and customers, processes exports in the background, and stores generated files with protected download access.

= Core features =

- Advanced order and customer exports
- Configurable export formats (CSV, JSON)
- Password-protected export downloads
- Filters for export periods, order statuses, and customer countries
- Configurable columns and field mapping
- Support for custom order and customer fields
- Tax rate and tax amount fields for order exports
- Invoice fields and invoice PDF attachments when a supported invoice plugin is active
- Local and password-protected storage
- Asynchronous background processing for better performance

= How to configure exports =

After activation, go to Accounting > Exports in the WordPress admin area.
Use Create New Export for a quick one-time export, or create an export configuration that can be reused.

When creating an export, choose the export type, export format, storage location, filters, and field mapping.
StoreAccountant saves every export as a record in the admin area so you can review its status and download the generated file when processing is complete.

= General export features =

= Field mapping =
You can map fields from the WooCommerce order to the export. Renaming fields is also possible.
Value formatting is also supported, including date and time formatting, cents or full amounts, and similar export-specific options.
Custom fields can be included in the exported columns.

= Order exports =

= Filters =
Filter orders by date period and order status.

= Configurable columns =
You can choose which columns from an order should be in the export.

= Custom fields =
Custom fields will also be configurable and taken into account in the export.

= Tax fields =
Add tax rates and tax amounts to the export in simple or extended mode.

= Invoices =
Export invoice fields and attach invoice files to the export.
Note: Currently, we only support the
[PDF Invoices & Packing Slips for WooCommerce](https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/)
plugin. Additional invoice plugins will be supported in future updates.

= Customer exports =

= Filters =
Filter customers by date period and country. Country filters can use billing or shipping countries.

== Screenshots ==
1. Export overview and export management.
2. Order export configuration with field mapping.
3. Customer export configuration.
4. Export filters and date range selection.
5. Password-protected export download.

== Frequently Asked Questions ==

= Can I export only specific orders? =
Yes. Order exports can be filtered by date range and order status.
This allows you to generate exports only for the orders relevant to your accounting workflow.

= Can I include custom WooCommerce fields in my exports? =
Yes. StoreAccountant supports custom order and customer fields.
You can add these fields to your export configuration and include them as columns in the generated export files.

= Are export files protected from unauthorized access? =
Yes. Export files can be stored with password protection enabled.
This helps protect sensitive customer and accounting data when sharing exports with accountants,
tax advisors, or other third parties.

= Which export formats are supported? =
The free version currently supports CSV and JSON exports. Additional export formats may be added in future releases.

= Does StoreAccountant support invoice exports? =
Yes. StoreAccountant can export invoice-related fields and attach invoice PDF files to exports. Currently,
integration is available for the "PDF Invoices & Packing Slips for WooCommerce" plugin,
with support for additional invoice plugins planned for future releases.

== Upgrade Notice ==
Update the plugin via the WordPress dashboard.

== Changelog ==
{{CHANGELOG}}
