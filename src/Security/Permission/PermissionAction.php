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

namespace StoreAccountant\Security\Permission;

use StoreAccountant\Security\Permission\Contract\PermissionActionInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents a permission-controlled admin action.
 */
final readonly class PermissionAction implements PermissionActionInterface {
	public function __construct(
		private string $id,
		private string $label,
		private string $group,
		private string $capability,
		private string $description = ''
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_group(): string {
		return $this->group;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_capability(): string {
		return $this->capability;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return $this->description;
	}
}
