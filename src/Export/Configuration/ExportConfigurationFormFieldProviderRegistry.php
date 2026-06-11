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

namespace StoreAccountant\Export\Configuration;

use StoreAccountant\Registry;
use StoreAccountant\Export\Contract\ExportConfigurationFormFieldProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides additional export configuration form field providers.
 */
final readonly class ExportConfigurationFormFieldProviderRegistry extends Registry {
	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_export_configuration_form_field_provider';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return ExportConfigurationFormFieldProviderInterface::class;
	}
}
