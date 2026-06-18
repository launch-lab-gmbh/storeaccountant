(function () {
	const monthYearPeriodFields = document.querySelectorAll('[data-storeaccountant-period-month]');

	monthYearPeriodFields.forEach((monthField) => {
		if (monthField.dataset.storeaccountantPeriodProvider !== 'month-year') {
			return;
		}

		const monthRow = monthField.closest('tr');
		const yearRow = monthRow?.nextElementSibling?.matches('[data-storeaccountant-period-year-row]')
			? monthRow.nextElementSibling
			: null;
		const yearField = yearRow?.querySelector('select, input');

		if (!yearRow) {
			return;
		}

		function toggleYearField() {
			const isRelativePeriod = monthField.value === 'all_time' || monthField.value === 'current_month' || monthField.value === 'last_month';

			yearRow.classList.toggle('storeaccountant-is-hidden', isRelativePeriod);
			if (yearField) {
				yearField.disabled = isRelativePeriod;
			}
		}

		monthField.addEventListener('change', toggleYearField);
		toggleYearField();
	});

	document.querySelectorAll('#storeaccountant-export-create-selection').forEach((select) => {
		const form = select.closest('form');
		const titleField = form?.querySelector('[data-storeaccountant-configuration-export-title]');

		if (!titleField) {
			return;
		}

		function toggleConfigurationExportTitle() {
			const isConfigurationExport = select.value.startsWith('configuration:');

			titleField.classList.toggle('storeaccountant-is-hidden', !isConfigurationExport);
			titleField.disabled = !isConfigurationExport;
			titleField.required = isConfigurationExport;
		}

		select.addEventListener('change', toggleConfigurationExportTitle);
		toggleConfigurationExportTitle();
	});

	document.querySelectorAll('#storeaccountant-customer-country-field').forEach((countryField) => {
		const form = countryField.closest('form');
		const countrySelect = form?.querySelector('#storeaccountant-customer-countries');

		if (!countrySelect) {
			return;
		}

		function toggleCustomerCountryOptions() {
			const activeField = countryField.value;

			Array.from(countrySelect.options).forEach((option) => {
				const fields = option.dataset.storeaccountantCustomerCountryFields;

				if (!fields) {
					return;
				}

				const active = fields.split(' ').includes(activeField);

				option.hidden = !active;
				option.disabled = !active;

				if (!active) {
					option.selected = false;
				}
			});

			if (Array.from(countrySelect.selectedOptions).length === 0 && countrySelect.options.length > 0) {
				countrySelect.options[0].selected = true;
			}
		}

		countryField.addEventListener('change', toggleCustomerCountryOptions);
		toggleCustomerCountryOptions();
	});

	document.querySelectorAll('.storeaccountant-invoice-provider-checkbox').forEach((checkbox) => {
		checkbox.addEventListener('change', () => {
			if (!checkbox.checked) {
				return;
			}

			document.querySelectorAll('.storeaccountant-invoice-provider-checkbox').forEach((other) => {
				if (other !== checkbox) {
					other.checked = false;
				}
			});
		});
	});

	document.querySelectorAll('.storeaccountant-customer-country-token-field').forEach((container) => {
		if (!window.wp?.element || !window.wp?.components?.FormTokenField) {
			return;
		}

		let options = [];
		let selectedCountries = [];

		try {
			options = JSON.parse(container.dataset.countries || '[]');
			selectedCountries = JSON.parse(container.dataset.selectedCountries || '[]');
		} catch (error) {
			return;
		}

		if (!Array.isArray(options) || !Array.isArray(selectedCountries)) {
			return;
		}

		const fieldName = container.dataset.fieldName;
		const allValue = container.dataset.allValue || 'all';
		const unassignedValue = container.dataset.unassignedValue || 'unassigned';
		const form = container.closest('form');
		const countryField = form?.querySelector('#storeaccountant-customer-country-field');
		const select = form?.querySelector('#storeaccountant-customer-countries');
		const root = window.wp.element.createRoot(container);

		function getActiveOptions() {
			const activeCountryField = countryField?.value || 'billing_country';

			return options.filter((option) => option.value === allValue || option.value === unassignedValue || option.fields?.includes(activeCountryField));
		}

		function getOptionMaps(activeOptions) {
			return {
				labelsByValue: new Map(activeOptions.map((option) => [option.value, option.label])),
				valuesByLabel: new Map(activeOptions.map((option) => [option.label, option.value])),
			};
		}

		function normalizeToken(token, activeOptions) {
			const value = typeof token === 'object' && token !== null ? token.value : token;

			if (typeof value !== 'string') {
				return '';
			}

			const { labelsByValue, valuesByLabel } = getOptionMaps(activeOptions);

			return valuesByLabel.get(value) || (labelsByValue.has(value) ? value : '');
		}

		function normalizeSelection(nextTokens) {
			const activeOptions = getActiveOptions();
			const activeValues = new Set(activeOptions.map((option) => option.value));
			const nextCountries = Array.from(
				new Set(nextTokens.map((token) => normalizeToken(token, activeOptions)).filter((country) => activeValues.has(country))),
			);

			if (nextCountries.length === 0) {
				return [allValue];
			}

			const includesAll = nextCountries.includes(allValue);
			const includesUnassigned = nextCountries.includes(unassignedValue);
			const concreteCountries = nextCountries.filter((country) => country !== allValue && country !== unassignedValue);
			let normalizedCountries = [];

			if (includesAll && concreteCountries.length > 0) {
				if (selectedCountries.includes(allValue)) {
					normalizedCountries = concreteCountries;
				} else {
					normalizedCountries = [allValue];
				}
			} else if (includesAll) {
				normalizedCountries = [allValue];
			} else {
				normalizedCountries = concreteCountries;
			}

			if (includesUnassigned) {
				normalizedCountries.push(unassignedValue);
			}

			return normalizedCountries.length > 0 ? normalizedCountries : [allValue];
		}

		function syncFallbackSelect() {
			if (!select) {
				return;
			}

			Array.from(select.options).forEach((option) => {
				option.selected = selectedCountries.includes(option.value);
			});

			select.disabled = true;
			select.classList.add('storeaccountant-is-hidden');
		}

		function setSelectedCountries(nextTokens) {
			selectedCountries = normalizeSelection(nextTokens);

			render();
		}

		function render() {
			const activeOptions = getActiveOptions();
			const { labelsByValue, valuesByLabel } = getOptionMaps(activeOptions);
			const suggestions = activeOptions.map((option) => option.label);

			selectedCountries = normalizeSelection(selectedCountries);
			syncFallbackSelect();

			root.render(
				window.wp.element.createElement(
					window.wp.element.Fragment,
					null,
					selectedCountries.map((country) => window.wp.element.createElement('input', {
						key: country,
						name: `${fieldName}[]`,
						type: 'hidden',
						value: country,
					})),
					window.wp.element.createElement(window.wp.components.FormTokenField, {
						__experimentalExpandOnFocus: true,
						__experimentalValidateInput: (value) => valuesByLabel.has(value) || labelsByValue.has(value),
						__next40pxDefaultSize: true,
						help: '',
						hideLabelFromVision: true,
						label: container.dataset.label || '',
						maxSuggestions: activeOptions.length,
						onChange: setSelectedCountries,
						suggestions,
						value: selectedCountries.map((country) => labelsByValue.get(country) || country),
					}),
				),
			);
		}

		countryField?.addEventListener('change', render);
		container.classList.add('storeaccountant-customer-country-token-field-enhanced');
		render();
	});

	document.querySelectorAll('.storeaccountant-field-mapping-table tbody').forEach((body) => {
		let draggedRow = null;
		let activeHandle = null;

		function stopDragging() {
			if (draggedRow) {
				draggedRow.classList.remove('storeaccountant-field-mapping-row-dragging');
			}

			document.removeEventListener('pointermove', moveDraggedRow);
			document.removeEventListener('pointerup', stopDragging);
			document.removeEventListener('pointercancel', stopDragging);

			draggedRow = null;
			activeHandle = null;
		}

		function moveDraggedRow(event) {
			if (!draggedRow || !activeHandle) {
				return;
			}

			const rows = Array.from(body.querySelectorAll('tr')).filter((row) => row !== draggedRow);
			const nextRow = rows.find((row) => {
				const rowBounds = row.getBoundingClientRect();

				return event.clientY < rowBounds.top + rowBounds.height / 2;
			});

			body.insertBefore(draggedRow, nextRow || null);
			event.preventDefault();
		}

		body.querySelectorAll('.storeaccountant-field-mapping-handle').forEach((handle) => {
			handle.addEventListener('pointerdown', (event) => {
				if (event.button !== 0) {
					return;
				}

				const row = handle.closest('tr');

				if (!row || !body.contains(row)) {
					return;
				}

				draggedRow = row;
				activeHandle = handle;
				draggedRow.classList.add('storeaccountant-field-mapping-row-dragging');

				document.addEventListener('pointermove', moveDraggedRow);
				document.addEventListener('pointerup', stopDragging);
				document.addEventListener('pointercancel', stopDragging);
				event.preventDefault();
			});
		});
	});

	document.querySelectorAll('.storeaccountant-order-status-token-field').forEach((container) => {
		if (!window.wp?.element || !window.wp?.components?.FormTokenField) {
			return;
		}

		let options = [];
		let selectedStatuses = [];

		try {
			options = JSON.parse(container.dataset.statuses || '[]');
			selectedStatuses = JSON.parse(container.dataset.selectedStatuses || '[]');
		} catch (error) {
			return;
		}

		if (!Array.isArray(options) || !Array.isArray(selectedStatuses)) {
			return;
		}

		const fieldName = container.dataset.fieldName;
		const checkboxContainer = container.parentElement?.querySelector('.storeaccountant-order-status-checkboxes');
		const labelsByValue = new Map(options.map((option) => [option.value, option.label]));
		const valuesByLabel = new Map(options.map((option) => [option.label, option.value]));
		const suggestions = options.map((option) => option.label);
		const root = window.wp.element.createRoot(container);

		function normalizeToken(token) {
			const value = typeof token === 'object' && token !== null ? token.value : token;

			if (typeof value !== 'string') {
				return '';
			}

			return valuesByLabel.get(value) || (labelsByValue.has(value) ? value : '');
		}

		function setSelectedStatuses(nextTokens) {
			selectedStatuses = Array.from(
				new Set(nextTokens.map(normalizeToken).filter((status) => status !== '')),
			);

			render();
		}

		function render() {
			root.render(
				window.wp.element.createElement(
					window.wp.element.Fragment,
					null,
					selectedStatuses.map((status) => window.wp.element.createElement('input', {
						key: status,
						name: `${fieldName}[]`,
						type: 'hidden',
						value: status,
					})),
					window.wp.element.createElement(window.wp.components.FormTokenField, {
						__experimentalExpandOnFocus: true,
						__experimentalValidateInput: (value) => valuesByLabel.has(value) || labelsByValue.has(value),
						__next40pxDefaultSize: true,
						help: '',
						hideLabelFromVision: true,
						label: container.dataset.label || '',
						maxSuggestions: options.length,
						onChange: setSelectedStatuses,
						suggestions,
						value: selectedStatuses.map((status) => labelsByValue.get(status) || status),
					}),
				),
			);
		}

		if (checkboxContainer) {
			checkboxContainer.classList.add('storeaccountant-is-hidden');
			checkboxContainer.querySelectorAll('input[type="checkbox"]').forEach((input) => {
				input.disabled = true;
			});
		}

		container.classList.add('storeaccountant-order-status-token-field-enhanced');
		render();
	});

	document.querySelectorAll('.storeaccountant-permission-role-token-field').forEach((container) => {
		if (!window.wp?.element || !window.wp?.components?.FormTokenField) {
			return;
		}

		let options = [];
		let selectedRoles = [];

		try {
			options = JSON.parse(container.dataset.roles || '[]');
			selectedRoles = JSON.parse(container.dataset.selectedRoles || '[]');
		} catch (error) {
			return;
		}

		if (!Array.isArray(options) || !Array.isArray(selectedRoles)) {
			return;
		}

		const fieldName = container.dataset.fieldName;
		const checkboxContainer = container.parentElement?.querySelector('.storeaccountant-permission-role-checkboxes');
		const labelsByValue = new Map(options.map((option) => [option.value, option.label]));
		const valuesByLabel = new Map(options.map((option) => [option.label, option.value]));
		const suggestions = options.map((option) => option.label);
		const root = window.wp.element.createRoot(container);

		function normalizeToken(token) {
			const value = typeof token === 'object' && token !== null ? token.value : token;

			if (typeof value !== 'string') {
				return '';
			}

			return valuesByLabel.get(value) || (labelsByValue.has(value) ? value : '');
		}

		function setSelectedRoles(nextTokens) {
			selectedRoles = Array.from(
				new Set(nextTokens.map(normalizeToken).filter((role) => role !== '')),
			);

			render();
		}

		function render() {
			root.render(
				window.wp.element.createElement(
					window.wp.element.Fragment,
					null,
					selectedRoles.map((role) => window.wp.element.createElement('input', {
						key: role,
						name: `${fieldName}[]`,
						type: 'hidden',
						value: role,
					})),
					window.wp.element.createElement(window.wp.components.FormTokenField, {
						__experimentalExpandOnFocus: true,
						__experimentalValidateInput: (value) => valuesByLabel.has(value) || labelsByValue.has(value),
						__next40pxDefaultSize: true,
						help: '',
						hideLabelFromVision: true,
						label: container.dataset.label || '',
						maxSuggestions: options.length,
						onChange: setSelectedRoles,
						suggestions,
						value: selectedRoles.map((role) => labelsByValue.get(role) || role),
					}),
				),
			);
		}

		if (checkboxContainer) {
			checkboxContainer.classList.add('storeaccountant-is-hidden');
			checkboxContainer.querySelectorAll('input[type="checkbox"]').forEach((input) => {
				input.disabled = true;
			});
		}

		container.classList.add('storeaccountant-permission-role-token-field-enhanced');
		render();
	});

	document.querySelectorAll('[data-storeaccountant-permission-toggle]').forEach((button) => {
		const group = button.closest('[data-storeaccountant-permission-group]');

		if (!group) {
			return;
		}

		function toggleGroup() {
			const expanded = group.classList.toggle('is-collapsed') === false;

			button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		}

		button.addEventListener('click', toggleGroup);
	});
}());
