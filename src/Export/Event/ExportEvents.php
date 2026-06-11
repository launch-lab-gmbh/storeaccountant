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

namespace StoreAccountant\Export\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines public export event hook names.
 */
enum ExportEvents: string {
	case LOG_ENTRY            = 'storeaccountant_export_log_entry';
	case QUEUED               = 'storeaccountant_export_queued';
	case STARTED              = 'storeaccountant_export_started';
	case BATCHES_CALCULATED   = 'storeaccountant_export_batches_calculated';
	case BATCH_PROCESSED      = 'storeaccountant_export_batch_processed';
	case BATCH_JOBS_QUEUED    = 'storeaccountant_export_batch_jobs_queued';
	case FINALIZATION_QUEUED  = 'storeaccountant_export_finalization_queued';
	case FINALIZATION_STARTED = 'storeaccountant_export_finalization_started';
	case DATASET_LOADED       = 'storeaccountant_export_dataset_loaded';
	case ARTIFACT_RENDERED    = 'storeaccountant_export_artifact_rendered';
	case ARTIFACT_PERSISTED   = 'storeaccountant_export_artifact_persisted';
	case COMPLETED            = 'storeaccountant_export_completed';
	case FAILED               = 'storeaccountant_export_failed';
}
