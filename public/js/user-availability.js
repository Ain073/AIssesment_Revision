(() => {
    const selector = 'input[name="email"], input[name="student_number"], input[name="employee_number"]';
    const states = new WeakMap();
    let nextId = 0;

    const stateFor = (field) => {
        if (! states.has(field)) {
            const feedback = document.createElement('div');
            feedback.id = `user-availability-${++nextId}`;
            feedback.className = 'small mt-1 text-secondary';
            feedback.setAttribute('aria-live', 'polite');
            field.insertAdjacentElement('afterend', feedback);
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), feedback.id].filter(Boolean).join(' '));
            states.set(field, { feedback, generation: 0, timer: null, controller: null, invalid: false, checkedValue: null });
        }
        return states.get(field);
    };

    const clear = (field, state) => {
        state.generation++;
        window.clearTimeout(state.timer);
        state.controller?.abort();
        state.timer = state.controller = null;
        state.checkedValue = null;
        if (state.invalid) {
            field.setCustomValidity('');
            field.removeAttribute('aria-invalid');
            state.invalid = false;
        }
        state.feedback.textContent = '';
    };

    const message = (state, text, tone = 'secondary') => {
        state.feedback.className = `small mt-1 text-${tone}`;
        state.feedback.textContent = text;
    };

    const queueCheck = (field, immediate = false) => {
        const form = field.form;
        if (! form?.dataset.userAvailabilityUrl) return;

        const state = stateFor(field);
        const value = field.value.trim();
        if (immediate && ! field.disabled && state.checkedValue === value) return;
        clear(field, state);
        if (field.disabled || ! value || form.dataset.ajaxSubmitting === 'true') return;
        if (value.length > 255 || field.validity.typeMismatch) return;

        const generation = state.generation;
        const isCurrent = () => state.generation === generation && field.isConnected && ! field.disabled
            && field.value.trim() === value && form.dataset.ajaxSubmitting !== 'true';

        message(state, 'Checking...');
        state.timer = window.setTimeout(async () => {
            state.timer = null;
            if (! isCurrent()) return;

            const controller = new AbortController();
            state.controller = controller;
            const timeout = window.setTimeout(() => controller.abort(), 10000);
            try {
                const response = await (window.portalFetch || fetch)(form.dataset.userAvailabilityUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json', 'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                    },
                    body: JSON.stringify({ field: field.name, value }),
                    signal: controller.signal,
                    cache: 'no-store',
                });
                if (! isCurrent()) return;
                if (! response.ok) throw new Error('Check unavailable');
                const data = await response.json();
                if (! isCurrent()) return;
                if (data.field !== field.name || typeof data.available !== 'boolean') throw new Error('Invalid check response');
                state.checkedValue = value;

                if (! data.available) {
                    const text = data.message || 'This value is already in use.';
                    field.setCustomValidity(text);
                    field.setAttribute('aria-invalid', 'true');
                    state.invalid = true;
                    message(state, text, 'danger');
                } else {
                    message(state, 'No duplicate found.', 'success');
                }
            } catch (error) {
                if (isCurrent()) message(state, 'Could not check right now. Save will validate this field.', 'warning');
            } finally {
                window.clearTimeout(timeout);
                if (state.controller === controller) state.controller = null;
            }
        }, immediate ? 0 : 450);
    };

    const clearWithin = (root) => {
        root.querySelectorAll(selector).forEach((field) => {
            const state = states.get(field);
            if (state) clear(field, state);
        });
    };

    // Delegation also covers modal forms inserted by AJAX navigation.
    document.addEventListener('input', (event) => {
        const field = event.target.closest(selector);
        if (field) queueCheck(field);
    });
    document.addEventListener('focusout', (event) => {
        const field = event.target.closest(selector);
        if (field) queueCheck(field, true);
    });
    document.addEventListener('change', (event) => {
        const form = event.target.form;
        if (! form?.dataset.userAvailabilityUrl) return;
        form.querySelectorAll(selector).forEach((field) => {
            if (field.disabled || ! field.value.trim()) {
                const state = states.get(field);
                if (state) clear(field, state);
            }
        });
    });
    document.addEventListener('reset', (event) => clearWithin(event.target));
    document.addEventListener('submit', (event) => clearWithin(event.target), true);
    document.addEventListener('hidden.bs.modal', (event) => clearWithin(event.target));
})();
