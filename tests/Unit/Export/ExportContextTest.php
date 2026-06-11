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
use StoreAccountant\Export\ExportContext;

/**
 * Tests export runtime context value objects.
 */
final class ExportContextTest extends TestCase {
	public function test_get_returns_stored_values_and_fallbacks(): void {
		$context = new ExportContext(
			'orders',
			42,
			[ 'order-a' ],
			[
				'format' => 'csv',
				'count'  => 12,
			]
		);

		self::assertSame( 'orders', $context->export_type );
		self::assertSame( 42, $context->configuration_id );
		self::assertSame( [ 'order-a' ], $context->items );
		self::assertSame( 'csv', $context->get( 'format' ) );
		self::assertSame( 12, $context->get( 'count' ) );
		self::assertSame( 'fallback', $context->get( 'missing', 'fallback' ) );
		self::assertNull( $context->get( 'missing' ) );
	}

	public function test_with_returns_new_context_without_mutating_original(): void {
		$original = new ExportContext( 'customers', 9, [ 'customer-a' ], [ 'format' => 'csv' ] );

		$changed = $original->with( 'format', 'json' )->with( 'storage', 'local' );

		self::assertNotSame( $original, $changed );
		self::assertSame( 'csv', $original->get( 'format' ) );
		self::assertNull( $original->get( 'storage' ) );
		self::assertSame( 'json', $changed->get( 'format' ) );
		self::assertSame( 'local', $changed->get( 'storage' ) );
		self::assertSame( 'customers', $changed->export_type );
		self::assertSame( 9, $changed->configuration_id );
		self::assertSame( [ 'customer-a' ], $changed->items );
	}
}
