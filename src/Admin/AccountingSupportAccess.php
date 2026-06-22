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

namespace StoreAccountant\Admin;

use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use function current_user_can;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether the current user can open the Support page.
 */
final readonly class AccountingSupportAccess {
	/**
	 * Checks whether the current user has any StoreAccountant backend access.
	 */
	public function can_access(): bool {
		return current_user_can( 'manage_options' )
			|| current_user_can( StoreAccountantCapabilities::ACCESS_ADMIN )
			|| current_user_can( StoreAccountantCapabilities::READ_EXPORTS )
			|| current_user_can( StoreAccountantCapabilities::READ_CONFIGURATIONS )
			|| current_user_can( StoreAccountantCapabilities::MANAGE_SETTINGS )
			|| current_user_can( StoreAccountantCapabilities::MANAGE_PERMISSIONS )
			|| current_user_can( StoreAccountantCapabilities::MANAGE_DIAGNOSTICS );
	}
}
