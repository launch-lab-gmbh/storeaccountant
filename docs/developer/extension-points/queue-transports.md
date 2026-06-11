# Queue Transports

StoreAccountant dispatches export work through Symfony Messenger. The free
plugin ships with a synchronous transport provider for the `sync` scheme and an
Action Scheduler transport provider for the `action_scheduler` scheme. The
synchronous transport is the default for small exports; Action Scheduler is
recommended for larger exports because it moves work into background actions.
When Action Scheduler is selected, manually triggered exports start an async HTTP
loopback runner. The runner processes StoreAccountant's own pending export
actions in small chunks and chains another loopback request while more immediate
work remains.

Reusable queue infrastructure lives below `StoreAccountant\Queue`. Export
messages and handlers live below `StoreAccountant\Export\Queue`.

Queue transports register provider objects on the
`storeaccountant_queue_transport_provider` hook. A provider returns the generated
Messenger DSN for display and creates the actual Symfony transport used by the
message bus. The active provider is selected from the StoreAccountant settings
page under the `Transports` tab.

Saved exports carry their own batch size. The
`storeaccountant_export_batch_size` filter receives that saved value and the
export post ID before batches are enqueued, so development code can override the
runtime size without changing the stored export record.

## Contract

```php
StoreAccountant\Queue\Contract\QueueTransportProviderInterface
```

Methods:

- `get_id(): string`
- `get_label(): string`
- `get_description(): string`
- `get_dsn(): string`
- `supports_manual_loopback(): bool`
- `create_transport(SerializerInterface $serializer): TransportInterface`

## Registry

```php
StoreAccountant\Queue\QueueTransportRegistry
```

## Hook

```php
storeaccountant_queue_transport_provider
```

## Registration

```php
add_filter(
	'storeaccountant_queue_transport_provider',
	static function ( array $providers ) use ( $transport_provider ): array {
		$providers[ $transport_provider->get_id() ] = $transport_provider;

		return $providers;
	},
	110
);
```

## Example Implementation

```php
<?php

declare(strict_types=1);

namespace Acme\StoreAccountant;

use StoreAccountant\Queue\Contract\QueueTransportProviderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class AmqpQueueTransportProvider implements QueueTransportProviderInterface {
	public function get_id(): string {
		return 'amqp';
	}

	public function get_label(): string {
		return __( 'AMQP', 'acme-storeaccountant' );
	}

	public function get_description(): string {
		return __( 'Processes StoreAccountant queue jobs through an AMQP worker.', 'acme-storeaccountant' );
	}

	public function get_dsn(): string {
		return 'amqp://guest:guest@localhost:5672/storeaccountant';
	}

	public function supports_manual_loopback(): bool {
		return false;
	}

	public function create_transport( SerializerInterface $serializer ): TransportInterface {
		// Return a Symfony Messenger transport for get_dsn().
	}
}
```

Important: the registered value must be a concrete instance of
`QueueTransportProviderInterface`.

## Debug Hooks

The `storeaccountant_export_queue_debug_delay_seconds` filter can return an
integer number of seconds to sleep during queued export batch processing and
finalization. Its default value is `0`. This hook is intended only for local
development and automated/manual polling tests where processing exports should
remain visible long enough to observe status updates.

The `storeaccountant_export_polling_scheduled_window_seconds` filter controls
how soon the admin export overview starts polling a scheduled export run. The
default is five minutes. The filter applies only to real saved export records
that store a scheduled start time in `_storeaccountant_scheduled_for`; export
configurations are not polled.

Premium add-ons may introduce additional transports such as Redis or AMQP
without changing export messages or handlers. Transport registration should keep
these boundaries:

- Messages carry scalar payload data only.
- Handlers reload WordPress/WooCommerce state through repositories and APIs.
- Export handlers must not call Redis, AMQP, or Action Scheduler APIs directly.
- Workers must bootstrap WordPress before consuming messages.

The intended future WP-CLI entry point is:

```bash
wp storeaccountant messenger:consume
```
