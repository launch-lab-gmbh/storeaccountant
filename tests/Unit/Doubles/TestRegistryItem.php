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
 * Valid test registry item.
 */
final readonly class TestRegistryItem implements RegistryItemInterface {
	/**
	 * Initializes the test registry item.
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
