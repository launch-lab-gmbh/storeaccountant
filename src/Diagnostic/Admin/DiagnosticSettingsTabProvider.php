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

namespace StoreAccountant\Diagnostic\Admin;

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Diagnostic\DiagnosticIncidentRepository;
use StoreAccountant\Diagnostic\DiagnosticSettings;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Settings\Contract\PluginSettingsTabProviderInterface;
use function __;
use function add_filter;
use function array_key_exists;
use function checked;
use function disabled;
use function esc_html;
use function esc_html_e;
use function is_wp_error;
use function wp_die;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders diagnostic logging settings.
 */
final readonly class DiagnosticSettingsTabProvider implements HookRegistrarInterface, PluginSettingsTabProviderInterface {
	private const TAB_ID = 'diagnostics';

	/**
	 * Initializes the tab provider.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param DiagnosticSettings $settings    Diagnostic settings.
	 * @param PermissionChecker  $permissions Permission checker.
	 * @param DiagnosticIncidentRepository $repository  Incident repository.
	 */
	public function __construct(
		private DiagnosticSettings $settings,
		private PermissionChecker $permissions,
		private DiagnosticIncidentRepository $repository
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_plugin_settings_tab_provider',
			function ( array $providers ): array {
				$providers[ $this->get_id() ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return self::TAB_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_tabs(): array {
		return [
			self::TAB_ID => __( 'Diagnostics', 'storeaccountant' ),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render( string $tab ): void {
		if ( self::TAB_ID !== $tab ) {
			return;
		}

		$can_manage = $this->permissions->can( PermissionActionIds::DIAGNOSTIC_LOGGING_MANAGE );
		?>
		<tr>
			<th scope="row" colspan="2">
				<h3><?php esc_html_e( 'Diagnostic logging', 'storeaccountant' ); ?></h3>
				<p class="description"><?php esc_html_e( 'StoreAccountant can create a protected diagnostic package when an admin-facing error occurs. Diagnostic packages are intended for support and do not include passwords, nonces, or export file contents.', 'storeaccountant' ); ?></p>
			</th>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable diagnostic logging', 'storeaccountant' ); ?></th>
			<td>
				<label for="storeaccountant-diagnostic-logging-enabled">
					<input
						type="checkbox"
						id="storeaccountant-diagnostic-logging-enabled"
						name="storeaccountant_diagnostic_logging_enabled"
						value="1"
						<?php checked( $this->settings->is_enabled() ); ?>
						<?php disabled( ! $can_manage ); ?>
					/>
					<?php esc_html_e( 'Create protected diagnostic packages for StoreAccountant support.', 'storeaccountant' ); ?>
				</label>
				<?php if ( ! $can_manage ) : ?>
					<p class="description"><?php esc_html_e( 'You are not allowed to change diagnostic logging settings.', 'storeaccountant' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function save( string $tab, array $request ): void {
		if ( self::TAB_ID !== $tab || ! $this->permissions->can( PermissionActionIds::DIAGNOSTIC_LOGGING_MANAGE ) ) {
			return;
		}

		$enabled = array_key_exists( 'storeaccountant_diagnostic_logging_enabled', $request );

		if ( $enabled ) {
			$result = $this->repository->ensure();

			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ) );
			}
		}

		$this->settings->save_enabled( $enabled );
	}
}
