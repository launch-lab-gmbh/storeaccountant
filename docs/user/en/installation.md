---
title: Installation
description: Install StoreAccountant through the WordPress plugin search, by ZIP upload, or by FTP.
---

# Installation

StoreAccountant is a WordPress plugin for WooCommerce. You need a WordPress installation with WooCommerce active and a
user account that is allowed to install and activate plugins.

After activation, you can find StoreAccountant in the WordPress admin under `Accounting > Exports`. Open the plugin
settings through `Plugins > Installed Plugins > StoreAccountant > Settings`.

## Install Through the WordPress Plugin Search

This is the normal installation path for most shops.

1. Open `Plugins > Add New` in the WordPress admin.
2. Search for `StoreAccountant`.
3. Click `Install Now` on StoreAccountant.
4. After installation, click `Activate`.
5. Open `Accounting > Exports`.

[Screenshot: Plugin search in the WordPress admin]

## Install by ZIP Upload

Use this path when you want to install a plugin ZIP file from a release.

1. Download the current StoreAccountant ZIP file from the latest GitHub release:
   [github.com/launch-lab-gmbh/storeaccountant/releases/latest](https://github.com/launch-lab-gmbh/storeaccountant/releases/latest)
2. Open `Plugins > Add New` in the WordPress admin.
3. Click `Upload Plugin` at the top.
4. Select the ZIP file.
5. Click `Install Now`.
6. Activate the plugin after installation.
7. Open `Accounting > Exports`.

[Screenshot: ZIP upload in the WordPress admin]

## Install by FTP

This path is useful when the WordPress admin does not allow ZIP uploads or the installation should be done manually on
the server.

1. Download the current StoreAccountant ZIP file from the latest GitHub release:
   [github.com/launch-lab-gmbh/storeaccountant/releases/latest](https://github.com/launch-lab-gmbh/storeaccountant/releases/latest)
2. Extract the ZIP file locally.
3. Upload the extracted plugin folder by FTP into the WordPress directory `wp-content/plugins/`.
4. Open `Plugins > Installed Plugins` in the WordPress admin.
5. Activate StoreAccountant.
6. Open `Accounting > Exports`.

[Screenshot: Activation in the plugin list]

## After Installation

Check the basic settings first:

1. Open `Plugins > Installed Plugins > StoreAccountant > Settings`.
2. In the `Storage Locations` tab, check that at least one storage location is enabled.
3. In the `Security` tab, check that a global download password exists.
4. In the `Transports` tab, check how StoreAccountant processes background jobs.
5. In the `Permissions` tab, assign access to additional backend roles if needed.

For details, see [Plugin Configuration](plugin/configuration.md).
