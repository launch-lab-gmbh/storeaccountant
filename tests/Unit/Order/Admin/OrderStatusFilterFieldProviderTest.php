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

namespace StoreAccountant\Tests\Unit\Order\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Order\Admin\OrderStatusField;
use StoreAccountant\Order\Admin\OrderStatusFilterFieldProvider;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Export\Filter\OrderStatusFilter;
use StoreAccountant\Order\Export\OrderStatusProvider;
use WP_Error;

/**
 * Tests the order status filter field provider.
 */
final class OrderStatusFilterFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_filter_field_provider(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_filter_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$this->provider()->register();

		self::assertTrue( true );
	}

	public function test_get_id_and_supports_are_stable(): void {
		$provider = $this->provider();

		self::assertSame( OrderStatusFilter::FILTER_ID, $provider->get_id() );
		self::assertTrue( $provider->supports( OrderExportAdapter::ADAPTER_ID ) );
		self::assertFalse( $provider->supports( 'customers' ) );
	}

	public function test_get_selection_from_request_returns_sanitized_selection(): void {
		$this->mock_order_statuses();
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => strtolower( str_replace( ' ', '-', $value ) ) );

		$selection = $this->provider()->get_selection_from_request(
			[
				OrderStatusField::FIELD_NAME => [ 'wc-completed', 'WC Failed', 'wc-unknown' ],
			]
		);

		self::assertInstanceOf( ExportFilterSelection::class, $selection );
		self::assertSame( OrderStatusFilter::FILTER_ID, $selection->filter_id );
		self::assertSame( [ 'wc-completed', 'wc-failed' ], $selection->settings['statuses'] );
	}

	public function test_get_selection_from_request_returns_error_for_empty_status_selection(): void {
		$this->mock_order_statuses();
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => $value );

		$result = $this->provider()->get_selection_from_request(
			[
				OrderStatusField::FIELD_NAME => [ 'wc-unknown' ],
			]
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'storeaccountant_invalid_order_statuses', $result->get_error_code() );
	}

	public function test_get_default_selection_uses_default_order_statuses(): void {
		$this->mock_order_statuses();

		$selection = $this->provider()->get_default_selection();

		self::assertSame( OrderStatusFilter::FILTER_ID, $selection->filter_id );
		self::assertSame( [ 'wc-completed', 'wc-failed' ], $selection->settings['statuses'] );
	}

	private function provider(): OrderStatusFilterFieldProvider {
		return new OrderStatusFilterFieldProvider(
			new OrderStatusField( new OrderStatusProvider() )
		);
	}

	private function mock_order_statuses(): void {
		Functions\expect( 'wc_get_order_statuses' )
			->once()
			->andReturn(
				[
					'wc-completed' => 'Completed',
					'wc-failed'    => 'Failed',
				]
			);
	}
}
