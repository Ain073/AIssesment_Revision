document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('activitySearch');
    const tableBody = document.querySelector('.activity-log-table tbody');
    const countBadge = document.querySelector('.directory-header .badge');
    if (!searchInput || !tableBody) return;

    const dataRows = Array.from(tableBody.querySelectorAll('tr:not(.mobile-empty-cell)'));
    if (dataRows.length === 0) return;

    const defaultBadgeText = countBadge ? countBadge.textContent.trim() : '';
    let noMatchRow = null;

    function filterTable() {
        const query = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        dataRows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const matches = query === '' || text.includes(query);
            row.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        if (visibleCount === 0 && query !== '') {
            if (!noMatchRow) {
                noMatchRow = document.createElement('tr');
                noMatchRow.className = 'no-live-match-row';
                noMatchRow.innerHTML = '<td colspan="5" class="text-center py-4 text-secondary small"><span class="material-symbols-outlined fs-3 d-block mb-1 text-muted">search_off</span>No matching entries on this page. Press <strong>Enter</strong> or click <strong>Search</strong> to query the full database.</td>';
                tableBody.appendChild(noMatchRow);
            }
            noMatchRow.style.display = '';
        } else if (noMatchRow) {
            noMatchRow.style.display = 'none';
        }

        if (countBadge) {
            if (query !== '') {
                countBadge.textContent = `${visibleCount} of ${dataRows.length} displayed`;
            } else {
                countBadge.textContent = defaultBadgeText;
            }
        }
    }

    searchInput.addEventListener('input', filterTable);
    searchInput.addEventListener('search', filterTable);
});
