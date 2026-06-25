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

use StoreAccountant\Contract\HookRegistrarInterface;
use function __;
use function add_action;
use function add_submenu_page;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function wp_die;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the StoreAccountant support page.
 */
final readonly class AccountingSupportPage implements HookRegistrarInterface {
	public const PAGE_SLUG = 'storeaccountant-support';

	private const SUPPORT_EMAIL = 'storeaccountant@launch-lab.de';

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private AccountingHeaderBar $header_bar,
		private AccountingSupportAccess $access
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_page' ], 11 );
	}

	/**
	 * Registers the hidden support page.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register_page(): void {
		add_submenu_page(
			'options.php',
			__( 'Support', 'storeaccountant' ),
			__( 'Support', 'storeaccountant' ),
			'read',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Renders the support page.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render(): void {
		if ( ! $this->access->can_access() ) {
			wp_die( esc_html__( 'You are not allowed to access StoreAccountant support.', 'storeaccountant' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'StoreAccountant Support', 'storeaccountant' ); ?></h1>

			<?php $this->header_bar->render_support_actions(); ?>

			<div class="storeaccountant-tab-panel">
				<h2><?php esc_html_e( 'Found a bug or error?', 'storeaccountant' ); ?></h2>
				<p><?php esc_html_e( 'Have you found bugs or errors in StoreAccountant? Please contact us so we can help and improve the plugin.', 'storeaccountant' ); ?></p>

				<h2><?php esc_html_e( 'Custom development', 'storeaccountant' ); ?></h2>
				<p><?php esc_html_e( 'StoreAccountant already covers many use cases for WooCommerce order exports, customer exports, CSV exports, and JSON exports. If you need custom export formats, ERP connections, accounting integrations, or individual WooCommerce extensions, we are happy to support you with the implementation.', 'storeaccountant' ); ?></p>

				<p>
					<a class="button button-primary" href="<?php echo esc_url( 'mailto:' . self::SUPPORT_EMAIL ); ?>">
						<?php esc_html_e( 'Contact StoreAccountant Support', 'storeaccountant' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}
}
