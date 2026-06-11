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

use StoreAccountant\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test registry for registry unit tests.
 */
final readonly class TestRegistry extends Registry {
	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant/test_registry';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return TestRegistryItem::class;
	}
}
