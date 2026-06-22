---
title: Permissions
description: Assign StoreAccountant permissions to backend roles.
---

# Permissions

StoreAccountant uses its own WordPress capabilities for backend actions. Administrators always have all
StoreAccountant permissions. Other backend roles can be enabled selectively.

You can find the settings under:

`Plugins > Installed Plugins > StoreAccountant > Settings > Permissions`

## Basic Model

Each permission represents a concrete action, for example:

- Open the StoreAccountant admin area.
- View exports.
- Create exports.
- Download export files.
- Create or edit export configurations.
- Edit field mappings.
- Manage plugin settings.
- View download passwords.
- Manage diagnostic logging.

When a role receives at least one StoreAccountant action, it automatically receives the basic StoreAccountant admin
access permission.

## Assign Roles

1. Open `Plugins > Installed Plugins > StoreAccountant > Settings > Permissions`.
2. Find the action you want to configure.
3. Enable the backend roles that may perform this action.
4. Save the settings.

[Screenshot: Permission matrix]

StoreAccountant intentionally shows only roles that are generally suitable for backend access. Frontend-only roles such
as customer or subscriber normally do not appear.

## Typical Role Models

For accounting staff, the following permissions are often enough:

- Open the admin area.
- View exports.
- Create exports.
- Download export files.
- View export configurations.

For users who maintain templates, add:

- Create export configurations.
- Edit export configurations.
- Edit field mappings.

For technical administrators, add:

- Manage plugin settings.
- Manage permissions.
- Manage diagnostic logging.
- View download passwords, if passwords should be visible in the backend.

## Security Notes

Export files can contain sensitive customer, order, tax, and invoice data. Grant download and configuration permissions
only to roles that truly need this data.

The `View download passwords` permission is especially sensitive. It is not required for entering known passwords on
the download page, but it allows users to view stored passwords in the backend.
