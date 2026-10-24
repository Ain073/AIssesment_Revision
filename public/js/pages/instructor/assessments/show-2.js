(() => {
    document.addEventListener('click', (event) => {
        // Add row in identification edit modal
        const addIdentBtn = event.target.closest('[data-add-edit-ident-row]');
        if (addIdentBtn) {
            const itemId = addIdentBtn.dataset.addEditIdentRow;
            const container = document.getElementById(`editIdentRows${itemId}`);
            if (! container) return;

            const row = document.createElement('div');
            row.className = 'ident-answer-row d-flex gap-2 mb-2';
            row.dataset.identRow = 'true';
            row.innerHTML = `
                <span class="input-group-text bg-light border rounded px-2 small fw-semibold" style="min-width:5.5rem;justify-content:center;">Alternative</span>
                <input class="form-control" name="accepted_answers[]" type="text" placeholder="e.g. Alternative answer / acronym" required>
                <button class="btn btn-sm btn-outline-danger" type="button" data-edit-remove-ident-row title="Remove">&times;</button>
            `;
            container.appendChild(row);
            return;
        }

        // Remove row in identification edit modal
        const removeIdentBtn = event.target.closest('[data-edit-remove-ident-row]');
        if (removeIdentBtn) {
            const row = removeIdentBtn.closest('[data-ident-row]');
            row?.remove();
            return;
        }
        // Add row in edit modal
        const addBtn = event.target.closest('[data-add-edit-enum-row]');

        if (addBtn) {
            const itemId = addBtn.dataset.addEditEnumRow;
            const container = document.getElementById(`editEnumRows${itemId}`);

            if (! container) { return; }

            const rowCount = container.querySelectorAll('[data-enum-row]').length + 1;
            const row = document.createElement('div');
            row.className = 'enum-answer-row d-flex gap-2 mb-2';
            row.dataset.enumRow = 'true';
            row.innerHTML = `
                <span class="input-group-text bg-light border rounded px-2" style="min-width:2.2rem;justify-content:center;">${rowCount}</span>
                <input class="form-control" name="enum_answers[]" type="text" placeholder="Answer ${rowCount}">
                <button class="btn btn-sm btn-outline-danger" type="button" data-edit-remove-enum-row title="Remove">&times;</button>
            `;
            container.appendChild(row);
            const pointsInput = document.getElementById(`edit_points_${itemId}`);
            if (pointsInput) {
                pointsInput.value = rowCount;
            }
            return;
        }

        // Remove row in edit modal
        const removeBtn = event.target.closest('[data-edit-remove-enum-row]');

        if (removeBtn) {
            const row = removeBtn.closest('[data-enum-row]');
            const container = row?.parentElement;
            row?.remove();

            if (container) {
                const remaining = container.querySelectorAll('[data-enum-row]');
                remaining.forEach((r, i) => {
                    const label = r.querySelector('span');
                    const input = r.querySelector('input');
                    if (label) { label.textContent = String(i + 1); }
                    if (input) { input.placeholder = `Answer ${i + 1}`; }
                });
                const modal = container.closest('.modal');
                const pointsInput = modal?.querySelector('input[name="points"]');
                if (pointsInput) {
                    pointsInput.value = Math.max(1, remaining.length);
                }
            }
        }
    });
})();
