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

namespace StoreAccountant\Tests\Unit\Export\Filter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;

/**
 * Tests export filter selection serialization.
 */
final class ExportFilterSelectionSerializerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_encode_serializes_filter_selections(): void {
		$selections = [
			new ExportFilterSelection( 'order_date', [ 'month' => '5' ] ),
			new ExportFilterSelection( 'order_status', [ 'statuses' => [ 'wc-completed' ] ] ),
		];

		$expected_items = [
			[
				'filter_id' => 'order_date',
				'settings'  => [ 'month' => '5' ],
			],
			[
				'filter_id' => 'order_status',
				'settings'  => [ 'statuses' => [ 'wc-completed' ] ],
			],
		];

		Functions\expect( 'wp_json_encode' )
			->once()
			->with( $expected_items )
			->andReturn( '[{"filter_id":"order_date"},{"filter_id":"order_status"}]' );

		self::assertSame(
			'[{"filter_id":"order_date"},{"filter_id":"order_status"}]',
			( new ExportFilterSelectionSerializer() )->encode( $selections )
		);
	}

	public function test_encode_returns_empty_json_array_when_encoding_fails(): void {
		Functions\expect( 'wp_json_encode' )
			->once()
			->andReturn( false );

		self::assertSame( '[]', ( new ExportFilterSelectionSerializer() )->encode( [ new ExportFilterSelection( 'x' ) ] ) );
	}

	public function test_decode_sanitizes_filter_ids_and_ignores_invalid_items(): void {
		Functions\expect( 'sanitize_key' )
			->twice()
			->andReturnUsing(
				static fn ( string $value ): string => strtolower( str_replace( ' ', '_', $value ) )
			);

		$decoded = ( new ExportFilterSelectionSerializer() )->decode(
			json_encode(
				[
					[
						'filter_id' => 'Order Date',
						'settings'  => [ 'month' => '5' ],
					],
					[
						'filter_id' => 'Order Status',
						'settings'  => 'invalid-settings',
					],
					[ 'settings' => [] ],
					'not-an-array',
				]
			)
		);

		self::assertCount( 2, $decoded );
		self::assertSame( 'order_date', $decoded[0]->filter_id );
		self::assertSame( [ 'month' => '5' ], $decoded[0]->settings );
		self::assertSame( 'order_status', $decoded[1]->filter_id );
		self::assertSame( [], $decoded[1]->settings );
	}

	public function test_decode_returns_empty_array_for_invalid_json(): void {
		Functions\expect( 'sanitize_key' )->never();

		self::assertSame( [], ( new ExportFilterSelectionSerializer() )->decode( 'not-json' ) );
		self::assertSame( [], ( new ExportFilterSelectionSerializer() )->decode( '' ) );
	}
}
