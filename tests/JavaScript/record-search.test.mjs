import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const searchScript = readFileSync(new URL('../../public/js/record-search.js', import.meta.url), 'utf8');
const portalScript = readFileSync(new URL('../../resources/views/layouts/portal.blade.php', import.meta.url), 'utf8');

const searchContext = () => {
    const handlers = {};
    const timers = new Map();
    let nextTimer = 0;
    const status = {};
    const clear = {};
    const form = {
        isConnected: true, submissions: 0,
        querySelector(selector) { return selector.includes('status') ? status : selector.includes('clear') ? clear : input; },
        requestSubmit() { this.submissions++; }, setAttribute() {}, removeAttribute() {},
    };
    const input = { value: '', form, focus() { this.focused = true; } };
    const window = {
        setTimeout(callback, delay) { assert.equal(delay, 350); const id = ++nextTimer; timers.set(id, callback); return id; },
        clearTimeout(id) { timers.delete(id); },
    };
    const document = {
        addEventListener(name, callback) { handlers[name] = callback; },
        querySelector(selector) { return selector.includes('record-search-input') ? input : { dataset: { tableTabButton: 'students' } }; },
        querySelectorAll() { return []; },
    };
    vm.runInNewContext(searchScript, { window, document });
    return { window, document, handlers, timers, input, form, status, clear };
};

test('typing debounces database search and discards timers for disconnected pages', () => {
    const { handlers, timers, input, form, status } = searchContext();
    input.value = 'Name';
    handlers.input({ target: { closest: () => input } });
    handlers.input({ target: { closest: () => input } });
    assert.equal(timers.size, 1);
    assert.equal(status.textContent, 'Searching...');
    [...timers.values()][0]();
    assert.equal(form.submissions, 1);
    form.isConnected = false;
    handlers.input({ target: { closest: () => input } });
    [...timers.values()][0]();
    assert.equal(form.submissions, 1);
});

test('clear submits immediately and keeps input focus', () => {
    const { handlers, input, form, timers, clear } = searchContext();
    input.value = 'Name';
    handlers.input({ target: { closest: () => input } });
    clear.form = form;
    handlers.click({ target: { closest: () => clear } });
    assert.equal(input.value, '');
    assert.equal(input.focused, true);
    assert.equal(form.submissions, 1);
    assert.equal(timers.size, 0);
});

test('empty results use current-feature feedback and the correct student placeholder', () => {
    const { window, input, status } = searchContext();
    input.value = 'Missing account';
    window.initializeRecordSearch();
    assert.equal(input.placeholder, 'Search students...');
    assert.ok(status.textContent.includes('No matching records in this feature'));
    input.value = '';
    window.initializeRecordSearch();
    assert.equal(status.textContent, '');
});

const runSearchForm = (fields, recordSearch = true, audit = false) => {
    const calls = [];
    const action = audit ? 'http://localhost/super-admin/audit-logs' : 'http://localhost/super-admin/users';
    const form = {
        id: audit ? 'auditFilterForm' : 'recordSearchForm',
        method: 'GET',
        // A named form control shadows the native action property in the browser.
        action: fields.has('action') ? { toString: () => '[object HTMLSelectElement]' } : action,
        getAttribute: (name) => name === 'action' ? action : null,
        dataset: { preservePageState: 'true' },
        matches: () => recordSearch,
        querySelector(selector) { return fields.has(selector.match(/name="([^"]+)"/)[1]) ? {} : null; },
    };
    const context = {
        URL, URLSearchParams,
        FormData: class { forEach(callback) { fields.forEach(callback); } },
        window: { location: { href: audit
            ? 'http://localhost/super-admin/audit-logs?search=old&page=4'
            : 'http://localhost/super-admin/users?tab=students&program=2&department=1&search=old&page=4&teachers_page=3&students_page=2' } },
        document: { addEventListener(_event, callback) { context.submit = callback; } },
        loadPortalPage: async (...args) => { calls.push(args); return true; },
    };
    const start = portalScript.indexOf("        document.addEventListener('submit', (event) => {\n            const form = event.target.closest('form[data-ajax-page-form]')");
    assert.ok(start >= 0);
    const end = portalScript.indexOf("\n        document.addEventListener('click'", start);
    vm.createContext(context);
    vm.runInContext(portalScript.slice(start, end), context);
    context.submit({ target: { closest: () => form }, preventDefault() {} });
    return { url: new URL(calls[0][0]), historyMode: calls[0][1], preserveState: calls[0][2] };
};

test('feature search uses current tab/filters and resets pagination without adding history for every keystroke', () => {
    const result = runSearchForm(new Map([['search', 'New name'], ['tab', 'teachers']]));
    assert.equal(result.url.searchParams.get('search'), 'New name');
    assert.equal(result.url.searchParams.get('tab'), 'students');
    assert.equal(result.url.searchParams.get('program'), '2');
    assert.equal(result.url.searchParams.get('department'), '1');
    assert.equal(result.url.searchParams.has('page'), false);
    assert.equal(result.url.searchParams.has('teachers_page'), false);
    assert.equal(result.url.searchParams.has('students_page'), false);
    assert.equal(result.historyMode, 'replace');
    assert.equal(result.preserveState, true);
});

test('clearing search retains filters while a filter change retains the search term', () => {
    const cleared = runSearchForm(new Map([['search', '']]));
    assert.equal(cleared.url.searchParams.has('search'), false);
    assert.equal(cleared.url.searchParams.get('program'), '2');
    const filtered = runSearchForm(new Map([['department', '3']]), false);
    assert.equal(filtered.url.searchParams.get('search'), 'old');
    assert.equal(filtered.url.searchParams.get('tab'), 'students');
    assert.equal(filtered.url.searchParams.get('department'), '3');
    assert.equal(filtered.url.searchParams.has('teachers_page'), false);
    assert.equal(filtered.url.searchParams.has('students_page'), false);
});

test('audit role filtering keeps the audit URL when the Action dropdown shadows form.action', () => {
    const result = runSearchForm(new Map([
        ['search', 'Account'], ['role', 'instructor'], ['module', 'Users'],
        ['action', ''], ['from', '2026-10-01'], ['to', '2026-10-17'],
    ]), true, true);
    assert.equal(result.url.pathname, '/super-admin/audit-logs');
    assert.equal(result.url.searchParams.get('role'), 'instructor');
    assert.equal(result.url.searchParams.get('search'), 'Account');
    assert.equal(result.url.searchParams.get('module'), 'Users');
    assert.equal(result.url.searchParams.get('from'), '2026-10-01');
    assert.equal(result.url.searchParams.get('to'), '2026-10-17');
    assert.equal(result.url.searchParams.has('action'), false);
    assert.equal(result.url.searchParams.has('page'), false);
    assert.equal(result.historyMode, 'replace');
    assert.equal(result.preserveState, true);
});

test('audit Action filtering and clearing filters keep the audit URL and selected values', () => {
    const filtered = runSearchForm(new Map([['role', 'student'], ['action', 'UPDATE']]), true, true);
    assert.equal(filtered.url.pathname, '/super-admin/audit-logs');
    assert.equal(filtered.url.searchParams.get('role'), 'student');
    assert.equal(filtered.url.searchParams.get('action'), 'UPDATE');
    const cleared = runSearchForm(new Map([['role', ''], ['action', ''], ['search', '']]), true, true);
    assert.equal(cleared.url.pathname, '/super-admin/audit-logs');
    assert.equal(cleared.url.search, '');
});
