# Uninstall Cleanup Tasks

Uninstall cleanup tasks remove StoreAccountant database artifacts when the plugin
is uninstalled through WordPress. They do not run during deactivation.

Tasks are registered through `storeaccountant_uninstall_cleanup_task` and must
implement `StoreAccountant\Uninstall\Contract\UninstallCleanupTaskInterface`.
The interface extends the shared registry item contract, so each task needs a
stable non-empty ID.

Built-in cleanup tasks remove:

- Plugin settings and StoreAccountant role capabilities.
- StoreAccountant cron and Action Scheduler queue state.
- Saved export configuration records.
- Saved export records.

Cleanup is deliberately database-only. Generated export archives in the
StoreAccountant storage directory and diagnostic log files are kept on disk.
Extensions should follow the same rule unless they clearly document a separate
user-confirmed data removal workflow.

```php
<?php

use StoreAccountant\Uninstall\Contract\UninstallCleanupTaskInterface;

add_filter(
	'storeaccountant_uninstall_cleanup_task',
	static function ( array $tasks ): array {
		$tasks['my_integration'] = new class() implements UninstallCleanupTaskInterface {
			public function get_id(): string {
				return 'my_integration';
			}

			public function cleanup(): void {
				delete_option( 'storeaccountant_my_integration_setting' );
			}
		};

		return $tasks;
	}
);
```
