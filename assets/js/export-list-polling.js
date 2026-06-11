(function () {
	const config = window.storeAccountantExportPolling || {};
	const ajaxUrl = config.ajaxUrl || '';
	const nonce = config.nonce || '';
	const intervalMs = Number(config.intervalMs || 3000);
	const backoffMs = Number(config.backoffMs || 8000);

	if (!ajaxUrl || !nonce) {
		return;
	}

	let timer = null;
	let running = false;

	function getPollableActionCells() {
		return Array.from(document.querySelectorAll('[data-storeaccountant-export-actions][data-storeaccountant-pollable="1"]'));
	}

	function getPollableIds() {
		const ids = new Set();

		getPollableActionCells().forEach((cell) => {
			const id = Number(cell.dataset.storeaccountantExportId || 0);

			if (id > 0) {
				ids.add(id);
			}
		});

		return Array.from(ids);
	}

	function setNextPoll(delay) {
		window.clearTimeout(timer);

		const ids = getPollableIds();

		if (ids.length === 0) {
			timer = null;
			return;
		}

		timer = window.setTimeout(poll, delay);
	}

	function updateProgress(exportData) {
		document.querySelectorAll(`[data-storeaccountant-export-progress][data-storeaccountant-export-id="${exportData.id}"]`).forEach((cell) => {
			cell.textContent = exportData.progress_label || '';
		});
	}

	function createStatusBadge(exportData) {
		const badge = document.createElement('span');
		badge.className = `storeaccountant-export-status storeaccountant-export-status--${exportData.status}`;
		badge.textContent = exportData.status_label || exportData.status || '';

		return badge;
	}

	function createDownloadButton(exportData) {
		const link = document.createElement('a');
		link.className = 'button button-small';
		link.href = exportData.download_url;
		link.rel = 'noopener noreferrer';
		link.target = '_blank';
		link.textContent = exportData.download_label || 'Download';

		return link;
	}

	function updateActions(exportData) {
		document.querySelectorAll(`[data-storeaccountant-export-actions][data-storeaccountant-export-id="${exportData.id}"]`).forEach((cell) => {
			cell.replaceChildren();
			cell.dataset.storeaccountantExportStatus = exportData.status || '';
			cell.dataset.storeaccountantPollable = exportData.pollable ? '1' : '0';

			if (exportData.download_url) {
				cell.appendChild(createDownloadButton(exportData));
				return;
			}

			cell.appendChild(createStatusBadge(exportData));
		});
	}

	function applyExport(exportData) {
		if (!exportData || !exportData.id) {
			return;
		}

		updateProgress(exportData);
		updateActions(exportData);
	}

	async function poll() {
		if (running) {
			return;
		}

		const ids = getPollableIds();

		if (ids.length === 0) {
			return;
		}

		running = true;

		const body = new URLSearchParams();
		body.append('action', 'storeaccountant_poll_exports');
		body.append('nonce', nonce);
		ids.forEach((id) => body.append('export_ids[]', String(id)));

		try {
			const response = await window.fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body,
			});
			const payload = await response.json();

			if (!response.ok || !payload.success || !payload.data || !Array.isArray(payload.data.exports)) {
				setNextPoll(backoffMs);
				return;
			}

			payload.data.exports.forEach(applyExport);
			setNextPoll(intervalMs);
		} catch (error) {
			setNextPoll(backoffMs);
		} finally {
			running = false;
		}
	}

	setNextPoll(intervalMs);
})();
