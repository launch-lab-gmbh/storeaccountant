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

namespace StoreAccountant\Tests\Unit\Export\Admin\Period;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Admin\Period\Contract\ExportPeriodFieldProviderInterface;
use StoreAccountant\Export\Admin\Period\Contract\ExportPeriodViewProviderInterface;
use StoreAccountant\Export\Admin\Period\ExportPeriodFieldProviderResolver;
use StoreAccountant\Export\Admin\Period\MonthYearExportPeriodFieldProvider;
use StoreAccountant\Export\ExportPeriod;
use WP_Error;

/**
 * Tests active export period provider resolution.
 */
final class ExportPeriodFieldProviderResolverTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_returns_filtered_field_provider(): void {
		$provider = $this->createMock( ExportPeriodFieldProviderInterface::class );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_period_field_provider', null )
			->andReturn( $provider );

		self::assertSame( $provider, ( new ExportPeriodFieldProviderResolver() )->get() );
	}

	public function test_get_returns_default_field_provider_when_filter_result_is_invalid(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_period_field_provider', null )
			->andReturn( 'invalid' );

		self::assertInstanceOf( MonthYearExportPeriodFieldProvider::class, ( new ExportPeriodFieldProviderResolver() )->get() );
	}

	public function test_get_view_provider_returns_filtered_view_provider(): void {
		$provider = $this->createMock( ExportPeriodViewProviderInterface::class );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_period_view_provider', null )
			->andReturn( $provider );

		self::assertSame( $provider, ( new ExportPeriodFieldProviderResolver() )->get_view_provider() );
	}

	public function test_get_view_provider_uses_field_provider_when_it_supports_view_interface(): void {
		$provider = new class() implements ExportPeriodFieldProviderInterface, ExportPeriodViewProviderInterface {
			public function render( ?ExportPeriod $period = null, array $selection = [], bool $read_only = false ): void {}
			public function get_period_from_request( array $request ): ExportPeriod|WP_Error {
				return new ExportPeriod( '2026-01-01 00:00:00', '2026-01-31 23:59:59' );
			}
			public function get_period_selection_from_request( array $request ): array {
				return []; }
			public function get_period_from_selection( array $selection ): ExportPeriod|WP_Error {
				return new ExportPeriod( '2026-01-01 00:00:00', '2026-01-31 23:59:59' );
			}
			public function stores_concrete_period( array $selection ): bool {
				return false; }
			public function get_default_title_suffix(): string {
				return ''; }
			public function format_period_label( ExportPeriod $period ): string {
				return ''; }
		};

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_period_view_provider', null )
			->andReturn( null );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_period_field_provider', null )
			->andReturn( $provider );

		self::assertSame( $provider, ( new ExportPeriodFieldProviderResolver() )->get_view_provider() );
	}
}
