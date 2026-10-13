(() => {
    const settings = {
        dean: { picker: 'grantAdminDeanModal', confirm: 'deanConfirmModal', prefix: 'deanConfirm', unit: 'deanConfirmCollege', empty: 'noDeanFacultyFound' },
        chair: { picker: 'grantDepartmentChairModal', confirm: 'chairConfirmModal', prefix: 'chairConfirm', unit: 'chairConfirmDept', empty: 'noChairFacultyFound' },
    };
    const pendingPickers = new WeakSet();
    const openingModals = new WeakSet();
    const returnToPicker = new WeakMap();

    document.addEventListener('show.bs.modal', (event) => openingModals.add(event.target));
    document.addEventListener('shown.bs.modal', (event) => openingModals.delete(event.target));

    const filterPicker = (type) => {
        const config = settings[type];
        const picker = config && document.getElementById(config.picker);
        if (!picker) return;

        const scope = picker.querySelector('[data-designation-filter]')?.value || '';
        const term = (picker.querySelector('[data-designation-search]')?.value || '').toLowerCase().trim();
        const candidates = picker.querySelectorAll('[data-designation-candidate]');
        let matches = 0;
        candidates.forEach((candidate) => {
            const visible = (!scope || candidate.dataset.scopeId === scope)
                && (!term || candidate.dataset.search.includes(term));
            candidate.classList.toggle('d-none', !visible);
            if (visible) matches++;
        });
        document.getElementById(config.empty)?.classList.toggle('d-none', matches > 0 || candidates.length === 0);
    };

    const openPicker = (type) => {
        const config = settings[type];
        const picker = config && document.getElementById(config.picker);
        if (!picker) return;
        filterPicker(type);
        bootstrap.Modal.getOrCreateInstance(picker).show();
    };

    document.addEventListener('click', (event) => {
        const candidate = event.target.closest('[data-designation-candidate]');
        if (!candidate) return;
        event.preventDefault();
        const config = settings[candidate.dataset.designationCandidate];
        const picker = config && document.getElementById(config.picker);
        const confirmation = config && document.getElementById(config.confirm);
        const form = confirmation?.querySelector('form');
        if (!picker || !confirmation || !form || pendingPickers.has(picker)) return;

        const data = candidate.dataset;
        form.action = data.designationAction;
        const userInput = form.querySelector('[name="user_id"]');
        if (userInput) userInput.value = data.userId;
        document.getElementById(`${config.prefix}Name`).textContent = data.name;
        document.getElementById(`${config.prefix}Email`).textContent = data.email;
        document.getElementById(`${config.prefix}Avatar`).textContent = (data.name.charAt(0) || 'U').toUpperCase();
        document.getElementById(config.unit).textContent = data.unit + (data.department ? ` • ${data.department}` : '');

        // Bootstrap supports one open modal at a time. Wait for the picker to close.
        pendingPickers.add(picker);
        returnToPicker.set(confirmation, picker);
        const showConfirmation = () => {
            pendingPickers.delete(picker);
            if (picker.isConnected && confirmation.isConnected) {
                bootstrap.Modal.getOrCreateInstance(confirmation).show();
            }
        };
        if (picker.classList.contains('show')) {
            picker.addEventListener('hidden.bs.modal', showConfirmation, { once: true });
            const hidePicker = () => bootstrap.Modal.getOrCreateInstance(picker).hide();
            if (openingModals.has(picker)) picker.addEventListener('shown.bs.modal', hidePicker, { once: true });
            else hidePicker();
        } else {
            showConfirmation();
        }
    });

    document.addEventListener('hidden.bs.modal', (event) => {
        const picker = returnToPicker.get(event.target);
        returnToPicker.delete(event.target);
        if (picker?.isConnected) bootstrap.Modal.getOrCreateInstance(picker).show();
    });

    ['input', 'change'].forEach((name) => {
        document.addEventListener(name, (event) => {
            const control = event.target.closest('[data-designation-filter], [data-designation-search]');
            if (control) filterPicker(control.dataset.designationFilter || control.dataset.designationSearch);
        });
    });

    window.initializeDesignationPickers = () => {
        Object.entries(settings).forEach(([type, config]) => {
            filterPicker(type);
            const picker = document.getElementById(config.picker);
            if (picker?.hasAttribute('data-designation-auto-open')) {
                picker.removeAttribute('data-designation-auto-open');
                openPicker(type);
            }
        });
    };
    window.initializeDesignationPickers();
})();
