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
use StoreAccountant\Export\Contract\FieldProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldProviderRegistry;

/**
 * Tests the export field provider registry.
 */
final class FieldProviderRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_fields_collects_fields_from_supported_providers(): void {
		$context = new ExportContext( 'orders' );
		$field   = new Field( 'order_number', 'Order Number' );

		$supported = $this->createMock( FieldProviderInterface::class );
		$supported->method( 'get_id' )->willReturn( 'supported' );
		$supported->expects( self::once() )->method( 'supports' )->with( $context )->willReturn( true );
		$supported->expects( self::once() )->method( 'get_fields' )->with( $context )->willReturn( [ $field ] );

		$unsupported = $this->createMock( FieldProviderInterface::class );
		$unsupported->method( 'get_id' )->willReturn( 'unsupported' );
		$unsupported->expects( self::once() )->method( 'supports' )->with( $context )->willReturn( false );
		$unsupported->expects( self::never() )->method( 'get_fields' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_field_provider', [] )
			->andReturn( [ $unsupported, $supported ] );

		$fields = ( new FieldProviderRegistry() )->get_fields( $context );

		self::assertSame( [ 'order_number' => $field ], $fields->all() );
	}

	public function test_get_fields_keeps_last_field_when_providers_return_duplicate_ids(): void {
		$context = new ExportContext( 'orders' );
		$first   = new Field( 'total', 'First Total' );
		$second  = new Field( 'total', 'Second Total' );

		$first_provider  = $this->provider_returning_fields( 'first', $context, [ $first ] );
		$second_provider = $this->provider_returning_fields( 'second', $context, [ $second ] );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_field_provider', [] )
			->andReturn( [ $first_provider, $second_provider ] );

		self::assertSame( [ 'total' => $second ], ( new FieldProviderRegistry() )->get_fields( $context )->all() );
	}

	/**
	 * Builds a field provider mock.
	 *
	 * @param array<int, Field> $fields Fields returned by provider.
	 */
	private function provider_returning_fields( string $id, ExportContext $context, array $fields ): FieldProviderInterface {
		$provider = $this->createMock( FieldProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( $id );
		$provider->method( 'supports' )->with( $context )->willReturn( true );
		$provider->method( 'get_fields' )->with( $context )->willReturn( $fields );

		return $provider;
	}
}
