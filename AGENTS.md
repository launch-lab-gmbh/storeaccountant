# Agent Instructions

This repository contains a WordPress PHP project. The active custom plugin is
`wp-content/plugins/storeaccountant`.

## Product Context

StoreAccountant is planned as a WooCommerce accounting workflow plugin. It
starts with configurable exports for orders and customers, while keeping the
architecture open for broader bookkeeping-related features around tax data,
documents, storage, and recurring accounting processes.

## Documentation

Before implementing changes, read `README.md` and follow its links to the
relevant project documentation. Treat the README as the documentation entry
point and use the linked docs as the source of truth for architecture, extension
points, and development notes.

Exports should be persisted as first-class records, not treated as temporary
button results only. Prefer modelling saved exports as objects with metadata,
for example via a custom post type or another appropriate WordPress storage
primitive, rather than as taxonomy terms. Saved export metadata is expected to
include at least:

- Export name.
- Export creation/trigger time.
- User who triggered the export.
- Selected period, initially month-based.
- Selected export template.
- Selected storage adapter or destination.
- Export status and generated file/reference when available.

Users will be able to choose from different export templates. A template defines
which columns are included in the export and how values are resolved from
WooCommerce orders, customers, and related data. Keep template logic separate
from the admin UI, export execution, and storage concerns.

Storage is adapter-based and uses Composer-managed dependencies. The built-in
local adapter uses League Flysystem internally so different storage adapters can
be offered. Users can choose enabled storage adapters globally and select a
storage adapter per export or export configuration.

Exports must support asynchronous/background execution. Manual button-triggered
exports should not block the browser request when the export work becomes
non-trivial. Scheduled exports are still planned, but should be modelled through
a focused scheduling workflow rather than as a checkbox on export
configurations.

When shaping new code, keep these likely boundaries in mind:

- `src/Admin` for admin screens, forms, actions, and status views.
- `src/Export` for export orchestration, order querying, generated files, and
  background execution.
- `src/Template` or an equivalent namespace for export template definitions and
  column resolvers.
- `src/Storage` for adapter abstractions and concrete storage integrations.
- `src/Schedule` or a similarly focused namespace for future recurring export
  setup and cron integration.

## Project Rules

- Follow the WordPress PHP coding standards.
- Write code for the PHP version declared in `storeaccountant.php` via `StoreAccountant::PHP_VERSION`.
- Use the latest PHP language features available in that declared PHP version.
- Prefer short array syntax (`[]`) over legacy `array()` syntax.
- Add scalar, object, and `void` return types wherever possible.
- Use constructor property promotion for service dependencies. Keep service dependencies private and avoid getters/setters for them.
- For value objects and DTOs, prefer `readonly final class` with promoted public properties over private properties with trivial getters.
- In namespaced PHP files, import global functions used by the file with `use function ...;`.
  This applies to PHP built-ins and WordPress/WooCommerce functions such as
  `sanitize_key`, `trailingslashit`, `wp_tempnam`, or `wc_get_orders`. Prefer
  `use function wp_tempnam;` plus `wp_tempnam()` over leading-slash calls.
- Importing a WordPress function does not load the file that defines it. When
  code runs in cron, Action Scheduler, REST, AJAX, or CLI contexts, explicitly
  load optional WordPress admin helper files before using functions that are not
  part of the normal frontend bootstrap, for example
  `require_once ABSPATH . 'wp-admin/includes/file.php';` before `wp_tempnam()`.
- Always add `declare(strict_types=1);` to new PHP files.
- Every new PHP file should include the WordPress direct-access guard directly after namespace/use statements:

```php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
```

- Work with the Composer autoloader. Do not add manual `require` calls for feature classes outside the bootstrap/autoloader setup.
- The plugin entry file `storeaccountant.php` should stay a bootstrap file only.
- Create new PHP classes only inside `wp-content/plugins/storeaccountant/src`.
- Create feature-specific subdirectories below `src`, for example `src/Export` or `src/FeatureName`.
- Every new PHP file must start with a file docblock.
- Every new class file must include this header comment at the top:

