(() => {
    const syncAssessmentFilter = (root) => {
        if (! root) {
            return;
        }

        const selectedTab = root.dataset.tableTabsSelected || root.dataset.tableTabsDefault || 'draft';
        const label = root.querySelector('[data-assessment-filter-label]');
        const draftSelect = root.querySelector('[data-assessment-filter-select="draft"]');
        const publishedSelect = root.querySelector('[data-assessment-filter-select="published"]');
        const activeSelect = selectedTab === 'published' ? publishedSelect : draftSelect;
        const inactiveSelect = selectedTab === 'published' ? draftSelect : publishedSelect;
        const activePanel = root.querySelector(`[data-table-tab-panel="${selectedTab}"]`);
        const filterKey = selectedTab === 'published' ? 'classId' : 'subjectId';
        const selectedValue = activeSelect?.value || '';
        let visibleCards = 0;

        if (label) {
            label.textContent = selectedTab === 'published' ? 'Class' : 'Subject';
            label.setAttribute('for', activeSelect?.id || '');
        }

        activeSelect?.classList.remove('d-none');
        inactiveSelect?.classList.add('d-none');

        activePanel?.querySelectorAll('[data-assessment-filter-card]').forEach((card) => {
            const isVisible = selectedValue === '' || card.dataset[filterKey] === selectedValue;
            card.hidden = ! isVisible;

            if (isVisible) {
                visibleCards += 1;
            }
        });

        root.querySelectorAll('[data-assessment-filter-empty]').forEach((emptyMessage) => {
            const isCurrentTab = emptyMessage.dataset.assessmentFilterEmpty === selectedTab;

            emptyMessage.classList.toggle('d-none', ! isCurrentTab || selectedValue === '' || visibleCards > 0);
        });
    };

    window.initializeAssessmentFilters = (root = document) => {
        const filterRoots = root.matches?.('[data-assessment-filter-root]')
            ? [root]
            : Array.from(root.querySelectorAll('[data-assessment-filter-root]'));

        filterRoots.forEach(syncAssessmentFilter);
    };

    document.addEventListener('change', (event) => {
        const select = event.target.closest('[data-assessment-filter-select]');

        if (! select) {
            return;
        }

        syncAssessmentFilter(select.closest('[data-assessment-filter-root]'));
        const url = new URL(window.location.href);
        const key = select.dataset.assessmentFilterSelect === 'published' ? 'class' : 'subject';
        if (select.value) url.searchParams.set(key, select.value);
        else url.searchParams.delete(key);
        window.history.replaceState({ ajaxPage: true }, '', url.href);
        window.initializeRecordSearch?.();
    });
})();
