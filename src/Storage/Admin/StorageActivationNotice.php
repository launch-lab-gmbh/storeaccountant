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

namespace StoreAccountant\Storage\Admin;

use StoreAccountant\Contract\HookRegistrarInterface;
use function is_string;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders one-time activation notices for local storage preparation failures.
 */
final readonly class StorageActivationNotice implements HookRegistrarInterface {
	public const TRANSIENT_NAME = 'storeaccountant_storage_activation_error';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
	}

	/**
	 * Renders and clears the activation notice.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render(): void {
		$message = get_transient( self::TRANSIENT_NAME );

		if ( ! is_string( $message ) || '' === $message ) {
			return;
		}

		delete_transient( self::TRANSIENT_NAME );
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}
}
