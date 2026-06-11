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

namespace StoreAccountant\Export\Field\Type;

use StoreAccountant\Export\Field\FieldTypeInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes an extension-defined field type.
 */
final readonly class CustomFieldType implements FieldTypeInterface {
	/**
	 * Initializes the custom field type.
	 *
	 * @param string $id Stable field type identifier.
	 */
	public function __construct(
		private string $id
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return $this->id;
	}
}
