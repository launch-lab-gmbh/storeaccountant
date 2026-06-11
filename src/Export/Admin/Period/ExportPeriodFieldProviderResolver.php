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

namespace StoreAccountant\Export\Admin\Period;

use StoreAccountant\Export\Admin\Period\Contract\ExportPeriodFieldProviderInterface;
use StoreAccountant\Export\Admin\Period\Contract\ExportPeriodViewProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the active export period field provider.
 */
final readonly class ExportPeriodFieldProviderResolver {
	/**
	 * Gets the active period field provider.
	 */
	public function get(): ExportPeriodFieldProviderInterface {
		$provider = apply_filters(
			'storeaccountant_export_period_field_provider',
			null
		);

		if ( $provider instanceof ExportPeriodFieldProviderInterface ) {
			return $provider;
		}

		return new MonthYearExportPeriodFieldProvider();
	}

	/**
	 * Gets the active period view provider.
	 */
	public function get_view_provider(): ExportPeriodViewProviderInterface {
		$provider = apply_filters(
			'storeaccountant_export_period_view_provider',
			null
		);

		if ( $provider instanceof ExportPeriodViewProviderInterface ) {
			return $provider;
		}

		$field_provider = $this->get();

		if ( $field_provider instanceof ExportPeriodViewProviderInterface ) {
			return $field_provider;
		}

		return new MonthYearExportPeriodFieldProvider();
	}
}
