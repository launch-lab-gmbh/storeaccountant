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

namespace StoreAccountant\Export\Contract;

use WP_Error;
use StoreAccountant\Contract\RegistryItemInterface;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\ExportArtifact;
use StoreAccountant\Export\ExportPayload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines a renderer that creates a storage-ready export artifact.
 */
interface ExportRendererInterface extends RegistryItemInterface {
	/**
	 * Gets the file extension produced by this renderer.
	 */
	public function get_file_extension(): string;

	/**
	 * Gets the MIME type produced by this renderer.
	 */
	public function get_mime_type(): string;

	/**
	 * Renders a dataset and returns a storage-ready artifact.
	 *
	 * @param ExportDataset $dataset Export dataset.
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return ExportArtifact|WP_Error
	 */
	public function render( ExportDataset $dataset, ExportPayload $payload ): ExportArtifact|WP_Error;
}
