(() => {
    let searchTimer;

    document.addEventListener('input', (event) => {
        const input = event.target.closest('[data-record-search-input]');
        if (!input) return;

        window.clearTimeout(searchTimer);
        const form = input.form;
        const clear = form.querySelector('[data-record-search-clear]');
        if (clear) {
            clear.hidden = input.value.trim() === '';
            clear.disabled = clear.hidden;
        }
        const status = form.querySelector('[data-record-search-status]');
        if (status) status.textContent = 'Searching...';
        searchTimer = window.setTimeout(() => {
            if (form.isConnected) form.requestSubmit();
        }, 350);
    });

    document.addEventListener('submit', (event) => {
        if (event.target.matches('[data-record-search-form]')) {
            window.clearTimeout(searchTimer);
            event.target.setAttribute('aria-busy', 'true');
            const status = event.target.querySelector('[data-record-search-status]');
            if (status) status.textContent = 'Searching...';
        }
    });

    document.addEventListener('click', (event) => {
        const clear = event.target.closest('[data-record-search-clear]');
        if (!clear) return;
        window.clearTimeout(searchTimer);
        const input = clear.form.querySelector('[data-record-search-input]');
        input.value = '';
        clear.hidden = true;
        input.focus();
        clear.form.requestSubmit();
    });

    window.initializeRecordSearch = () => {
        const input = document.querySelector('[data-record-search-input]');
        if (!input) return;
        input.form.removeAttribute('aria-busy');
        const tab = document.querySelector('#userTabs [data-table-tab-button].active, [data-table-tabs-root] [data-table-tab-button].active')?.dataset.tableTabButton;
        if (tab === 'teachers') input.placeholder = 'Search teachers...';
        if (tab === 'students') input.placeholder = 'Search students...';
        const status = input.form.querySelector('[data-record-search-status]');
        const query = input.value.trim();
        document.querySelectorAll('[data-record-search-empty]').forEach((item) => { item.hidden = Boolean(query); });
        const records = Array.from(document.querySelectorAll('.page-container tbody tr, .page-container .assessment-card, .page-container .class-card, .page-container .class-performance-card, .page-container .report-assessment-card, .page-container .assessment-result-card'))
            .filter((item) => {
                if (item.closest('.modal, [data-search-ignore], [data-record-search-empty]')) return false;
                const tabPane = item.closest('.tab-pane');
                if (tabPane && !tabPane.classList.contains('active')) return false;
                if (item.querySelector('td[colspan]') && item.children.length === 1) {
                    item.hidden = Boolean(query);
                    return false;
                }
                return !item.closest('[hidden], .d-none');
            });
        const clear = input.form.querySelector('[data-record-search-clear]');
        if (clear) {
            clear.hidden = query === '';
            clear.disabled = clear.hidden;
        }
        if (status) {
            status.textContent = query === '' ? '' : records.length
                ? 'Showing matching records in this feature.'
                : 'No matching records in this feature. Try another search or clear the search.';
        }
    };
})();
