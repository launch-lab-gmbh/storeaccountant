<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author      thomas.baier@launch-lab.de
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Tests\Unit\Export\Event;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Event\ExportEvents;

/**
 * Tests public export event hook names.
 */
final class ExportEventsTest extends TestCase {
	public function test_event_values_are_prefixed_public_hook_names(): void {
		self::assertSame(
			[
				'storeaccountant_export_log_entry',
				'storeaccountant_export_queued',
				'storeaccountant_export_started',
				'storeaccountant_export_batches_calculated',
				'storeaccountant_export_batch_processed',
				'storeaccountant_export_batch_jobs_queued',
				'storeaccountant_export_finalization_queued',
				'storeaccountant_export_finalization_started',
				'storeaccountant_export_dataset_loaded',
				'storeaccountant_export_artifact_rendered',
				'storeaccountant_export_artifact_persisted',
				'storeaccountant_export_completed',
				'storeaccountant_export_failed',
			],
			array_map( static fn ( ExportEvents $event ): string => $event->value, ExportEvents::cases() )
		);
	}
}
