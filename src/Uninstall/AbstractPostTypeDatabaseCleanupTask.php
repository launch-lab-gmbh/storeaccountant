<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Uninstall;

use StoreAccountant\Uninstall\Contract\UninstallCleanupTaskInterface;
use function array_map;
use function get_posts;
use function wp_delete_post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes custom post type records during plugin uninstall.
 */
abstract readonly class AbstractPostTypeDatabaseCleanupTask implements UninstallCleanupTaskInterface {
	/**
	 * Post statuses that can contain StoreAccountant database artifacts.
	 *
	 * @var array<int, string>
	 */
	private const POST_STATUSES = [
		'publish',
		'future',
		'draft',
		'pending',
		'private',
		'trash',
		'auto-draft',
		'inherit',
	];

	/**
	 * {@inheritDoc}
	 */
	final public function cleanup(): void {
		foreach ( $this->get_post_ids() as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Gets the custom post type to delete.
	 */
	abstract protected function get_post_type(): string;

	/**
	 * Gets matching post IDs.
	 *
	 * @return array<int, int>
	 */
	private function get_post_ids(): array {
		return array_map(
			'intval',
			get_posts(
				[
					'fields'           => 'ids',
					'post_type'        => $this->get_post_type(),
					'post_status'      => self::POST_STATUSES,
					'posts_per_page'   => -1,
					'no_found_rows'    => true,
					'suppress_filters' => true,
				]
			)
		);
	}
}
