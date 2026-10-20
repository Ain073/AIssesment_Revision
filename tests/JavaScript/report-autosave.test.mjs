import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/report-autosave.js', import.meta.url), 'utf8');
const target = () => ({
    listeners: new Map(),
    addEventListener(name, fn) { this.listeners.set(name, [...(this.listeners.get(name) || []), fn]); },
    removeEventListener(name, fn) { this.listeners.set(name, (this.listeners.get(name) || []).filter(item => item !== fn)); },
    emit(name, extras = {}) {
        const event = { prevented: false, preventDefault() { this.prevented = true; }, ...extras };
        event.done = Promise.all((this.listeners.get(name) || []).map(fn => fn(event)));
        return event;
    },
});
const settle = async () => { for (let count = 0; count < 12; count++) await Promise.resolve(); };
const setup = () => {
    const requests = [];
    const timers = new Map();
    const storage = new Map();
    const legacyStorage = new Map();
    const downloads = [];
    let timerId = 0;
    const window = target();
    window.confirm = () => false;
    const nodes = Object.fromEntries(['status', 'retry', 'download'].map(name => [name, { ...target(), hidden: true, textContent: '' }]));
    const feedback = { querySelector: selector => nodes[selector.includes('status') ? 'status' : selector.includes('retry') ? 'retry' : 'download'] };
    const document = { ...target(), hidden: false, getElementById: () => feedback, createElement: () => ({ click() { downloads.push(this); } }) };
    window.portalFetch = (url, options) => new Promise((resolve, reject) => {
        requests.push({ url, options, fail: reject, respond(status = 200, data = { revisions: { quiz: 1 } }) {
            resolve({ ok: status >= 200 && status < 300, status, json: async () => data });
        } });
        options.signal.addEventListener('abort', () => reject(new Error('timeout')));
    });
    const sessionStorage = { getItem: key => storage.get(key) || null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) };
    class FormData {
        constructor(form) { this.snapshot = Object.fromEntries([...form.fields, ...form.revisionInputs].map(input => [input.name, input.value])); this.deleted = []; }
        delete(name) { this.deleted.push(name); }
    }
    vm.runInNewContext(script, { window, document, FormData, sessionStorage, localStorage: { getItem: key => legacyStorage.get(key) || null }, AbortController, Blob,
        URL: { createObjectURL: () => 'blob:copy', revokeObjectURL() {} },
        setTimeout(fn, delay) { const id = ++timerId; timers.set(id, { fn, delay }); return id; },
        clearTimeout: id => timers.delete(id),
    });
    const createForm = ({ enabled = true, conflict = false, unsaved = false, revision = 0, value = 'Server text', account = '1' } = {}) => {
        const form = { ...target(), isConnected: true, submissions: [],
            dataset: { reportAccount: account, reportAutosaveUrl: '/instructor/reports/autosave', reportAutosaveEnabled: String(enabled), reportConflict: String(conflict), reportUnsaved: String(unsaved) },
            fields: [{ name: 'reports[quiz][issues_concern]', value }],
            revisionInputs: [{ dataset: { reportRevision: 'quiz' }, name: 'revisions[quiz]', value: String(revision) }],
            querySelector: () => ({ value: 'formative' }),
            querySelectorAll(selector) { return selector.includes('report-revision') ? this.revisionInputs : selector.startsWith('input') ? [{ value: 'quiz' }] : this.fields; },
            requestSubmit(submitter) {
                const event = this.emit('submit', { submitter });
                if (!event.prevented) this.submissions.push({ submitter, revision: this.revisionInputs[0].value, value: this.fields[0].value });
            },
        };
        const api = window.createReportAutosave(form);
        return { form, api };
    };
    const tick = async (delay = 1000) => {
        for (const [id, timer] of [...timers]) if (timer.delay === delay) { timers.delete(id); timer.fn(); }
        await settle();
    };
    const edit = (instance, text = 'Edited text') => { instance.form.fields[0].value = text; instance.api.changed(); };
    return { window, document, nodes, requests, timers, storage, legacyStorage, downloads, createForm, tick, edit };
};

test('debounces typing and reports Saved only after a validated server acknowledgement', async () => {
    const env = setup(); const page = env.createForm();
    assert.equal(env.requests.length, 0);
    env.edit(page, 'First'); env.edit(page, 'Latest');
    assert.equal(env.requests.length, 0);
    assert.equal(env.storage.size, 1);
    await env.tick();
    assert.equal(env.requests.length, 1);
    assert.equal(env.requests[0].options.body.snapshot['reports[quiz][issues_concern]'], 'Latest');
    assert.equal(env.nodes.status.textContent, 'Saving…');
    assert.equal(env.requests[0].options.headers.Accept, 'application/json');
    assert.ok(env.requests[0].options.body.deleted.includes('save_action'));
    env.requests[0].respond(); await settle();
    assert.equal(env.nodes.status.textContent, 'Saved to server.');
    assert.equal(page.form.revisionInputs[0].value, '1');
    assert.equal(env.storage.size, 0);
});

