(() => {
    const syncTableTabs = (root, selectedTab = null, updateUrl = false) => {
        const buttons = Array.from(root.querySelectorAll('[data-table-tab-button]'));
        const validTabs = buttons.map((button) => button.dataset.tableTabButton).filter(Boolean);

        if (validTabs.length === 0) {
            return;
        }

        const url = new URL(window.location.href);
        const param = root.dataset.tableTabsParam || 'tab';
        const fallbackTab = root.dataset.tableTabsDefault || validTabs[0];
        const storedTab = root.dataset.tableTabsSelected;
        const tab = validTabs.includes(selectedTab)
            ? selectedTab
            : (validTabs.includes(storedTab)
                ? storedTab
                : (validTabs.includes(url.searchParams.get(param)) ? url.searchParams.get(param) : fallbackTab));

        root.dataset.tableTabsSelected = tab;
        root.dataset.tableTabsDefault = tab;

        buttons.forEach((button) => {
            const isActive = button.dataset.tableTabButton === tab;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        root.querySelectorAll('[data-table-tab-panel]').forEach((panel) => {
            const isActive = panel.dataset.tableTabPanel === tab;
            panel.hidden = ! isActive;
            panel.classList.toggle('d-none', ! isActive);
        });

        if (updateUrl) {
            url.searchParams.set(param, tab);
            window.history.pushState({ ajaxPage: true }, '', url.href);
        }

        window.initializeRecordSearch?.();
        window.initializeAssessmentFilters?.(root);
    };

    window.initializeTableTabs = (root = document) => {
        const tabRoots = root.matches?.('[data-table-tabs-root]')
            ? [root]
            : Array.from(root.querySelectorAll('[data-table-tabs-root]'));

        tabRoots.forEach((tabRoot) => syncTableTabs(tabRoot));
    };

    document.addEventListener('click', (event) => {
        const tableTabButton = event.target.closest('[data-table-tab-button]');

        if (! tableTabButton) {
            return;
        }

        const root = tableTabButton.closest('[data-table-tabs-root]');

        if (! root) {
            return;
        }

        event.preventDefault();
        syncTableTabs(root, tableTabButton.dataset.tableTabButton, true);
    });
})();
