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

namespace StoreAccountant\Export\Field;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use function array_intersect_key;
use function array_keys;
use function count;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds export fields keyed by their stable identifier.
 *
 * @implements IteratorAggregate<string, Field>
 */
final readonly class FieldCollection implements IteratorAggregate, Countable {
	/**
	 * Fields keyed by identifier.
	 *
	 * @var array<string, Field>
	 */
	private array $fields;

	/**
	 * Initializes the field collection.
	 *
	 * @param array<string, Field> $fields Fields keyed by identifier.
	 */
	public function __construct( array $fields = [] ) {
		$indexed_fields = [];

		foreach ( $fields as $field ) {
			if ( ! $field instanceof Field || '' === $field->id ) {
				continue;
			}

			$indexed_fields[ $field->id ] = $field;
		}

		$this->fields = $indexed_fields;
	}

	/**
	 * Gets all fields keyed by identifier.
	 *
	 * @return array<string, Field>
	 */
	public function all(): array {
		return $this->fields;
	}

	/**
	 * Gets all field identifiers in collection order.
	 *
	 * @return array<int, string>
	 */
	public function ids(): array {
		return array_keys( $this->fields );
	}

	/**
	 * Checks whether the field exists.
	 *
	 * @param string $id Field identifier.
	 */
	public function has( string $id ): bool {
		return isset( $this->fields[ $id ] );
	}

	/**
	 * Keeps only values for fields contained in the collection.
	 *
	 * @param array<string, FieldValue> $values Values keyed by field identifier.
	 *
	 * @return array<string, FieldValue>
	 */
	public function filter_values( array $values ): array {
		return array_intersect_key( $values, $this->fields );
	}

	/**
	 * {@inheritDoc}
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->fields );
	}

	/**
	 * {@inheritDoc}
	 */
	public function count(): int {
		return count( $this->fields );
	}
}
