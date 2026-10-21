(() => {
    const synchronize = (form) => {
        const counts = { ready: 0, already_enrolled: 0, inactive: 0, not_found: 0, excluded: 0 };
        const submitting = form.dataset.rosterSubmitting === 'true';
        form.querySelectorAll('[data-roster-row]').forEach((row) => {
            const excluded = !row.querySelector('[data-roster-exclusion]').disabled;
            const badge = row.querySelector('[data-roster-status-badge]');
            if (badge.dataset.originalText === undefined) {
                badge.dataset.originalText = badge.textContent;
                badge.dataset.originalClass = badge.className;
            }
            row.classList.toggle('class-import-excluded', excluded);
            if (excluded) counts.excluded++;
            else if (Object.hasOwn(counts, row.dataset.rosterStatus)) counts[row.dataset.rosterStatus]++;
            badge.textContent = excluded ? 'Removed from import' : badge.dataset.originalText;
            badge.className = excluded ? 'badge text-bg-secondary rounded-1' : badge.dataset.originalClass;
            const button = row.querySelector('[data-roster-toggle]');
            button.disabled = submitting;
            button.classList.toggle('btn-outline-danger', !excluded);
            button.classList.toggle('btn-outline-secondary', excluded);
            button.setAttribute('aria-label', `${excluded ? 'Restore to' : 'Remove from'} import: ${row.querySelector('.class-import-identity').textContent.trim()}`);
            row.querySelector('[data-roster-toggle-label]').textContent = excluded ? 'Undo' : 'Remove from Import';
            row.querySelector('[data-roster-toggle-icon]').textContent = excluded ? 'undo' : 'remove_circle_outline';
        });
        form.querySelector('[data-roster-ready-count]').textContent = String(counts.ready);
        form.querySelector('[data-roster-confirm-count]').textContent = String(counts.ready);
        form.querySelector('[data-roster-enrolled-count]').textContent = String(counts.already_enrolled);
        form.querySelector('[data-roster-review-count]').textContent = String(counts.inactive + counts.not_found);
        form.querySelector('[data-roster-excluded-count]').textContent = String(counts.excluded);
        form.querySelector('[data-roster-confirm]').disabled = counts.ready === 0 || submitting;
        return counts.ready;
    };

    window.initializeClassRosterImports = () => {
        document.querySelectorAll('[data-class-roster-import]').forEach(synchronize);
        document.querySelectorAll('[data-class-roster-import-auto-open]').forEach((modal) => {
            modal.removeAttribute('data-class-roster-import-auto-open');
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    };
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-roster-toggle]');
        const form = button?.closest('[data-class-roster-import]');
        if (!form || button.disabled || form.dataset.rosterSubmitting === 'true') return;
        const row = button.closest('[data-roster-row]');
        const exclusion = row.querySelector('[data-roster-exclusion]');
        exclusion.disabled = !exclusion.disabled;
        synchronize(form);
    });
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-class-roster-import]');
        if (!form) return;
        if (form.dataset.rosterSubmitting === 'true' || synchronize(form) === 0) {
            event.preventDefault();
            return;
        }
        form.dataset.rosterSubmitting = 'true';
        synchronize(form);
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-class-roster-import]').forEach((form) => {
            delete form.dataset.rosterSubmitting;
            synchronize(form);
        });
    });
    window.initializeClassRosterImports();
})();
