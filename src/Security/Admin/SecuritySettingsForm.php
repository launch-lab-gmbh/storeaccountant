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

namespace StoreAccountant\Security\Admin;

use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use function esc_attr;
use function esc_html_e;
use function is_wp_error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders security-related plugin settings.
 */
final readonly class SecuritySettingsForm {
	/**
	 * Initializes the form.
	 */
	public function __construct(
		private DownloadPasswordManager $passwords,
		private PermissionChecker $permissions
	) {}

	/**
	 * Renders the security settings fields.
	 */
	public function render_fields(): void {
		$this->passwords->ensure_global_password();
		?>
		<tr>
			<th scope="row">
				<label for="storeaccountant-global-download-password"><?php esc_html_e( 'Global Download Password', 'storeaccountant' ); ?></label>
			</th>
			<td>
				<?php if ( ! $this->passwords->is_available() ) : ?>
					<div class="notice notice-error inline">
						<p><?php esc_html_e( 'Password-protected public export downloads are unavailable because this server provides neither Sodium nor OpenSSL encryption.', 'storeaccountant' ); ?></p>
					</div>
				<?php else : ?>
					<input
						type="password"
						id="storeaccountant-global-download-password"
						name="storeaccountant_global_download_password"
						class="regular-text"
						value=""
						autocomplete="new-password"
					/>
					<p class="description"><?php esc_html_e( 'Enter a new password to replace the current global export download password. Leave this field empty to keep the existing password.', 'storeaccountant' ); ?></p>
					<?php if ( $this->permissions->can( PermissionActionIds::VIEW_DOWNLOAD_PASSWORDS ) ) : ?>
						<?php $revealed_password = $this->passwords->reveal_global_password(); ?>
						<?php if ( ! is_wp_error( $revealed_password ) ) : ?>
							<p>
								<label for="storeaccountant-current-global-download-password"><?php esc_html_e( 'Current Download Password', 'storeaccountant' ); ?></label><br />
								<input
									type="text"
									id="storeaccountant-current-global-download-password"
									class="regular-text"
									value="<?php echo esc_attr( $revealed_password ); ?>"
									readonly="readonly"
								/>
							</p>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
