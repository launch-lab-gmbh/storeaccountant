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

namespace StoreAccountant\Tests\Unit\Doubles;

use StoreAccountant\Contract\RegistryItemInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry item with the wrong concrete type for registry unit tests.
 */
final readonly class OtherRegistryItem implements RegistryItemInterface {
	/**
	 * Initializes the other registry item.
	 *
	 * @param string $id Item ID.
	 */
	public function __construct(
		private string $id,
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return $this->id;
	}
}
