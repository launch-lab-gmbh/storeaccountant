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

namespace StoreAccountant\Export\Field\Mutator;

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\FieldValueMutatorInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use function explode;
use function is_scalar;
use function ltrim;
use function preg_match;
use function str_pad;
use function str_replace;
use function str_starts_with;
use function substr;
use function trim;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mutates decimal field values into the configured output format.
 */
final readonly class AmountMutator implements FieldValueMutatorInterface, HookRegistrarInterface {
	public const MUTATOR_ID           = 'amount';
	public const OPTION_AMOUNT_FORMAT = 'amount_format';
	public const FORMAT_AMOUNT        = 'amount';
	public const FORMAT_CENTS         = 'cents';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_field_value_mutator',
			function ( array $mutators ): array {
				$mutators[ self::MUTATOR_ID ] = $this;

				return $mutators;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return self::MUTATOR_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports( Field $field, ExportContext $context ): bool {
		return $field->type instanceof NumberFieldType && $field->type->is_decimal();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function mutate( FieldValue $value, Field $field, array $settings, ExportContext $context ): FieldValue {
		if ( self::FORMAT_CENTS !== ( $settings[ self::OPTION_AMOUNT_FORMAT ] ?? self::FORMAT_AMOUNT ) ) {
			return $value;
		}

		$minor_units = $this->to_minor_units( $value->value );

		if ( null === $minor_units ) {
			return $value;
		}

		return new FieldValue( $value->field_id, $minor_units, $value->options );
	}

	/**
	 * Converts a normalized decimal amount string into minor units.
	 *
	 * @param mixed $amount Amount value.
	 */
	private function to_minor_units( mixed $amount ): ?string {
		if ( ! is_scalar( $amount ) ) {
			return null;
		}

		$amount = str_replace( ',', '.', trim( (string) $amount ) );

		if ( ! preg_match( '/^[+-]?\d+(?:\.\d{1,2})?$/', $amount ) ) {
			return null;
		}

		$negative = str_starts_with( $amount, '-' );
		$amount   = str_replace( [ '+', '-' ], '', $amount );
		$parts    = explode( '.', $amount, 2 );
		$major    = $parts[0];
		$minor    = str_pad( $parts[1] ?? '', 2, '0' );
		$cents    = ltrim( $major . substr( $minor, 0, 2 ), '0' );
		$cents    = '' === $cents ? '0' : $cents;

		return ( $negative && '0' !== $cents ? '-' : '' ) . $cents;
	}
}
