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

namespace StoreAccountant;

use StoreAccountant\Contract\HookRegistrarInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires plugin components into WordPress.
 */
final readonly class Plugin {
	/**
	 * Boots plugin services.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function boot(): void {
		$container = ( new ContainerBuilder() )->build();

		foreach ( ContainerBuilder::HOOK_SERVICES as $service ) {
			$registrar = $container->get( $service );

			if ( $registrar instanceof HookRegistrarInterface ) {
				$registrar->register();
			}
		}
	}
}
