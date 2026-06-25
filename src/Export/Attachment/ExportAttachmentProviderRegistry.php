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

namespace StoreAccountant\Export\Attachment;

use StoreAccountant\Export\Contract\ExportAttachmentProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides registered export attachment providers.
 */
final readonly class ExportAttachmentProviderRegistry extends Registry {
	/**
	 * Gets providers that support the export type.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportContext $context Runtime export context.
	 *
	 * @return array<string, ExportAttachmentProviderInterface>
	 */
	public function get_providers( ExportContext $context ): array {
		$providers = [];

		foreach ( $this->get_all() as $provider ) {
			if ( $provider->supports( $context ) ) {
				$providers[ $provider->get_id() ] = $provider;
			}
		}

		return $providers;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_export_attachment_provider';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return ExportAttachmentProviderInterface::class;
	}
}
