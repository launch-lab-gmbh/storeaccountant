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

namespace StoreAccountant\Tests\Unit\Export\Field\Mutator;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\Mutator\DateMutator;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Export\Field\Type\StringFieldType;

/**
 * Tests date value mutation.
 */
final class DateMutatorTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_mutator_filter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_value_mutator', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		( new DateMutator() )->register();

		self::assertTrue( true );
	}

	public function test_get_id_returns_stable_id(): void {
		self::assertSame( DateMutator::MUTATOR_ID, ( new DateMutator() )->get_id() );
	}

	public function test_supports_date_time_fields_only(): void {
		$mutator = new DateMutator();
		$context = new ExportContext( 'orders' );

		self::assertTrue( $mutator->supports( new Field( 'created_at', 'Created', new DateTimeFieldType() ), $context ) );
		self::assertFalse( $mutator->supports( new Field( 'name', 'Name', new StringFieldType() ), $context ) );
	}

	public function test_sanitize_format_accepts_known_format_and_falls_back_for_unknown_value(): void {
		self::assertSame( DateMutator::FORMAT_DATE_GERMAN, DateMutator::sanitize_format( DateMutator::FORMAT_DATE_GERMAN ) );
		self::assertSame( DateMutator::FORMAT_ORIGINAL, DateMutator::sanitize_format( 'unknown' ) );
		self::assertSame( DateMutator::FORMAT_ORIGINAL, DateMutator::sanitize_format( [ 'not-scalar' ] ) );
	}

	public function test_get_format_labels_contains_all_supported_formats(): void {
		$labels = DateMutator::get_format_labels();

		self::assertArrayHasKey( DateMutator::FORMAT_ORIGINAL, $labels );
		self::assertArrayHasKey( DateMutator::FORMAT_DATE_ISO, $labels );
		self::assertArrayHasKey( DateMutator::FORMAT_DATETIME_RFC3339, $labels );
		self::assertArrayHasKey( DateMutator::FORMAT_TIMESTAMP, $labels );
		self::assertSame( 'Original value', $labels[ DateMutator::FORMAT_ORIGINAL ] );
	}

	public function test_mutate_formats_datetime_interface_values(): void {
		$value = new FieldValue( 'created_at', new DateTimeImmutable( '2026-05-24 14:30:00' ), [ 'source' => 'test' ] );

		$mutated = ( new DateMutator() )->mutate(
			$value,
			new Field( 'created_at', 'Created', new DateTimeFieldType() ),
			[ DateMutator::OPTION_DATE_FORMAT => DateMutator::FORMAT_DATE_GERMAN ],
			new ExportContext( 'orders' )
		);

		self::assertSame( '24.05.2026', $mutated->value );
		self::assertSame( [ 'source' => 'test' ], $mutated->options );
	}

	public function test_mutate_formats_string_and_timestamp_values(): void {
		$mutator = new DateMutator();
		$field   = new Field( 'created_at', 'Created', new DateTimeFieldType() );
		$context = new ExportContext( 'orders' );

		self::assertSame(
			'2026-05-24',
			$mutator->mutate(
				new FieldValue( 'created_at', '2026-05-24 14:30:00' ),
				$field,
				[ DateMutator::OPTION_DATE_FORMAT => DateMutator::FORMAT_DATE_ISO ],
				$context
			)->value
		);
		self::assertSame(
			'0',
			$mutator->mutate(
				new FieldValue( 'created_at', '0' ),
				$field,
				[ DateMutator::OPTION_DATE_FORMAT => DateMutator::FORMAT_TIMESTAMP ],
				$context
			)->value
		);
	}

	public function test_mutate_keeps_original_value_for_original_format_or_invalid_value(): void {
		$mutator = new DateMutator();
		$field   = new Field( 'created_at', 'Created', new DateTimeFieldType() );
		$context = new ExportContext( 'orders' );
		$value   = new FieldValue( 'created_at', 'not-a-date' );

		self::assertSame( $value, $mutator->mutate( $value, $field, [ DateMutator::OPTION_DATE_FORMAT => DateMutator::FORMAT_ORIGINAL ], $context ) );
		self::assertSame( $value, $mutator->mutate( $value, $field, [ DateMutator::OPTION_DATE_FORMAT => DateMutator::FORMAT_DATE_ISO ], $context ) );
	}
}
