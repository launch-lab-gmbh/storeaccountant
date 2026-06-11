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

namespace StoreAccountant\Tests\Unit\Export\Field;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValueProviderRegistry;

/**
 * Tests the export field value provider registry.
 */
final class FieldValueProviderRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_providers_returns_providers_supporting_at_least_one_field(): void {
		$context = new ExportContext( 'orders' );
		$field   = new Field( 'total', 'Total' );
		$fields  = new FieldCollection( [ $field ] );

		$supported = $this->createMock( FieldValueProviderInterface::class );
		$supported->method( 'get_id' )->willReturn( 'supported' );
		$supported->expects( self::once() )->method( 'supports' )->with( $field, $context )->willReturn( true );

		$unsupported = $this->createMock( FieldValueProviderInterface::class );
		$unsupported->method( 'get_id' )->willReturn( 'unsupported' );
		$unsupported->expects( self::once() )->method( 'supports' )->with( $field, $context )->willReturn( false );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_field_value_provider', [] )
			->andReturn( [ $unsupported, $supported ] );

		self::assertSame(
			[ 'supported' => $supported ],
			( new FieldValueProviderRegistry() )->get_providers( $fields, $context )
		);
	}

	public function test_get_providers_stops_checking_fields_after_first_supported_field(): void {
		$context = new ExportContext( 'orders' );
		$first   = new Field( 'order_number', 'Order Number' );
		$second  = new Field( 'total', 'Total' );
		$fields  = new FieldCollection( [ $first, $second ] );

		$provider = $this->createMock( FieldValueProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( 'provider' );
		$provider->expects( self::once() )->method( 'supports' )->with( $first, $context )->willReturn( true );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_field_value_provider', [] )
			->andReturn( [ $provider ] );

		self::assertSame(
			[ 'provider' => $provider ],
			( new FieldValueProviderRegistry() )->get_providers( $fields, $context )
		);
	}
}
