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
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\Mutator\AmountMutator;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Export\Field\Type\StringFieldType;

/**
 * Tests amount value mutation.
 */
final class AmountMutatorTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_mutator_filter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_value_mutator', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		( new AmountMutator() )->register();

		self::assertTrue( true );
	}

	public function test_get_id_returns_stable_id(): void {
		self::assertSame( AmountMutator::MUTATOR_ID, ( new AmountMutator() )->get_id() );
	}

	public function test_supports_decimal_number_fields_only(): void {
		$mutator = new AmountMutator();
		$context = new ExportContext( 'orders' );

		self::assertTrue( $mutator->supports( new Field( 'total', 'Total', new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ) ), $context ) );
		self::assertFalse( $mutator->supports( new Field( 'quantity', 'Quantity', new NumberFieldType( NumberFieldType::FORMAT_INTEGER ) ), $context ) );
		self::assertFalse( $mutator->supports( new Field( 'name', 'Name', new StringFieldType() ), $context ) );
	}

	#[DataProvider( 'provide_amount_values' )]
	public function test_mutate_converts_decimal_amounts_to_minor_units( mixed $amount, string $expected ): void {
		$value = new FieldValue( 'total', $amount, [ 'source' => 'test' ] );

		$mutated = ( new AmountMutator() )->mutate(
			$value,
			new Field( 'total', 'Total', new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ) ),
			[ AmountMutator::OPTION_AMOUNT_FORMAT => AmountMutator::FORMAT_CENTS ],
			new ExportContext( 'orders' )
		);

		self::assertNotSame( $value, $mutated );
		self::assertSame( 'total', $mutated->field_id );
		self::assertSame( $expected, $mutated->value );
		self::assertSame( [ 'source' => 'test' ], $mutated->options );
	}

	/**
	 * @return array<string, array{mixed, string}>
	 */
	public static function provide_amount_values(): array {
		return [
			'plain amount'  => [ '12.34', '1234' ],
			'comma amount'  => [ '12,30', '1230' ],
			'one decimal'   => [ '12.3', '1230' ],
			'negative'      => [ '-1.25', '-125' ],
			'zero'          => [ '0.00', '0' ],
			'leading zeros' => [ '0003.05', '305' ],
		];
	}

	public function test_mutate_keeps_original_value_for_default_format_or_invalid_amount(): void {
		$mutator = new AmountMutator();
		$field   = new Field( 'total', 'Total', new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ) );
		$context = new ExportContext( 'orders' );
		$value   = new FieldValue( 'total', '12.34' );
		$invalid = new FieldValue( 'total', 'not-an-amount' );

		self::assertSame( $value, $mutator->mutate( $value, $field, [], $context ) );
		self::assertSame(
			$invalid,
			$mutator->mutate(
				$invalid,
				$field,
				[ AmountMutator::OPTION_AMOUNT_FORMAT => AmountMutator::FORMAT_CENTS ],
				$context
			)
		);
	}
}
