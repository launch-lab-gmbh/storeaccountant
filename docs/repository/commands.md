# Development

## WP-ENV

The project uses [wp-env](https://github.com/10up/wp-env) for local development.  
Plugin path in container: /var/www/html/wp-content/plugins/storeaccountant/

Admin access  
Admin user: admin  
Admin password: password

## Commands

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

```bash
wp-env run cli wp wc generate terms
wp-env run cli wp wc generate products 20
wp-env run cli wp wc generate products 10 --type=variable
wp-env run cli wp wc generate orders 20 --status=completed
wp-env run cli wp wc generate orders 20 --status=failed
wp-env run cli wp wc generate orders 20 --date-start=2024-01-01 --date-end=2026-01-01 --status=completed
wp-env run cli wp wc generate orders 20 --date-start=2024-01-01 --date-end=2026-01-01 --status=failed
```

## Mkdocs

```bash
source .venv/bin/activate
mkdocs serve -f mkdocs-developer.yml
mkdocs serve -f mkdocs-user-de.yml
mkdocs serve -f mkdocs-user-en.yml
```

```bash
source .venv/bin/activate
mkdocs build -f mkdocs-developer.yml --strict
mkdocs build -f mkdocs-user-de.yml --strict
mkdocs build -f mkdocs-user-en.yml --strict
```