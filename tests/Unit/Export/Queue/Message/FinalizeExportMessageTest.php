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
use StoreAccountant\Export\Queue\Message\FinalizeExportMessage;

/**
 * Tests finalize export queue messages.
 */
final class FinalizeExportMessageTest extends TestCase {
	public function test_constructor_stores_export_id_and_renderer_override(): void {
		$message = new FinalizeExportMessage( 42, 'json' );

		self::assertSame( 42, $message->export_id );
		self::assertSame( 'json', $message->renderer_id );
	}

	public function test_renderer_override_defaults_to_null(): void {
		self::assertNull( ( new FinalizeExportMessage( 42 ) )->renderer_id );
	}
}
