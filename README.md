# StoreAccountant - WooCommerce plugin

![Header Image](docs/user/en/images/header.png)

> StoreAccountant is a WordPress/WooCommerce plugin for accounting workflows. It
starts with configurable accounting exports, but the project is intended as a
foundation for broader bookkeeping-related features around orders, customers,
tax data, documents, storage, and recurring accounting processes.

The current state is an early free-plugin foundation with persisted accounting
records, persisted export configurations, queue-backed background export
execution, adapter registries, and prepared extension points for future premium
add-ons or third-party integrations.
Saved export read views and saved export configuration detail screens expose
hook-backed tab providers so export-type-specific screens can be added without
changing the core admin pages.

Homepage: [storeaccountant.launch-lab.de](https://storeaccountant.launch-lab.de)

## Contributing

See [CONTRIBUTING.md](./CONTRIBUTING.md)




      
-- move:

Important for all future work: always read and follow [AGENTS.md](AGENTS.md)
before changing code.

## Current Features

```text
StoreAccountant
`-- Accounting
    |-- Exports
    |   |-- Orders
    |   |   |-- Date period filter
    |   |   |-- Order status filter
    |   |   |-- Field mapping
    |   |   |-- Tax field strategies
    |   |   |-- Custom order fields
    |   |   `-- Invoice fields and attachments
    |   `-- Customers
    |       |-- Date period filter
    |       |-- Country filter
    |       |-- Field mapping
    |       `-- Custom customer fields
    |-- Export Configurations
    |   |-- Export type
    |   |-- Export format
    |   |-- Storage location
    |   |-- Filters
    |   |-- Type-specific settings
    |   `-- Field mapping
    |-- Storage
    |   |-- Password-protected frontend downloads
    |   `-- Local protected ZIP storage
    |-- Queue
    |   |-- Symfony Messenger message bus
    |   `-- Action Scheduler transport
    `-- Integrations
        `-- WooCommerce PDF Invoices & Packing Slips
```

## Current State

- PHP 8.2 is the minimum target version.
- Classes live below `src` and are loaded through Composer PSR-4 autoloading.
- The plugin entry file `storeaccountant.php` stays bootstrap-only.
- Services are registered through `League\Container` in
  `src/ContainerBuilder.php`.
- Saved exports use the custom post type `storeacct_export`.
- Saved export configurations use the custom post type `storeacct_config`.
- Manual exports are dispatched through Symfony Messenger and processed in the
  background through the free Action Scheduler transport.
- Export processing stores lifecycle status and progress metadata so queued,
  processing, completed, and failed exports are visible in the backend.
- Scheduled exports are not currently offered in the admin UI. The export status
  and polling layers contain preparatory support for future scheduled export run
  records.
- StoreAccountant admin actions are guarded by plugin-specific WordPress
  capabilities that can be assigned to backend roles from the plugin settings.
- StoreAccountant declares WooCommerce HPOS compatibility; order features must
  use WooCommerce order APIs and avoid direct post/postmeta access for
  WooCommerce order data.
- Export adapters and providers register through WordPress hooks so premium
  add-ons or third-party plugins can hook into the system.
- The first storage adapter is `local`; it uses Flysystem with a protected local
  zip archive below `wp-content/uploads/storeaccountant`.

## Documentation

- [Architecture](docs/developer/architecture.md)
- [Permissions](docs/developer/permissions.md)
- [Events](docs/developer/events.md)
- [Extension Points, Free/Premium, and Hooks](docs/developer/extension-points.md)
- [Development Notes for Agents](docs/developer/development-notes.md)
- [Export Download Protection](docs/developer/export-download-protection.md)

## Translations

All user-facing strings in PHP code must stay in English and use the
`storeaccountant` text domain. Use natural English strings in translation
function calls for normal UI text. Synthetic translation keys are reserved for
values that must be resolved from runtime identifiers, such as registered export
adapter, renderer, storage adapter, or invoice plugin IDs; built-in dynamic keys
must also be listed statically for translation extraction.

When adding or changing translatable strings, update the complete translation
catalogue below `languages`, including the template, English files, and German
files. The English files are intentionally maintained even though the PHP source
strings are English, because they provide readable labels for dynamic keys and
give translators the same complete catalogue as other locales.

## Local development

The project uses [wp-env](https://github.com/10up/wp-env) for local development.  
Plugin path in container: /var/www/html/wp-content/plugins/storeaccountant/  

Admin access  
Admin user: admin  
Admin password: password

### Start the wp-env project

```bash
wp-env start --config=.wp-env.json

# Start for coverage
wp-env start --xdebug=coverage
```

### Stop the project

```bash
wp-env stop
```

### PHP commands

```bash
wp-env run cli php -v

# Run linting
wp-env run cli php -l /var/www/html/wp-content/plugins/storeaccountant/storeaccountant.php
```

### Composer commands

```bash
wp-env run cli composer --version

# Run against the plugin directory
wp-env run cli composer install --working-dir=/var/www/html/wp-content/plugins/storeaccountant
wp-env run cli composer install --no-dev --optimize-autoloader --classmap-authoritative --working-dir=/var/www/html/wp-content/plugins/storeaccountant
wp-env run cli composer install --working-dir=/var/www/html/wp-content/plugins/storeaccountant
wp-env run cli composer validate --working-dir=/var/www/html/wp-content/plugins/storeaccountant
```

### Run tests
```bash
wp-env run cli composer test --working-dir=/var/www/html/wp-content/plugins/storeaccountant
```

### Run phpcs lint
```bash
wp-env run cli composer lint --working-dir=/var/www/html/wp-content/plugins/storeaccountant
```

### Run phpcbf fix
```bash
wp-env run cli composer fix --working-dir=/var/www/html/wp-content/plugins/storeaccountant
```

## Detailed commands

### Stop the project (volumes, images,... )
```bash
wp-env destroy --force
```

### Activate the plugin
```bash
wp-env run cli wp plugin activate storeaccountant
```

### Reset Database

This command will reset the database to its initial state, deleting all data and
recreating the database structure. Use with caution. You need to run the installer after this.

```bash
wp-env run cli wp db reset --yes
```

### Reset Database with usable starter data
```bash
#!/usr/bin/env bash
set -e

wp-env run cli wp db reset --yes

wp-env run cli wp core install \
  --url=http://localhost \
  --title="Store Accountant Dev" \
  --admin_user=admin \
  --admin_password=password \
  --admin_email=admin@example.com

wp-env run cli wp plugin activate woocommerce.latest-stable
wp-env run cli wp plugin activate storeaccountant
```

## Smooth Generator

wp-env run cli wp wc generate terms

wp-env run cli wp wc generate products 20

wp-env run cli wp wc generate products 20 --type=variable

wp-env run cli wp wc generate orders 20 --date-start=2024-01-01 --date-end=2026-01-01 --status=completed
wp-env run cli wp wc generate orders 20 --date-start=2024-01-01 --date-end=2026-01-01 --status=failed

## Mkdocs

```bash
source .venv/bin/activate
mkdocs serve -f mkdocs-developer.yml
mkdocs serve -f mkdocs-user.yml
```

```bash
source .venv/bin/activate
mkdocs build -f mkdocs-developer.yml --strict
mkdocs build -f mkdocs-user.yml --strict
```
