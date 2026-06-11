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

namespace StoreAccountant\Tests\Unit\Export\Queue\Message;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Queue\Message\ProcessExportBatchMessage;

/**
 * Tests process export batch queue messages.
 */
final class ProcessExportBatchMessageTest extends TestCase {
	public function test_constructor_stores_batch_message_data(): void {
		$message = new ProcessExportBatchMessage( 42, 3, 200, 100 );

		self::assertSame( 42, $message->export_id );
		self::assertSame( 3, $message->batch_number );
		self::assertSame( 200, $message->offset );
		self::assertSame( 100, $message->limit );
	}
}
