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

use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\RolePermissionRepository;
use StoreAccountant\Security\Permission\Contract\PermissionActionInterface;
use function array_values;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders StoreAccountant permission role assignments.
 */
final readonly class PermissionsSettingsForm {
	public const FIELD_NAME = 'storeaccountant_permission_roles';

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private PermissionActionRegistry $actions,
		private RolePermissionRepository $roles
	) {}

	/**
	 * Renders the permission assignment table.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render_fields(): void {
		$role_options = $this->roles->get_role_options();
		$groups       = $this->get_grouped_actions();
		?>
		<tr>
			<th scope="row" colspan="2">
				<h3><?php esc_html_e( 'Permission role assignments', 'storeaccountant' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Assign backend roles to StoreAccountant actions. Administrators are always included and cannot be removed.', 'storeaccountant' ); ?></p>
			</th>
		</tr>
		<tr>
			<td class="storeaccountant-permissions-table-cell" colspan="2">
				<table class="widefat storeaccountant-permissions-table">
					<?php foreach ( $groups as $group => $actions ) : ?>
						<?php $group_id = 'storeaccountant-permission-group-' . sanitize_html_class( sanitize_key( $group ) ); ?>
						<tbody id="<?php echo esc_attr( $group_id ); ?>" class="storeaccountant-permissions-group is-collapsed" data-storeaccountant-permission-group="<?php echo esc_attr( $group_id ); ?>">
							<tr class="storeaccountant-permissions-group-row">
								<th scope="rowgroup" colspan="2">
									<button
										type="button"
										class="storeaccountant-permissions-group-toggle"
										aria-expanded="false"
										aria-controls="<?php echo esc_attr( $group_id ); ?>"
										data-storeaccountant-permission-toggle
									>
										<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
										<span><?php echo esc_html( $group ); ?></span>
										<span class="storeaccountant-permissions-group-count">
											<?php
											printf(
												/* translators: %d: number of permission actions in a group */
												esc_html( _n( '%d action', '%d actions', count( $actions ), 'storeaccountant' ) ),
												count( $actions )
											);
											?>
										</span>
									</button>
								</th>
							</tr>
							<?php foreach ( $actions as $action ) : ?>
								<?php $this->render_action_row( $action, $role_options ); ?>
							<?php endforeach; ?>
						</tbody>
					<?php endforeach; ?>
				</table>
			</td>
		</tr>
		<?php
	}

	/**
	 * Gets submitted roles grouped by action ID.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $request Request data.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function get_roles_from_request( array $request ): array {
		$submitted       = isset( $request[ self::FIELD_NAME ] ) && is_array( $request[ self::FIELD_NAME ] )
			? wp_unslash( $request[ self::FIELD_NAME ] )
			: [];
		$roles_by_action = [];

		foreach ( $this->actions->get_all() as $action ) {
			$action_roles = isset( $submitted[ $action->get_id() ] ) && is_array( $submitted[ $action->get_id() ] )
				? $submitted[ $action->get_id() ]
				: [];

			$roles_by_action[ $action->get_id() ] = array_values(
				array_filter(
					array_map( static fn ( mixed $role ): string => is_scalar( $role ) ? sanitize_key( (string) $role ) : '', $action_roles )
				)
			);
		}

		return $roles_by_action;
	}

	/**
	 * Renders one permission action row.
	 *
	 * @param PermissionActionInterface                       $action       Permission action.
	 * @param array<int, array{value: string, label: string}> $role_options Role options.
	 */
	private function render_action_row( PermissionActionInterface $action, array $role_options ): void {
		$selected_roles = $this->roles->get_roles_for_action( $action, false );
		$field_name     = self::FIELD_NAME . '[' . $action->get_id() . ']';
		?>
		<tr class="storeaccountant-permissions-action-row" id="<?php echo esc_attr( 'storeaccountant-permission-action-' . sanitize_html_class( $action->get_id() ) ); ?>">
			<th scope="row">
				<span class="storeaccountant-permission-action-label"><?php echo esc_html( $action->get_label() ); ?></span>
				<?php if ( '' !== $action->get_description() ) : ?>
					<p class="description"><?php echo esc_html( $action->get_description() ); ?></p>
				<?php endif; ?>
				<code><?php echo esc_html( $action->get_capability() ); ?></code>
			</th>
			<td>
				<label class="storeaccountant-locked-admin-role">
					<input type="checkbox" checked="checked" disabled="disabled" />
					<?php esc_html_e( 'Administrator', 'storeaccountant' ); ?>
				</label>
				<?php if ( PermissionActionIds::ACCESS_ADMIN === $action->get_id() ) : ?>
					<p class="description"><?php esc_html_e( 'This base access permission is granted automatically when a role receives any StoreAccountant action.', 'storeaccountant' ); ?></p>
				<?php endif; ?>
				<div
					class="storeaccountant-permission-role-token-field"
					data-field-name="<?php echo esc_attr( $field_name ); ?>"
					data-label="<?php echo esc_attr( $action->get_label() ); ?>"
					data-roles="<?php echo esc_attr( (string) wp_json_encode( $role_options ) ); ?>"
					data-selected-roles="<?php echo esc_attr( (string) wp_json_encode( array_values( $selected_roles ) ) ); ?>"
				></div>
				<fieldset class="storeaccountant-permission-role-checkboxes">
					<legend class="screen-reader-text">
						<span><?php echo esc_html( $action->get_label() ); ?></span>
					</legend>
					<?php foreach ( $role_options as $role ) : ?>
						<?php $field_id = 'storeaccountant-permission-' . sanitize_html_class( $action->get_id() . '-' . $role['value'] ); ?>
						<label for="<?php echo esc_attr( $field_id ); ?>">
							<input
								type="checkbox"
								id="<?php echo esc_attr( $field_id ); ?>"
								name="<?php echo esc_attr( $field_name ); ?>[]"
								value="<?php echo esc_attr( $role['value'] ); ?>"
								<?php checked( in_array( $role['value'], $selected_roles, true ) ); ?>
							/>
							<?php echo esc_html( $role['label'] ); ?>
						</label>
						<br />
					<?php endforeach; ?>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * Groups actions by group label.
	 *
	 * @return array<string, array<int, PermissionActionInterface>>
	 */
	private function get_grouped_actions(): array {
		$groups = [];

		foreach ( $this->actions->get_all() as $action ) {
			$groups[ $action->get_group() ][] = $action;
		}

		return $groups;
	}
}
