# Permissions

StoreAccountant uses an action-based permission layer on top of native WordPress
role capabilities. The model is inspired by admin action systems such as
Symfony EasyAdmin, but authorization remains WordPress-native: roles receive
capabilities, and privileged code checks those capabilities before rendering UI
or executing state-changing handlers.

## Concepts

- A permission action describes one admin operation, for example
  `export.download`, `configuration.edit`, or `configuration.edit_field_mapping`.
- Each action has one WordPress capability such as
  `storeaccountant_download_export`.
- Buttons, tabs, list actions, custom admin pages, and `admin_post_*` handlers
  should check the same action.
- The administrator role is always included and cannot be removed in the UI.
- Other roles can be assigned per action in
  `StoreAccountant Settings > Permissions`.
- A role receives `storeaccountant_access_admin` automatically when it receives
  any StoreAccountant action.

The permissions UI intentionally shows only backend-capable roles. By default,
roles are considered assignable when they have at least one common admin
capability such as `manage_options`, `manage_woocommerce`, `edit_posts`,
`read_private_posts`, `list_users`, or an existing StoreAccountant capability.
Frontend-only roles such as customer or subscriber are therefore not offered by
default.

## Built-In Actions

| Action ID | Capability | Purpose |
| --- | --- | --- |
| `admin.access` | `storeaccountant_access_admin` | Open StoreAccountant admin screens. |
| `settings.manage` | `storeaccountant_manage_settings` | Manage storage and invoice provider settings. |
| `settings.view_download_passwords` | `storeaccountant_view_download_passwords` | Reveal stored export download passwords in the backend. |
| `permissions.manage` | `storeaccountant_manage_permissions` | Manage permission assignments. |
| `export.list` | `storeaccountant_read_exports` | View the export overview. |
| `export.view` | `storeaccountant_view_export` | View saved export details. |
| `export.create` | `storeaccountant_create_exports` | Start quick or configuration-based exports. |
| `export.download` | `storeaccountant_download_export` | Download generated export files. |
| `export.delete` | `storeaccountant_delete_exports` | Trash or delete saved exports. |
| `configuration.list` | `storeaccountant_read_configurations` | View export configurations. |
| `configuration.view` | `storeaccountant_view_configuration` | Open configuration read views. |
| `configuration.create` | `storeaccountant_create_configurations` | Create export configurations. |
| `configuration.edit` | `storeaccountant_edit_configuration` | Edit export configurations. |
| `configuration.delete` | `storeaccountant_delete_configurations` | Trash or delete export configurations. |
| `configuration.edit_field_mapping` | `storeaccountant_edit_field_mapping` | Edit field mappings on configurations. |

## Registering Custom Actions

Extensions can register actions through `storeaccountant_permission_action`.
Use stable action IDs and capabilities prefixed with your plugin or
`storeaccountant`.

```php
use StoreAccountant\Security\Permission\PermissionAction;

add_filter(
	'storeaccountant_permission_action',
	static function ( array $actions ): array {
		$action = new PermissionAction(
			'my_addon.export.send_to_tax_office',
			__( 'Send export to tax office', 'my-addon' ),
			__( 'My Add-on', 'my-addon' ),
			'my_addon_send_storeaccountant_export',
			__( 'Allows sending a generated export to the tax office integration.', 'my-addon' )
		);

		$actions[ $action->get_id() ] = $action;

		return $actions;
	}
);
```

After registration, the action appears in the StoreAccountant permissions tab.
When a role is assigned, WordPress stores the action capability on that role.

## Checking Permissions

Core code should use `StoreAccountant\Security\Permission\PermissionChecker` where it is
available through the service container:

```php
if ( ! $this->permissions->can( 'my_addon.export.send_to_tax_office', $export_id ) ) {
	wp_die( esc_html__( 'You are not allowed to send this export.', 'my-addon' ) );
}
```

Extensions outside the container can also check the action capability directly
with `current_user_can( 'my_addon_send_storeaccountant_export' )`, but then they
must handle their own administrator override if they need one.

Always check both UI visibility and execution:

- Hide or disable the button when the user cannot perform the action.
- Repeat the same check inside the `admin_post_*`, AJAX, REST, or cron-triggered
  handler before doing the work.

## Assignable Roles

The permissions screen filters roles to avoid exposing frontend-only roles.
Projects with dedicated backend roles can opt in explicitly:

```php
add_filter(
	'storeaccountant_assignable_permission_roles',
	static function ( array $roles ): array {
		$role = get_role( 'accountant' );

		if ( null !== $role ) {
			$roles['accountant'] = [
				'label'  => __( 'Accountant', 'my-addon' ),
				'locked' => false,
			];
		}

		return $roles;
	}
);
```

Only add roles that should have access to `wp-admin`. StoreAccountant will grant
`storeaccountant_access_admin` automatically when the role receives at least one
StoreAccountant action.
