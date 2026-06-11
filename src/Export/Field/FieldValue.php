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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds one value for a field in an export record.
 */
final readonly class FieldValue {
	/**
	 * Initializes the export value.
	 *
	 * @param string               $field_id Field identifier.
	 * @param mixed                $value    Field value.
	 * @param array<string, mixed> $options  Additional value options.
	 */
	public function __construct(
		public string $field_id,
		public mixed $value,
		public array $options = []
	) {}
}
