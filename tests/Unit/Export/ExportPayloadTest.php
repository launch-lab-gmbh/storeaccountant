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

namespace StoreAccountant\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\ExportFilterSelection;

/**
 * Tests export payload value objects.
 */
final class ExportPayloadTest extends TestCase {
	public function test_constructor_stores_payload_data(): void {
		$filters = [ new ExportFilterSelection( 'order_date', [ 'month' => '5' ] ) ];
		$options = [ ExportPayload::OPTION_INCLUDE_ATTACHMENTS => true ];

		$payload = new ExportPayload( 42, 'orders', $filters, $options );

		self::assertSame( 42, $payload->export_id );
		self::assertSame( 'orders', $payload->export_type );
		self::assertSame( $filters, $payload->filters );
		self::assertSame( $options, $payload->options );
	}

	public function test_constructor_uses_empty_defaults(): void {
		$payload = new ExportPayload( 7, 'customers' );

		self::assertSame( [], $payload->filters );
		self::assertSame( [], $payload->options );
	}
}