```php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author      thomas.baier@launch-lab.de
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */
```

## Internationalization

- Keep all user-facing text strings in code in English.
- Wrap user-facing strings with WordPress translation functions using the `storeaccountant` text domain.
- Use natural English source strings in translation calls for normal UI text,
  for example `__( 'Storage Location', 'storeaccountant' )`, not opaque label
  keys such as `storage_location_label`.
- Use synthetic translation keys only when the translated value is built from
  runtime identifiers, for example registry item IDs such as
  `export_adapter_orders`. Keep every built-in dynamic key discoverable through
  a static translation call such as the catalogue entries in `src/I18n.php`.
- Maintain translation files in `wp-content/plugins/storeaccountant/languages`.
- When adding or changing translatable strings, update:
  - `languages/storeaccountant.pot`
  - `languages/storeaccountant-en_US.po`
  - `languages/storeaccountant-en_US.mo`
  - `languages/storeaccountant-de_DE.po`
  - `languages/storeaccountant-de_DE.mo`
- Keep the English translation files complete, even though the code already
  uses English source strings. The English files provide readable translations
  for synthetic dynamic keys and give translators the same complete catalogue as
  the German files.
- Preserve placeholders exactly in translations, for example `%1$s` and `%2$s`.

## WordPress Practices

- Escape output with the appropriate WordPress escaping function, such as `esc_html()`, `esc_attr()`, or `esc_url()`.
- Sanitize and validate input before use.
- Verify nonces for form submissions and state-changing actions.
- Check capabilities before admin actions. Prefer WooCommerce capabilities such as `manage_woocommerce` when appropriate.
- Avoid direct database queries unless the WordPress or WooCommerce APIs are insufficient.
- Prefer WordPress APIs over direct PHP alternatives whenever WordPress provides
  a suitable helper, including filesystem, HTTP, redirects, sanitization,
  escaping, dates, uploads, and database access.
- Before introducing any `phpcs:ignore`, first investigate whether the warning
  can be fixed with a WordPress API, validation, sanitization, escaping, or a
  small code structure change. If an ignore is still necessary, explain the
  warning, the attempted alternatives, and ask for confirmation before adding it.
- Prefix global constants, hooks, options, transients, and non-namespaced functions with `storeaccountant` or `STOREACCOUNTANT`.

## Structure

- Use the `StoreAccountant` namespace for plugin classes.
- Map namespaces to folders below `src`.
- Keep classes focused and small.
- Prefer service classes for behavior and thin WordPress hook registration methods.
- Keep admin UI code under `src/Admin`.
- Keep export-related code under `src/Export`.

## Local Environment

- This project lives in WSL at `/home/thomas/Projects/storeaccountant`. If the
  working directory is shown as a Windows UNC path such as
  `\\wsl.localhost\FedoraLinux-44\home\thomas\Projects\storeaccountant`, run
  project commands through WSL instead of looking for PHP, Git, or `wp-env` on
  the Windows/PowerShell PATH.
- Use `wsl -d FedoraLinux-44 bash -lc 'cd /home/thomas/Projects/storeaccountant && <command>'`
  for repository commands from a Windows shell.
- PHP tooling is available through `wp-env` in WSL. For example:

```sh
wsl -d FedoraLinux-44 bash -lc 'cd /home/thomas/Projects/storeaccountant && wp-env run cli php -v'
wsl -d FedoraLinux-44 bash -lc 'cd /home/thomas/Projects/storeaccountant && wp-env run cli php -l /var/www/html/wp-content/plugins/storeaccountant/src/ChangedFile.php'
```

## Verification

- Run PHP linting or tests when PHP tooling is available.
- Do not revert unrelated user changes.
- Keep changes scoped to the requested feature or fix.
