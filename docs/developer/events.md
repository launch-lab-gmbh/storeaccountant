# Events

StoreAccountant exposes domain events through WordPress action hooks. Events are
intended for add-ons that need to react to completed lifecycle steps without
coupling themselves to queue handlers, admin pages, or storage internals.

Events are different from registry and provider filters:

- Registry filters register services or replace extension-point objects.
- Events notify listeners that something happened in the StoreAccountant domain.

Event listeners should keep heavy work out of the original request or queue job.
For expensive post-processing such as email delivery, remote uploads, webhooks,
or document processing, listeners should schedule their own background work and
return quickly.

## Event Subscribers

Core event listeners use a Symfony-inspired event subscriber style while still
registering normal WordPress action hooks underneath. Subscribers implement
`StoreAccountant\Event\Contract\EventSubscriberInterface` and are registered by
`StoreAccountant\Event\EventSubscriberRegistrar`.

```php
<?php

namespace StoreAccountant\Export\Event;

use StoreAccountant\Event\Contract\EventSubscriberInterface;

final readonly class ExampleExportSubscriber implements EventSubscriberInterface {
	public static function get_subscribed_events(): array {
		return [
			ExportEvents::COMPLETED->value => [
				[ 'handle_completed_export', 10, 2 ],
			],
		];
	}

	public function handle_completed_export( int $export_id, array $context = [] ): void {
		// React to the completed export here.
	}
}
```

Each subscriber entry uses this shape:

```text
[ method_name, priority, accepted_args ]
```

Add-ons may still use plain `add_action()` directly. The subscriber system is a
convenience layer for StoreAccountant services that should keep event wiring in
one place.

## Event Naming

Public event hooks are prefixed with `storeaccountant_` and use a past-tense
domain phrase. Core export event names are defined in the
`StoreAccountant\Export\Event\ExportEvents` string enum:

```text
storeaccountant_export_log_entry
storeaccountant_export_queued
storeaccountant_export_started
storeaccountant_export_batches_calculated
storeaccountant_export_batch_processed
storeaccountant_export_batch_jobs_queued
storeaccountant_export_finalization_queued
storeaccountant_export_finalization_started
storeaccountant_export_dataset_loaded
storeaccountant_export_artifact_rendered
storeaccountant_export_artifact_persisted
storeaccountant_export_completed
storeaccountant_export_failed
```

## Hook Overview

| Hook | Fires When | Arguments |
| --- | --- | --- |
| `storeaccountant_export_log_entry` | A technical export log entry should be written. | `int $export_id`, `string $level`, `string $message`, `array $context`, `Throwable|null $exception` |
| `storeaccountant_export_queued` | An export has been queued for background processing. | `int $export_id`, `array $context` |
| `storeaccountant_export_started` | The export queue worker has started preparing an export. | `int $export_id`, `array $context` |
| `storeaccountant_export_batches_calculated` | The queue worker has counted source items and calculated batches. | `int $export_id`, `array $context` |
| `storeaccountant_export_batch_processed` | One export batch has been processed. | `int $export_id`, `array $context` |
| `storeaccountant_export_batch_jobs_queued` | Batch processing jobs have been queued. | `int $export_id`, `array $context` |
| `storeaccountant_export_finalization_queued` | Export finalization has been queued. | `int $export_id`, `array $context` |
| `storeaccountant_export_finalization_started` | Export finalization has started. | `int $export_id`, `array $context` |
| `storeaccountant_export_dataset_loaded` | The finalizer has loaded the export dataset from batch files. | `int $export_id`, `array $context` |
| `storeaccountant_export_artifact_rendered` | The finalizer has rendered the export artifact. | `int $export_id`, `array $context` |
| `storeaccountant_export_artifact_persisted` | The finalizer has persisted the export artifact through the storage adapter. | `int $export_id`, `array $context` |
| `storeaccountant_export_completed` | A saved export has been successfully finalized and marked completed. | `int $export_id`, `array $context` |
| `storeaccountant_export_failed` | An export has been marked failed. | `int $export_id`, `string $error_message`, `string $log_message`, `array $context`, `Throwable|null $exception` |

The `storeaccountant_export_log_entry_limit` filter is related to these events,
but it is a configuration filter rather than a lifecycle event. It receives the
default limit and export post ID and returns the maximum number of stored log
entries.

```php
<?php

add_filter(
	'storeaccountant_export_log_entry_limit',
	static function ( int $limit, int $export_id ): int {
		return 100;
	},
	10,
	2
);
```

## Firing Events in Core Code

StoreAccountant fires lifecycle events through `ExportEventDispatcher` after
state has been persisted for the step being reported. New core events should
use `ExportEvents` instead of repeating literal hook strings.

```php
<?php

use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;

ExportEventDispatcher::dispatch(
	ExportEvents::QUEUED,
	$export_id,
	[
		'action'      => 'storeaccountant_start_export',
		'export_id'   => $export_id,
		'renderer_id' => $renderer_id,
	]
);
```

For failures, `ExportRepository::mark_failed()` and
`mark_failed_from_error()` centralize status persistence and emit
`storeaccountant_export_failed`, so queue handlers should prefer those helpers
over firing the failed event directly.

## `storeaccountant_export_completed`

Fires after the export finalizer has successfully created the export result,
after the export status has been stored as completed, and after the final
`Export completed.` log entry has been written.

The event fires for every successful completed export, including exports with
zero items. Zero-item exports can be meaningful for accounting workflows such as
nil returns or other "nothing to report" cases.

The event does not fire when finalization fails, when the export is invalid, when
queued batches have not finished yet, or when an already-completed export is
encountered again.

```php
<?php

add_action(
	'storeaccountant_export_completed',
	static function ( int $export_id, array $context = [] ): void {
		// Load the export and schedule post-processing work here.
	},
	10,
	2
);
```

Paid add-ons can use this event to dispatch export post-processing without the
free plugin needing to know about specific post-processors.

## `storeaccountant_export_failed`

Fires when an export is marked failed. The event includes both the public error
message and a technical log message so listeners can decide what to expose.

```php
<?php

add_action(
	'storeaccountant_export_failed',
	static function (
		int $export_id,
		string $error_message,
		string $log_message,
		array $context = [],
		?\Throwable $exception = null
	): void {
		// Notify maintainers or schedule cleanup work here.
	},
	10,
	5
);
```
