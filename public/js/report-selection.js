(() => {
    if (window.initializeReportsPage) return;

    const syncReportSelection = (type) => {
        const checkboxes = Array.from(document.querySelectorAll(`[data-report-checkbox="${type}"]`));
        const checked = checkboxes.filter((checkbox) => checkbox.checked);
        const countTarget = document.querySelector(`[data-report-selected-count="${type}"]`);
        const submitButton = document.querySelector(`[data-report-submit="${type}"]`);
        const selectAll = document.querySelector(`[data-report-select-all="${type}"]`);

        if (countTarget) {
            countTarget.textContent = `${checked.length} selected`;
        }

        if (submitButton) {
            submitButton.disabled = checked.length === 0;
        }

        if (selectAll) {
            selectAll.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
        }
    };

    window.initializeReportsPage = () => {
        ['formative', 'summative'].forEach(syncReportSelection);
    };

    document.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-report-tab]');

        if (! tab) {
            return;
        }

        const type = tab.dataset.reportTab;
        const url = new URL(window.location.href);
        url.searchParams.set('type', type);
        window.history.replaceState({ ajaxPage: true }, '', url.href);

        document.querySelectorAll('[data-report-tab]').forEach((button) => {
            const isActive = button.dataset.reportTab === type;
            button.classList.toggle('active', isActive);
            button.classList.toggle('btn-psu', isActive);
            button.classList.toggle('btn-outline-primary', ! isActive);
        });

        document.querySelectorAll('[data-report-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.reportPanel !== type;
        });

        document.querySelectorAll('[data-report-type-input]').forEach((input) => {
            input.value = type;
        });

        syncReportSelection(type);
        window.initializeRecordSearch?.();
    });

    document.addEventListener('change', (event) => {
        const selectAll = event.target.closest('[data-report-select-all]');
        const checkbox = event.target.closest('[data-report-checkbox]');

        if (selectAll) {
            const type = selectAll.dataset.reportSelectAll;

            document.querySelectorAll(`[data-report-checkbox="${type}"]`).forEach((target) => {
                target.checked = selectAll.checked;
            });

            syncReportSelection(type);
            return;
        }

        if (checkbox) {
            syncReportSelection(checkbox.dataset.reportCheckbox);
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-report-select-form]');

        if (! form) {
            return;
        }

        const type = form.dataset.reportSelectForm;
        const checked = form.querySelectorAll(`[data-report-checkbox="${type}"]:checked`);

        if (checked.length === 0) {
            event.preventDefault();
            syncReportSelection(type);
        }
    });
})();
