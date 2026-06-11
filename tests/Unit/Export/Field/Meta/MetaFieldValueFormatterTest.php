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

namespace StoreAccountant\Tests\Unit\Export\Field\Meta;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Field\Meta\MetaFieldValueFormatter;

/**
 * Tests metadata value formatting.
 */
final class MetaFieldValueFormatterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	#[DataProvider( 'provide_scalar_values' )]
	public function test_format_returns_scalar_values_as_strings( mixed $value, string $expected ): void {
		self::assertSame( $expected, ( new MetaFieldValueFormatter() )->format( $value ) );
	}

	/**
	 * @return array<string, array{mixed, string}>
	 */
	public static function provide_scalar_values(): array {
		return [
			'null'   => [ null, '' ],
			'false'  => [ false, '' ],
			'true'   => [ true, '1' ],
			'string' => [ 'VAT-123', 'VAT-123' ],
			'int'    => [ 123, '123' ],
			'float'  => [ 12.5, '12.5' ],
		];
	}

	public function test_format_returns_stringable_values_as_strings(): void {
		$value = new class() {
			public function __toString(): string {
				return 'stringable-value';
			}
		};

		self::assertSame( 'stringable-value', ( new MetaFieldValueFormatter() )->format( $value ) );
	}

	public function test_format_json_encodes_arrays_and_objects(): void {
		$value = [ 'vat' => 'VAT-123' ];

		Functions\expect( 'wp_json_encode' )
			->once()
			->with( $value )
			->andReturn( '{"vat":"VAT-123"}' );

		self::assertSame( '{"vat":"VAT-123"}', ( new MetaFieldValueFormatter() )->format( $value ) );
	}

	public function test_format_returns_empty_string_when_json_encoding_fails(): void {
		$value = [ 'invalid' => NAN ];

		Functions\expect( 'wp_json_encode' )
			->once()
			->with( $value )
			->andReturn( false );

		self::assertSame( '', ( new MetaFieldValueFormatter() )->format( $value ) );
	}
}
