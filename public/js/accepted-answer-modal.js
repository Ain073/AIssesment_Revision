(() => {
    const states = new WeakMap();
    const field = (modal, name) => modal.querySelector(`[data-alternative-${name}]`);
    const current = (modal, state, version = state.version) => modal.isConnected
        && states.get(modal) === state && state.version === version;
    const message = (modal, text = '', kind = 'danger') => {
        const box = field(modal, 'message');
        box.className = `alert alert-${kind}${text ? '' : ' d-none'}`;
        box.textContent = text;
    };
    const clearPreview = (modal) => {
        field(modal, 'token').value = '';
        field(modal, 'preview').classList.add('d-none');
    };
    const busy = (modal, state, action = null) => {
        state.action = action;
        field(modal, 'input').disabled = action === 'loading' || action === 'saving';
        field(modal, 'preview-button').disabled = !!action || !state.data;
        field(modal, 'save-button').disabled = !!action || !field(modal, 'token').value;
        modal.querySelectorAll('[data-alternative-close]').forEach(button => { button.disabled = action === 'saving'; });
    };
    const request = async (url, options, controller, html = false) => {
        const timer = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(url, {
                ...options, credentials: 'same-origin', signal: controller.signal,
                headers: { Accept: html ? 'text/html' : 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.redirected) throw new Error('Your session may have expired. Reload the page and sign in again.');
            if (html) {
                if (!response.ok) throw new Error('Could not refresh scores.');
                return await response.text();
            }
            if (!(response.headers.get('content-type') || '').includes('application/json')) {
                throw new Error('Unexpected response. Reload the page before trying again.');
            }
            const data = await response.json();
            if (!response.ok) {
                const validation = Object.values(data.errors || {}).flat().find(value => typeof value === 'string');
                throw new Error(response.status >= 500 ? 'The request could not be completed. Reload and check the accepted answers.'
                    : validation || data.message || 'The request could not be completed.');
            }
            return data;
        } finally {
            clearTimeout(timer);
        }
    };
    const acceptedList = (list, answers, styled = false) => {
        list.replaceChildren();
        answers.forEach((answer, index) => {
            const row = document.createElement('li');
            const text = document.createElement('span');
            text.textContent = answer;
            row.append(text);
            if (styled) {
                row.className = 'saved-choice';
                const badge = document.createElement('span');
                badge.className = `badge ${index === 0 ? 'text-bg-success' : 'text-bg-light border text-secondary'} rounded-1`;
                badge.textContent = answers.length === 1 ? 'Correct' : index === 0 ? 'Primary' : 'Alternative';
                row.append(badge);
            }
            list.append(row);
        });
    };
    const updateAnswers = (data) => {
        document.querySelectorAll('[data-accepted-answer-list]').forEach(list => {
            if (list.dataset.acceptedAnswerList === data.item_key) acceptedList(list, data.accepted_answers, true);
        });
        document.querySelectorAll('[data-accepted-answer-text]').forEach(box => {
            if (box.dataset.acceptedAnswerText === data.item_key) box.textContent = data.accepted_answers.join(' / ');
        });
    };
    const refreshGrading = async (modal, state, key) => {
        const summary = document.querySelector('[data-grading-score-summary]');
        if (!summary) return;
        const url = window.location.href;
        const html = await request(url, {}, state.controller, true);
        if (!current(modal, state) || window.location.href !== url) return;
        const fresh = new DOMParser().parseFromString(html, 'text/html');
        const newSummary = fresh.querySelector('[data-grading-score-summary]');
        const row = Array.from(document.querySelectorAll('[data-grading-item]')).find(node => node.dataset.gradingItem === key);
        const newRow = Array.from(fresh.querySelectorAll('[data-grading-item]')).find(node => node.dataset.gradingItem === key);
        if (!newSummary || !row || !newRow) throw new Error('Could not refresh scores.');
        summary.textContent = newSummary.textContent;
        ['[data-grading-item-score]', '[data-grading-student-answer]'].forEach(selector => {
            const target = row.querySelector(selector), source = newRow.querySelector(selector);
            if (target && source) {
                target.textContent = source.textContent;
                target.className = source.className;
            }
        });
    };
    const load = async (modal, url) => {
        states.get(modal)?.controller?.abort();
        const state = { version: 0, controller: new AbortController(), data: null };
        states.set(modal, state);
        message(modal);
        clearPreview(modal);
        field(modal, 'input').value = '';
        field(modal, 'content').classList.add('d-none');
        field(modal, 'loading').classList.remove('d-none');
        busy(modal, state, 'loading');
        try {
            const data = await request(url, {}, state.controller);
            if (!current(modal, state)) return;
            state.data = data;
            field(modal, 'question').textContent = data.question;
            acceptedList(field(modal, 'accepted'), data.accepted_answers);
            field(modal, 'content').classList.remove('d-none');
        } catch (error) {
            if (current(modal, state)) message(modal, error.name === 'AbortError' ? 'Loading timed out. Close and reopen this dialog.' : error.message);
        } finally {
            if (current(modal, state)) {
                field(modal, 'loading').classList.add('d-none');
                busy(modal, state);
            }
        }
    };
    const preview = async (modal, form, state) => {
        const value = field(modal, 'input').value.trim();
        const version = ++state.version;
        state.controller = new AbortController();
        clearPreview(modal);
        message(modal);
        busy(modal, state, 'previewing');
        try {
            const data = await request(state.data.preview_url, { method: 'POST', body: new FormData(form) }, state.controller);
            if (!current(modal, state, version) || field(modal, 'input').value.trim() !== value) return;
            field(modal, 'token').value = data.preview_token;
            field(modal, 'summary').textContent = `Accept "${data.alternative}": ${data.students_count} student(s), ${data.attempts_count} submitted attempt(s) will gain credit.`;
            const body = field(modal, 'changes');
            body.replaceChildren();
            data.changes.forEach(change => {
                const row = document.createElement('tr');
                [change.student_name + (change.student_number ? ` (${change.student_number})` : ''), change.attempt_number,
                    change.answer_text, `${change.before} / ${state.data.points} → ${change.after} / ${state.data.points}`].forEach(value => {
                    const cell = document.createElement('td');
                    cell.textContent = String(value);
                    row.append(cell);
                });
                body.append(row);
            });
            field(modal, 'preview').classList.remove('d-none');
        } catch (error) {
            if (current(modal, state, version)) message(modal, error.name === 'AbortError' ? 'Preview timed out. Please try Preview again.' : error.message);
        } finally {
            if (current(modal, state, version)) busy(modal, state);
        }
    };
    const save = async (modal, form, state) => {
        if (!field(modal, 'token').value) return;
        state.controller = new AbortController();
        message(modal);
        busy(modal, state, 'saving');
        try {
            const data = await request(state.data.store_url, { method: 'POST', body: new FormData(form) }, state.controller);
            if (!current(modal, state)) return;
            clearPreview(modal);
            field(modal, 'input').value = '';
            state.data.accepted_answers = data.accepted_answers;
            acceptedList(field(modal, 'accepted'), data.accepted_answers);
            updateAnswers(data);
            let notice = data.message;
            try {
                await refreshGrading(modal, state, data.item_key);
            } catch {
                notice += ' The alternative was saved; reload the page to refresh the displayed scores.';
            }
            if (current(modal, state)) message(modal, notice, 'success');
        } catch (error) {
            if (current(modal, state)) {
                clearPreview(modal);
                message(modal, error.name === 'AbortError'
                    ? 'Could not confirm whether the alternative was saved. Close and reopen this dialog to check the accepted answers before trying again.' : error.message);
            }
        } finally {
            if (current(modal, state)) busy(modal, state);
        }
    };

    // Delegated events also cover pages inserted by portal AJAX navigation.
    document.addEventListener('show.bs.modal', event => {
        const modal = event.target;
        if (!modal.matches('[data-accepted-answer-modal]')) return;
        const url = event.relatedTarget?.dataset.acceptedAnswerUrl;
        if (url) load(modal, url);
    });
    document.addEventListener('hide.bs.modal', event => {
        if (states.get(event.target)?.action === 'saving') event.preventDefault();
    });
    document.addEventListener('hidden.bs.modal', event => {
        const state = states.get(event.target);
        if (state) {
            state.controller?.abort();
            states.delete(event.target);
        }
    });
    document.addEventListener('input', event => {
        if (!event.target.matches('[data-alternative-input]')) return;
        const modal = event.target.closest('[data-accepted-answer-modal]'), state = states.get(modal);
        if (!state || state.action === 'saving') return;
        state.version++;
        state.controller?.abort();
        clearPreview(modal);
        message(modal);
        busy(modal, state);
    });
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!form.matches('[data-alternative-preview-form], [data-alternative-save-form]')) return;
        event.preventDefault();
        const modal = form.closest('[data-accepted-answer-modal]'), state = states.get(modal);
        if (!state?.data || state.action) return;
        if (form.matches('[data-alternative-preview-form]')) preview(modal, form, state);
        else save(modal, form, state);
    });
})();
