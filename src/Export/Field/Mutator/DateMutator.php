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

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\FieldValueMutatorInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use function is_numeric;
use function is_scalar;
use function preg_match;
use function trim;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mutates date and datetime field values into the configured output format.
 */
final readonly class DateMutator implements FieldValueMutatorInterface, HookRegistrarInterface {
	public const MUTATOR_ID                     = 'date';
	public const OPTION_DATE_FORMAT             = 'date_format';
	public const FORMAT_ORIGINAL                = 'original';
	public const FORMAT_DATE_ISO                = 'date_iso';
	public const FORMAT_DATE_GERMAN             = 'date_german';
	public const FORMAT_DATE_SLASH              = 'date_slash';
	public const FORMAT_DATE_COMPACT            = 'date_compact';
	public const FORMAT_DATETIME_ISO            = 'datetime_iso';
	public const FORMAT_DATETIME_GERMAN         = 'datetime_german';
	public const FORMAT_DATETIME_GERMAN_SECONDS = 'datetime_german_seconds';
	public const FORMAT_DATETIME_LOCAL          = 'datetime_local';
	public const FORMAT_DATETIME_RFC3339        = 'datetime_rfc3339';
	public const FORMAT_TIMESTAMP               = 'timestamp';

	/**
	 * Date format map keyed by stored option ID.
	 *
	 * @var array<string, string>
	 */
	private const FORMAT_MAP = [
		self::FORMAT_DATE_ISO                => 'Y-m-d',
		self::FORMAT_DATE_GERMAN             => 'd.m.Y',
		self::FORMAT_DATE_SLASH              => 'm/d/Y',
		self::FORMAT_DATE_COMPACT            => 'Ymd',
		self::FORMAT_DATETIME_ISO            => 'Y-m-d H:i:s',
		self::FORMAT_DATETIME_GERMAN         => 'd.m.Y H:i',
		self::FORMAT_DATETIME_GERMAN_SECONDS => 'd.m.Y H:i:s',
		self::FORMAT_DATETIME_LOCAL          => 'Y-m-d\TH:i',
		self::FORMAT_DATETIME_RFC3339        => DateTimeInterface::RFC3339,
		self::FORMAT_TIMESTAMP               => 'U',
	];

	/**
	 * Input formats accepted before falling back to DateTime's parser.
	 *
	 * @var array<int, string>
	 */
	private const INPUT_FORMATS = [
		'Y-m-d H:i:s',
		'Y-m-d H:i',
		'Y-m-d',
		'Y-m-d\TH:i:sP',
		'Y-m-d\TH:i:s',
		'Y-m-d\TH:i',
		'd.m.Y H:i:s',
		'd.m.Y H:i',
		'd.m.Y',
		'm/d/Y H:i:s',
		'm/d/Y H:i',
		'm/d/Y',
		'Ymd',
	];

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
		return $field->type instanceof DateTimeFieldType;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function mutate( FieldValue $value, Field $field, array $settings, ExportContext $context ): FieldValue {
		$format = $this->get_format( $settings );

		if ( null === $format ) {
			return $value;
		}

		$date = $this->parse_date( $value->value );

		if ( null === $date ) {
			return $value;
		}

		try {
			return new FieldValue( $value->field_id, $date->format( $format ), $value->options );
		} catch ( Throwable ) {
			return $value;
		}
	}

	/**
	 * Gets selectable output formats for admin UIs.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array<string, string>
	 */
	public static function get_format_labels(): array {
		return [
			self::FORMAT_ORIGINAL                => __( 'Original value', 'storeaccountant' ),
			self::FORMAT_DATE_ISO                => __( 'Date: 2026-05-24', 'storeaccountant' ),
			self::FORMAT_DATE_GERMAN             => __( 'Date: 24.05.2026', 'storeaccountant' ),
			self::FORMAT_DATE_SLASH              => __( 'Date: 05/24/2026', 'storeaccountant' ),
			self::FORMAT_DATE_COMPACT            => __( 'Date: 20260524', 'storeaccountant' ),
			self::FORMAT_DATETIME_ISO            => __( 'Date/time: 2026-05-24 14:30:00', 'storeaccountant' ),
			self::FORMAT_DATETIME_GERMAN         => __( 'Date/time: 24.05.2026 14:30', 'storeaccountant' ),
			self::FORMAT_DATETIME_GERMAN_SECONDS => __( 'Date/time: 24.05.2026 14:30:00', 'storeaccountant' ),
			self::FORMAT_DATETIME_LOCAL          => __( 'Date/time: 2026-05-24T14:30', 'storeaccountant' ),
			self::FORMAT_DATETIME_RFC3339        => __( 'Date/time: 2026-05-24T14:30:00+00:00', 'storeaccountant' ),
			self::FORMAT_TIMESTAMP               => __( 'Unix timestamp', 'storeaccountant' ),
		];
	}

	/**
	 * Gets a sanitized date format option.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param mixed $format Format option.
	 */
	public static function sanitize_format( mixed $format ): string {
		return is_scalar( $format ) && isset( self::get_format_labels()[ (string) $format ] ) ? (string) $format : self::FORMAT_ORIGINAL;
	}

	/**
	 * Gets the PHP date format for the selected setting.
	 *
	 * @param array<string, mixed> $settings Mutator settings.
	 */
	private function get_format( array $settings ): ?string {
		$format_id = self::sanitize_format( $settings[ self::OPTION_DATE_FORMAT ] ?? self::FORMAT_ORIGINAL );

		if ( self::FORMAT_ORIGINAL === $format_id ) {
			return null;
		}

		return self::FORMAT_MAP[ $format_id ] ?? null;
	}

	/**
	 * Parses a date value while swallowing invalid inputs.
	 *
	 * @param mixed $value Date value.
	 */
	private function parse_date( mixed $value ): ?DateTimeInterface {
		if ( $value instanceof DateTimeInterface ) {
			return $value;
		}

		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return null;
		}

		foreach ( self::INPUT_FORMATS as $format ) {
			$date = $this->parse_from_format( $format, $value );

			if ( null !== $date ) {
				return $date;
			}
		}

		if ( is_numeric( $value ) && preg_match( '/^-?\d+$/', $value ) ) {
			try {
				return new DateTimeImmutable( '@' . $value );
			} catch ( Throwable ) {
				return null;
			}
		}

		try {
			return new DateTimeImmutable( $value );
		} catch ( Throwable ) {
			return null;
		}
	}

	/**
	 * Parses a date through a concrete input format.
	 *
	 * @param string $format Date input format.
	 * @param string $value  Date input value.
	 */
	private function parse_from_format( string $format, string $value ): ?DateTimeInterface {
		try {
			$date = DateTimeImmutable::createFromFormat( $format, $value );

			if ( false === $date ) {
				return null;
			}

			$errors = DateTimeImmutable::getLastErrors();

			if ( false !== $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) {
				return null;
			}

			return $date;
		} catch ( Throwable ) {
			return null;
		}
	}
}
