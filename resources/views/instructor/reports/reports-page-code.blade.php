<script>
    window.initializeReportsPage = () => {
        const tabs = Array.from(document.querySelectorAll('[data-report-tab]'));
        const panels = Array.from(document.querySelectorAll('[data-report-panel]'));

        if (tabs.length === 0 && panels.length === 0) {
            return;
        }

        const setActivePanel = (type) => {
            tabs.forEach((tab) => {
                const isActive = tab.dataset.reportTab === type;
                tab.classList.toggle('btn-psu', isActive);
                tab.classList.toggle('btn-outline-primary', ! isActive);
            });

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.reportPanel !== type;
            });
        };

        const syncSelection = (type) => {
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

        tabs.forEach((tab) => {
            if (tab.dataset.reportBound === 'true') {
                return;
            }

            tab.dataset.reportBound = 'true';
            tab.addEventListener('click', () => setActivePanel(tab.dataset.reportTab));
        });

        document.querySelectorAll('[data-report-select-all]').forEach((selectAll) => {
            if (selectAll.dataset.reportBound === 'true') {
                return;
            }

            selectAll.dataset.reportBound = 'true';
            selectAll.addEventListener('change', () => {
                const type = selectAll.dataset.reportSelectAll;
                document.querySelectorAll(`[data-report-checkbox="${type}"]`).forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
                syncSelection(type);
            });
        });

        document.querySelectorAll('[data-report-checkbox]').forEach((checkbox) => {
            if (checkbox.dataset.reportBound === 'true') {
                return;
            }

            checkbox.dataset.reportBound = 'true';
            checkbox.addEventListener('change', () => syncSelection(checkbox.dataset.reportCheckbox));
            checkbox.addEventListener('click', () => syncSelection(checkbox.dataset.reportCheckbox));
        });

        document.querySelectorAll('[data-report-select-form]').forEach((form) => {
            if (form.dataset.reportSubmitBound === 'true') {
                return;
            }

            form.dataset.reportSubmitBound = 'true';
            form.addEventListener('submit', (event) => {
                const type = form.dataset.reportSelectForm;
                const checked = form.querySelectorAll(`[data-report-checkbox="${type}"]:checked`);

                if (checked.length === 0) {
                    event.preventDefault();
                    syncSelection(type);
                }
            });
        });

        ['formative', 'summative'].forEach(syncSelection);
    };

    window.initializeReportsPage();
</script>
