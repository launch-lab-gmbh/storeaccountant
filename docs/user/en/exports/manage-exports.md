---
title: Manage and Download Exports
description: Track export status, open export details, download completed files, and analyze errors.
---

# Manage and Download Exports

The export list is located under `Accounting > Exports`. It shows all saved export runs.

Each export remains available as its own record. This lets you later verify when an export was created, who started it,
which configuration was used, and where the file was stored.

## Columns in the Export List

The export list shows, among other columns:

- `Title`: name of the export run.
- `Progress`: current progress during background processing.
- `Exported At`: export timestamp.
- `Export Type`: orders, customers, or products.
- `Export Format`: CSV or JSON.
- `Triggered By`: user who started the export.
- `Configuration`: quick export or name of the saved configuration.
- `Status / Download`: current status and download action when available.

[Screenshot: Export list with status and download]

## Export Status

StoreAccountant uses these status values:

- `Queued`: the export has been created and is waiting for processing.
- `Processing`: StoreAccountant is processing the data in the background.
- `Completed`: the file has been created and can be downloaded.
- `Failed`: processing could not be completed.

The list refreshes automatically for queued and processing exports.

## Open Export Details

Click an export title in `Accounting > Exports` to open the detail view.

The `Export Details` tab shows, for example:

- export ID, title, and status
- current processing step
- batches, records, and progress
- start time, finish time, and runtime
- export type, export format, and storage location
- storage reference and file size
- triggering user and related configuration
- download link and download password, if you have permission to view it

The `Raw Data` tab shows technical export configuration data. It is useful when support or development needs to verify
which settings were stored for an export.

The `Log` tab appears only for users with the required permission. It shows technical processing entries for the export.

## Download a File

A completed export can be downloaded from the list or from the detail view.

1. Open `Accounting > Exports`.
2. Wait until the status is `Completed`.
3. Click the download action.
4. Enter the matching download password on the download page.
5. Download the export file.

With local storage, StoreAccountant stores generated files in a protected location below
`wp-content/uploads/storeaccountant`. Downloads are served through a protected link, not through a freely guessable file
path.

For details, see [Download Password Protection](download-password-protection.md).

## Failed Exports

If an export fails:

1. Open the export through `Accounting > Exports`.
2. Check the error message in the `Export Details` tab.
3. Check that storage location, export format, password protection, and background processing are configured correctly.
4. Open the `Log` tab if needed.
5. Enable [Diagnostic Packages](../Diagnostic.md) for support cases.

If the cause was temporary, the export may show an action to retry it.
