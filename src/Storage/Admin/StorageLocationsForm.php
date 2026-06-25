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

use StoreAccountant\Storage\StorageAdapterRegistry;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\I18n;
use function array_key_first;
use function array_map;
use function count;
use function in_array;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders storage location settings.
 */
final readonly class StorageLocationsForm {
	/**
	 * Initializes the form.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param StorageAdapterRegistry $storage_adapters storage adapter registry.
	 */
	public function __construct(
		private StorageAdapterRegistry $storage_adapters
	) {}

	/**
	 * Renders the storage locations fields.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render_fields(): void {
		$enabled  = array_map(
			static fn ( StorageAdapterInterface $adapter ): string => $adapter->get_id(),
			$this->storage_adapters->get_enabled()
		);
		$adapters = $this->storage_adapters->get_all();
		$locked   = 1 === count( $adapters );

		if ( $locked ) {
			$enabled = [ (string) array_key_first( $adapters ) ];
		}
		?>
		<?php foreach ( $adapters as $storage_adapter ) : ?>
			<tr>
				<th scope="row">
					<?php
						echo esc_html( I18n::translate_registry_label( 'storage_adapter_', $storage_adapter->get_id() ) );
					?>
				</th>
				<td>
					<label for="storeaccountant-storage-engine-<?php echo esc_attr( $storage_adapter->get_id() ); ?>">
						<input
							type="checkbox"
							id="storeaccountant-storage-engine-<?php echo esc_attr( $storage_adapter->get_id() ); ?>"
							name="storeaccountant_enabled_storage_adapters[]"
							value="<?php echo esc_attr( $storage_adapter->get_id() ); ?>"
							<?php checked( in_array( $storage_adapter->get_id(), $enabled, true ) ); ?>
							<?php disabled( $locked ); ?>
						/>
						<?php if ( $locked ) : ?>
							<input type="hidden" name="storeaccountant_enabled_storage_adapters[]" value="<?php echo esc_attr( $storage_adapter->get_id() ); ?>" />
						<?php endif; ?>
						<?php esc_html_e( 'Enable this storage location for exports.', 'storeaccountant' ); ?>
					</label>
					<?php if ( $locked ) : ?>
						<p class="description"><?php esc_html_e( 'This is the only available storage location and cannot be disabled.', 'storeaccountant' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php
	}
}
