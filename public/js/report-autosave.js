(() => {
    let active = null;
    window.createReportAutosave = (form) => {
        active?.dispose();
        if (!form) return null;
        const fields = [...form.querySelectorAll('.report-edit-textarea, .report-header-input:not([data-report-generated])')];
        const revisionInputs = [...form.querySelectorAll('[data-report-revision]')];
        const feedback = document.getElementById('reportAutosaveFeedback');
        const status = feedback?.querySelector('[data-report-autosave-status]');
        const retry = feedback?.querySelector('[data-report-autosave-retry]');
        const download = feedback?.querySelector('[data-report-backup-download]');
        const keys = [...form.querySelectorAll('input[name="publish_assessment_keys[]"]')].map(input => input.value).sort();
        const type = form.querySelector('[name="report_type"]').value;
        const storageKey = `aissessment:server-report-draft:${form.dataset.reportAccount}:${type}:${keys.join('|')}`;
        const values = () => Object.fromEntries(fields.map(field => [field.name, field.value]));
        const revisions = () => Object.fromEntries(revisionInputs.map(input => [input.dataset.reportRevision, Number(input.value)]));
        const fingerprint = () => JSON.stringify(values());
        let baseline = form.dataset.reportUnsaved === 'true' ? '' : fingerprint();
        let timer = null;
        let pending = null;
        let paused = false;
        let submitting = false;
        let waitingToSubmit = false;
        let disposed = false;
        let blocked = form.dataset.reportConflict === 'true';
        let recovery = null;
        const enabled = form.dataset.reportAutosaveEnabled === 'true';
        const dirty = () => baseline !== fingerprint();
        const show = (message, canRetry = false) => {
            if (!form.isConnected || disposed) return;
            if (status) status.textContent = message;
            if (retry) retry.hidden = !canRetry;
            if (download) download.hidden = !dirty() && !recovery;
        };
        const clearLocal = () => {
            try { sessionStorage.removeItem(storageKey); } catch { /* Editing still works without storage. */ }
        };
        const backup = () => {
            if (!dirty()) return;
            try {
                sessionStorage.setItem(storageKey, JSON.stringify({ savedAt: Date.now(), revisions: revisions(), values: values() }));
            } catch { /* The unload warning and copy option remain available. */ }
        };
        const schedule = () => {
            clearTimeout(timer);
            if (enabled && !blocked && !paused && !disposed && dirty()) timer = setTimeout(save, 1000);
        };
        const changed = () => {
            backup();
            if (blocked) {
                show('Save stopped: this report changed elsewhere. Download your unsaved copy, then reload.');
                return;
            }
            show(dirty() ? (enabled ? 'Unsaved changes…' : 'Unsaved changes. Use Save Draft or Finalize to save edits to finalized reports.') : 'No unsaved changes.');
            schedule();
        };
        const save = () => {
            clearTimeout(timer);
            if (pending || paused || blocked || disposed || !form.isConnected || !enabled || !dirty()) return pending;
            const sentValues = fingerprint();
            const sentRevisions = revisions();
            const body = new FormData(form);
            // Save action is determined by the endpoint, never by a hidden/button value.
            body.delete('save_action');
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 30000);
            show('Saving…');
            pending = (async () => {
                try {
                    const response = await (window.portalFetch || fetch)(form.dataset.reportAutosaveUrl, {
                        method: 'POST', body, signal: controller.signal,
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        if (response.status === 409) {
                            blocked = true;
                            show('Save stopped: this report changed elsewhere or was finalized. Download your unsaved copy, then reload.');
                        } else if ([401, 403, 419].includes(response.status)) {
                            blocked = true;
                            show('Sign in or reload to continue saving. Copy or download your unsaved text first.');
                        } else if (response.status === 422) {
                            const validationMessage = Object.values(data?.errors || {}).flat()
                                .find(message => typeof message === 'string' && message.trim());
                            show(`Draft was not saved. ${validationMessage || 'Check the report fields and try again.'}`, true);
                        } else {
                            show('Save could not be confirmed. Keep this page open and retry.', true);
                        }
                        return;
                    }
                    if (!data?.revisions || !revisionInputs.every(input => {
                        const revision = data.revisions[input.dataset.reportRevision];
                        return Number.isSafeInteger(revision) && revision >= sentRevisions[input.dataset.reportRevision];
                    })) throw new Error('Unconfirmed save');
                    revisionInputs.forEach(input => { input.value = String(data.revisions[input.dataset.reportRevision]); });
                    baseline = sentValues;
                    if (dirty()) {
                        backup();
                        show('Unsaved changes…');
                        schedule();
                    } else {
                        clearLocal();
                        show('Saved to server.');
                    }
                } catch {
                    show('Save could not be confirmed. Keep this page open and retry.', true);
                } finally {
                    clearTimeout(timeout);
                    pending = null;
                }
            })();
            return pending;
        };
        const onRetry = () => { save(); };
        const onOnline = () => { if (form.isConnected) save(); };
        const onVisibility = () => { if (document.hidden) save(); };
        const beforeNavigation = async () => {
            if (disposed || !form.isConnected || submitting) return true;
            if (pending) await pending;
            if (dirty() && enabled && !blocked) await save();
            if (!dirty()) return true;
            backup();
            return window.confirm('Your latest report changes are not saved to the server. Leave this page? A local backup will remain in this browser tab.');
        };
        const onUnload = event => {
            if (!disposed && form.isConnected && !submitting && (dirty() || pending)) {
                backup();
                event.preventDefault();
                event.returnValue = '';
            }
        };
        const onSubmit = async event => {
            if (submitting) return;
            if (waitingToSubmit) { event.preventDefault(); return; }
            if (blocked) {
                event.preventDefault();
                show('Save stopped. Download your unsaved copy, then reload the latest report.');
                return;
            }
            backup();
            paused = true;
            clearTimeout(timer);
            if (!pending) {
                submitting = true;
                return; // Keep the ordinary, server-validated Save/Finalize submission.
            }
            event.preventDefault();
            waitingToSubmit = true;
            const submitter = event.submitter;
            show('Finishing the current save…');
            await pending; // Aborting fetch cannot cancel a write already running on the server.
            waitingToSubmit = false;
            if (blocked || disposed || !form.isConnected) { paused = false; return; }
            submitting = true;
            form.requestSubmit(submitter);
        };
        const onDownload = () => {
            const copy = dirty() ? values() : (recovery?.values || values());
            const text = Object.entries(copy).map(([name, value]) => {
                const field = fields.find(input => input.name === name);
                const assessment = field?.closest?.('tr')?.firstElementChild?.textContent?.trim().replace(/\s+/g, ' ');
                const label = name.replace(/^reports\[[^\]]+\]\[([^\]]+)\]$/, '$1').replaceAll('_', ' ');
                return `${assessment ? `${assessment} - ` : ''}${label}\n${value}`;
            }).join('\n\n');
            const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
            const link = document.createElement('a');
            link.href = url;
            link.download = 'unsaved-report.txt';
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 0);
        };
        try {
            const saved = JSON.parse(sessionStorage.getItem(storageKey));
            if (saved?.values && JSON.stringify(saved.values) === baseline) {
                clearLocal(); // A completed manual Save/Finalize already persisted this exact copy.
            } else if (saved?.values && Date.now() - saved.savedAt < 7 * 86400000) {
                if (enabled && !blocked && JSON.stringify(saved.revisions) === JSON.stringify(revisions())) {
                    fields.forEach(field => {
                        if (typeof saved.values[field.name] === 'string') field.value = saved.values[field.name];
                    });
                } else {
                    recovery = saved; // Never restore an old backup over a newer server version.
                }
            } else { clearLocal(); }
        } catch { /* Malformed storage never replaces server content. */ }
        if (!recovery && !dirty()) {
            try {
                // Older browser-only drafts have no server revision, so offer a copy instead of restoring them.
                const legacy = JSON.parse(localStorage.getItem(`aissessment:report-draft:${type}:${keys.join('|')}`));
                if (legacy?.values && Date.now() - legacy.savedAt < 7 * 86400000
                    && JSON.stringify(legacy.values) !== baseline) recovery = legacy;
            } catch { /* Browser storage may be disabled. */ }
        }
        form.addEventListener('submit', onSubmit);
        retry?.addEventListener('click', onRetry);
        download?.addEventListener('click', onDownload);
        window.addEventListener('beforeunload', onUnload);
        window.addEventListener('online', onOnline);
        document.addEventListener('visibilitychange', onVisibility);
        window.beforeReportNavigation = beforeNavigation;
        if (blocked) show('This report changed elsewhere. Download your unsaved copy, then reload.');
        else if (recovery) show('An older unsaved copy is available to download. The latest server report is displayed.');
        else if (dirty()) changed();
        else show(enabled ? 'Draft autosave is on.' : 'Finalized reports are saved manually after Edit.');
        active = {
            changed,
            clearLocal() {
                clearLocal();
                if (!dirty()) show(enabled ? 'Draft autosave is on.' : 'Finalized reports are saved manually after Edit.');
            },
            dispose() {
                backup();
                disposed = true;
                clearTimeout(timer);
                form.removeEventListener('submit', onSubmit);
                retry?.removeEventListener('click', onRetry);
                download?.removeEventListener('click', onDownload);
                window.removeEventListener('beforeunload', onUnload);
                window.removeEventListener('online', onOnline);
                document.removeEventListener('visibilitychange', onVisibility);
            },
        };
        return active;
    };
})();
