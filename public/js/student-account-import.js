(() => {
    const editors = (form) => Array.from(form.querySelectorAll('[data-import-editor-row]'));
    const writeDraft = (form) => {
        const rows = editors(form).map((row) => Object.fromEntries(
            Array.from(row.querySelectorAll('[data-import-field]'), (field) => [field.dataset.importField, field.value])
        ));
        form.querySelector('[name="draft_rows"]').value = JSON.stringify(rows);
    };
    const markDirty = (form, index) => {
        form.dataset.importDirty = 'true';
        form.querySelector('[data-import-create]').disabled = true;
        form.querySelector('[data-import-pending]').classList.remove('d-none');
        if (index !== undefined) {
            const badge = form.querySelector(`[data-import-row-validation="${index}"]`);
            if (badge) {
                badge.className = 'badge text-bg-warning rounded-1';
                badge.textContent = 'Needs validation';
            }
            const message = form.querySelector(`[data-import-row-message="${index}"]`);
            if (message) message.textContent = 'Select Save & Validate to check these changes.';
        }
    };

    window.initializeStudentAccountImports = () => {
        document.querySelectorAll('[data-student-import-review-form]').forEach((form) => {
            if (form.dataset.importEditorReady === 'true') return;
            form.dataset.importEditorReady = 'true';
            const savedInput = form.querySelector('[name="draft_rows"]').value;
            if (savedInput) {
                markDirty(form);
                try {
                    const rows = JSON.parse(savedInput);
                    const rowEditors = editors(form);
                    if (Array.isArray(rows) && rows.length === rowEditors.length) {
                        rowEditors.forEach((row, index) => {
                            row.querySelectorAll('[data-import-field]').forEach((field) => {
                                const value = rows[index]?.[field.dataset.importField];
                                if (typeof value === 'string' || typeof value === 'number') field.value = String(value);
                            });
                            markDirty(form, row.dataset.importEditorRow);
                        });
                    }
                } catch {
                    markDirty(form);
                }
            }
            writeDraft(form);
            form.querySelector('[data-import-review]').disabled = false;
            form.querySelector('[data-import-create]').disabled = form.dataset.importDirty === 'true' || Number(form.dataset.readyCount) === 0;
        });
        document.querySelectorAll('[data-student-import-auto-open]').forEach((modal) => {
            modal.removeAttribute('data-student-import-auto-open');
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    };

    ['input', 'change'].forEach((name) => document.addEventListener(name, (event) => {
        const field = event.target.closest('[data-import-field]');
        const form = field?.closest('[data-student-import-review-form]');
        if (form) markDirty(form, field.closest('[data-import-editor-row]')?.dataset.importEditorRow);
    }));
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-student-import-review-form]');
        if (!form) return;
        if (form.dataset.importDirty === 'true' && event.submitter?.matches('[data-import-create]')) {
            event.preventDefault();
            form.querySelector('[data-import-review]').focus();
            return;
        }
        if (form.dataset.importSubmitting === 'true') {
            event.preventDefault();
            return;
        }
        writeDraft(form);
        form.dataset.importSubmitting = 'true';
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-student-import-review-form]').forEach((form) => delete form.dataset.importSubmitting);
    });
    window.initializeStudentAccountImports();
})();