test('serializes saves and sends edits made during a request with the acknowledged revision', async () => {
    const env = setup(); const page = env.createForm();
    env.edit(page, 'First'); await env.tick();
    env.edit(page, 'Second'); await env.tick();
    assert.equal(env.requests.length, 1);
    env.requests[0].respond(); await settle();
    assert.match(env.nodes.status.textContent, /Unsaved/);
    await env.tick();
    assert.equal(env.requests.length, 2);
    assert.equal(env.requests[1].options.body.snapshot['revisions[quiz]'], '1');
    assert.equal(env.requests[1].options.body.snapshot['reports[quiz][issues_concern]'], 'Second');
    env.requests[1].respond(200, { revisions: { quiz: 2 } }); await settle();
    assert.equal(env.nodes.status.textContent, 'Saved to server.');
});

test('reverting text during an in-flight save still sends the revert afterwards', async () => {
    const env = setup(); const page = env.createForm();
    env.edit(page); await env.tick(); env.edit(page, 'Server text');
    env.requests[0].respond(); await settle(); await env.tick();
    assert.equal(env.requests.length, 2);
    assert.equal(env.requests[1].options.body.snapshot['reports[quiz][issues_concern]'], 'Server text');
});

test('409 preserves text and backup, stops retries and blocks stale manual finalization', async () => {
    const env = setup(); const page = env.createForm();
    env.edit(page); await env.tick(); env.requests[0].respond(409, { code: 'report_conflict' }); await settle();
    assert.match(env.nodes.status.textContent, /changed elsewhere/);
    assert.equal(page.form.fields[0].value, 'Edited text');
    assert.equal(env.nodes.download.hidden, false);
    assert.equal(env.storage.size, 1);
    env.edit(page, 'More edits'); await env.tick();
    env.window.emit('online'); await settle();
    assert.equal(env.requests.length, 1);
    assert.equal(page.form.emit('submit', { submitter: { value: 'finalized' } }).prevented, true);
    env.nodes.download.emit('click');
    assert.equal(env.downloads[0].download, 'unsaved-report.txt');
});

test('offline, timeout, HTML response and malformed acknowledgement never claim success', async () => {
    for (const failure of ['offline', 'timeout', 'html', 'missing-revision', 'backwards-revision']) {
        const env = setup(); const page = env.createForm({ revision: 2 });
        env.edit(page); await env.tick();
        if (failure === 'offline') env.requests[0].fail(new Error('offline'));
        else if (failure === 'timeout') await env.tick(30000);
        else env.requests[0].respond(200, failure === 'html' ? '<html>Login</html>' : { revisions: failure === 'missing-revision' ? {} : { quiz: 1 } });
        await settle();
        assert.match(env.nodes.status.textContent, /could not be confirmed/);
        assert.equal(env.nodes.retry.hidden, false);
        assert.equal(page.form.revisionInputs[0].value, '2');
        assert.equal(env.storage.size, 1);
        env.nodes.retry.emit('click'); await settle();
        assert.equal(env.requests.length, 2);
        assert.equal(env.requests[1].options.body.snapshot['revisions[quiz]'], '2');
    }
});

test('validation and throttling allow retry while session failures stop network saves', async () => {
    for (const status of [422, 429, 500, 401, 403, 419]) {
        const env = setup(); const page = env.createForm();
        env.edit(page); await env.tick(); env.requests[0].respond(status, {}); await settle();
        assert.equal(env.storage.size, 1);
        assert.equal(page.form.fields[0].value, 'Edited text');
        env.nodes.retry.emit('click'); await settle();
        assert.equal(env.requests.length, [401, 403, 419].includes(status) ? 1 : 2);
    }
});

test('validation feedback identifies the rejected report field and preserves unsaved text', async () => {
    const env = setup(); const page = env.createForm();
    env.edit(page); await env.tick();
    env.requests[0].respond(422, { errors: { course_code_title: ['The course code/title field is too long.'] } });
    await settle();
    assert.equal(env.nodes.status.textContent, 'Draft was not saved. The course code/title field is too long.');
    assert.equal(page.form.fields[0].value, 'Edited text');
    assert.equal(env.storage.size, 1);
    assert.equal(env.nodes.retry.hidden, false);
});

