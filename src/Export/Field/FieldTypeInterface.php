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
 * Describes the semantic value type of an export field.
 */
interface FieldTypeInterface {
	/**
	 * Gets the stable field type identifier.
	 */
	public function get_id(): string;
}
