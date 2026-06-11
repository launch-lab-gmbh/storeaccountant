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

namespace StoreAccountant\Export\Download;

use StoreAccountant\Export\ExportPostType;
use function get_post_meta;
use function home_url;
use function rawurlencode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds public frontend download URLs for export tokens.
 */
final readonly class ExportDownloadUrlFactory {
	/**
	 * Builds a frontend download URL for an export.
	 */
	public function get_url( int $export_id ): string {
		$token = (string) get_post_meta( $export_id, ExportPostType::META_DOWNLOAD_TOKEN, true );

		if ( '' === $token ) {
			return '';
		}

		return home_url( '/storeaccountant/export-download/' . rawurlencode( $token ) . '/' );
	}
}