test('manual Finalize waits for autosave then submits latest edits and updated revision once', async () => {
    const env = setup(); const page = env.createForm();
    env.edit(page); await env.tick();
    const submitter = { value: 'finalized' };
    const event = page.form.emit('submit', { submitter });
    assert.equal(event.prevented, true);
    assert.equal(page.form.emit('submit', { submitter }).prevented, true);
    env.edit(page, 'Final content');
    env.requests[0].respond(); await event.done;
    assert.deepEqual(page.form.submissions, [{ submitter, revision: '1', value: 'Final content' }]);
    assert.equal(env.window.emit('beforeunload').prevented, false);
    assert.equal(env.storage.size, 1, 'Keep backup until the server validates the normal form submission');
});

test('finalized and mixed sheets never autosave but permit deliberate manual submission', async () => {
    const env = setup(); const page = env.createForm({ enabled: false });
    env.edit(page); await env.tick();
    env.document.hidden = true; env.document.emit('visibilitychange');
    assert.equal(env.requests.length, 0);
    assert.match(env.nodes.status.textContent, /Use Save Draft or Finalize/);
    assert.equal(page.form.emit('submit', { submitter: { value: 'finalized' } }).prevented, false);
});

test('restores a backup only for the same account and server revision', async () => {
    const env = setup(); const first = env.createForm();
    env.edit(first, 'Recovered text');
    const reload = env.createForm();
    assert.equal(reload.form.fields[0].value, 'Recovered text');
    await env.tick(); assert.equal(env.requests.length, 1);
    const newer = env.createForm({ revision: 5, value: 'Newer server text' });
    assert.equal(newer.form.fields[0].value, 'Newer server text');
    assert.match(env.nodes.status.textContent, /older unsaved copy/);
    assert.equal(env.nodes.download.hidden, false);
    const anotherAccount = env.createForm({ account: '99' });
    assert.equal(anotherAccount.form.fields[0].value, 'Server text');
});

test('warns when leaving with unsaved text, flushes on hidden, and does not send detached pages', async () => {
    const env = setup(); const page = env.createForm();
    assert.equal(env.window.emit('beforeunload').prevented, false);
    env.edit(page);
    assert.equal(env.window.emit('beforeunload').prevented, true);
    env.document.hidden = true; env.document.emit('visibilitychange');
    assert.equal(env.requests.length, 1);
    page.form.isConnected = false;
    env.requests[0].respond(); await settle();
    env.edit(page, 'Detached changes'); await env.tick();
    assert.equal(env.requests.length, 1);
    assert.equal(env.window.emit('beforeunload').prevented, false);
});

test('AJAX navigation flushes a pending draft and offers to stay if save cannot be confirmed', async () => {
    const env = setup(); const page = env.createForm();
    env.edit(page);
    const navigation = env.window.beforeReportNavigation();
    assert.equal(env.requests.length, 1);
    env.requests[0].respond();
    assert.equal(await navigation, true);
    env.edit(page, 'Offline edit');
    const failed = env.window.beforeReportNavigation();
    env.requests[1].fail(new Error('offline'));
    assert.equal(await failed, false);
    env.window.confirm = () => true;
    const leaving = env.window.beforeReportNavigation();
    env.requests[2].fail(new Error('offline'));
    assert.equal(await leaving, true);
    assert.equal(env.storage.size, 1);
});

test('validation redirect remains unsaved and a successful manual-save reload clears identical backup', async () => {
    const env = setup(); const page = env.createForm({ unsaved: true, value: 'Rejected form text' });
    assert.match(env.nodes.status.textContent, /Unsaved/);
    await env.tick();
    assert.equal(env.requests.length, 1);
    env.requests[0].respond(422, {}); await settle();
    env.createForm({ revision: 1, value: 'Rejected form text' });
    assert.equal(env.storage.size, 0);
    assert.equal(env.nodes.download.hidden, true);
});

test('legacy browser-only backups remain recoverable without replacing the latest server draft', async () => {
    const env = setup();
    env.legacyStorage.set('aissessment:report-draft:formative:quiz', JSON.stringify({
        savedAt: Date.now(), values: { 'reports[quiz][issues_concern]': 'Legacy unsaved text' },
    }));
    const page = env.createForm({ revision: 5 });
    assert.equal(page.form.fields[0].value, 'Server text');
    assert.equal(env.nodes.download.hidden, false);
    assert.match(env.nodes.status.textContent, /older unsaved copy/);
    assert.equal(env.legacyStorage.size, 1);
    await env.tick();
    assert.equal(env.requests.length, 0);
});
