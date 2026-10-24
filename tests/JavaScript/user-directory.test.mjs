import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const portalScript = readFileSync(new URL('../../public/js/portal.js', import.meta.url), 'utf8');
const directoryScript = readFileSync(new URL('../../public/js/user-directory.js', import.meta.url), 'utf8');

const scriptSection = (start, end) => {
    const startIndex = portalScript.indexOf(start);
    const endIndex = portalScript.indexOf(end, startIndex);
    assert.ok(startIndex >= 0 && endIndex > startIndex, 'Portal script section exists');
    return portalScript.slice(startIndex, endIndex);
};

// Check the complete portal navigation script as well as individual behaviors.
test('portal script has valid JavaScript syntax', () => {
    new vm.Script(portalScript);
});

const createPageContext = () => {
    let tables = [{ scrollLeft: 160, scrollTop: 24 }, { scrollLeft: 80, scrollTop: 12 }];
    const container = {
        content: 'original rows',
        set innerHTML(value) {
            this.content = value;
            tables = [{ scrollLeft: 0, scrollTop: 0 }, { scrollLeft: 0, scrollTop: 0 }];
        },
        get innerHTML() { return this.content; },
        querySelector() { return null; },
    };
    const sidebar = { innerHTML: 'current sidebar' };
    const topbar = { innerHTML: 'current search field', searchValue: 'Person' };
    const elements = { '.page-container': container, '.sidebar': sidebar, '.topbar': topbar };
    const window = {
        location: { href: 'http://localhost/super-admin/users?tab=students&department=1&program=2', pathname: '/super-admin/users' },
        scrollX: 5, scrollY: 640,
        scrollTo(x, y) { this.scrollX = x; this.scrollY = y; },
        history: {
            pushState(_state, _title, url) {
                window.location.href = url;
                window.location.pathname = new URL(url).pathname;
            },
        },
        initializeTableTabs() { this.selectedTab = new URL(this.location.href).searchParams.get('tab'); },
        initializeUserDirectory() { this.directoryInitialized = true; },
    };
    const context = {
        window, URL, document: { body: {}, title: 'Users', querySelector: (selector) => elements[selector], querySelectorAll: (selector) => selector.includes('table-responsive') ? tables : [] },
        fetch: async () => ({ ok: true, text: async () => 'new rows' }),
        DOMParser: class {
            parseFromString(html) {
                return { body: {}, title: 'Users', querySelector: (selector) => ({ innerHTML: selector === '.page-container' ? html : 'new chrome' }) };
            }
        },
        cleanupBootstrapOverlays() {}, syncPageStyles() {}, initPortalPollSections() {},
        applyPageSearch() { context.searchApplied = true; },
        movePageFlashAlertsToToast() {}, autoDismissPageInfoAlerts() {}, cleanupStaleBootstrapBackdrops() {},
    };
    vm.createContext(context);
    vm.runInContext(scriptSection('    let latestPortalPageRequest', "\n    document.addEventListener('click'") + '\nglobalThis.loadPortalPage = loadPortalPage;', context);
    return { context, window, container, sidebar, topbar, getTables: () => tables };
};

test('report draft navigation guard can keep the current page without starting a fetch', async () => {
    const { context, window, container } = createPageContext();
    let requests = 0;
    context.fetch = async () => { requests++; throw new Error('Should not fetch'); };
    window.beforeReportNavigation = async () => false;
    assert.equal(await context.loadPortalPage('http://localhost/instructor/reports'), true);
    assert.equal(requests, 0);
    assert.equal(container.innerHTML, 'original rows');
});

test('page navigation waits for report draft saving before fetching the next page', async () => {
    const { context, window, container } = createPageContext();
    let finishSave;
    let requests = 0;
    window.beforeReportNavigation = () => new Promise(resolve => { finishSave = resolve; });
    context.fetch = async () => { requests++; return { ok: true, text: async () => 'next page' }; };
    const navigation = context.loadPortalPage('http://localhost/instructor/reports');
    assert.equal(requests, 0);
    finishSave(true);
    await navigation;
    assert.equal(requests, 1);
    assert.equal(container.innerHTML, 'next page');
});

test('saving preserves URL, tab, search and window/table scroll while fetching fresh rows', async () => {
    const { context, window, container, sidebar, topbar, getTables } = createPageContext();
    const url = window.location.href;
    assert.equal(await context.loadPortalPage(url, false, true), true);
    assert.equal(container.innerHTML, 'new rows');
    assert.equal(window.location.href, url);
    assert.equal(window.selectedTab, 'students');
    assert.equal(topbar.searchValue, 'Person');
    assert.equal(topbar.innerHTML, 'current search field');
    assert.equal(sidebar.innerHTML, 'current sidebar');
    assert.deepEqual([window.scrollX, window.scrollY], [5, 640]);
    assert.deepEqual(getTables().map((table) => [table.scrollLeft, table.scrollTop]), [[160, 24], [80, 12]]);
    assert.ok(context.searchApplied && window.directoryInitialized);
});

test('failed refresh keeps the current page instead of navigating away', async () => {
    const { context, window, container } = createPageContext();
    const url = window.location.href;
    context.fetch = async () => ({ ok: false });
    assert.equal(await context.loadPortalPage(url, false, true), false);
    assert.equal(container.innerHTML, 'original rows');
    assert.equal(window.location.href, url);
    context.fetch = async () => { throw new Error('offline'); };
    assert.equal(await context.loadPortalPage(url, false, true), false);
    assert.equal(container.innerHTML, 'original rows');
});

test('search refresh restores input focus and caret after replacing the feature list', async () => {
    const { context, window, container } = createPageContext();
    const input = {
        value: '',
        selectionStart: 2, selectionEnd: 5,
        focus(options) { this.focusOptions = options; },
        setSelectionRange(start, end) { this.selection = [start, end]; },
    };
    const form = { querySelector: () => input, querySelectorAll: () => [] };
    const fetchedInput = { replaceWith(received) { assert.equal(received, input); } };
    let calls = 0;
    container.querySelector = () => ++calls === 1 ? form : fetchedInput;
    context.document.activeElement = input;
    assert.equal(await context.loadPortalPage(window.location.href, false, true), true);
    assert.equal(input.focusOptions.preventScroll, true);
    assert.deepEqual(input.selection, [2, 5]);
});

test('a search response cannot replace results after more text is typed during debounce', async () => {
    const { context, window, container } = createPageContext();
    const input = { value: 'new search' };
    container.querySelector = () => ({ querySelector: () => input });
    assert.equal(await context.loadPortalPage(`${window.location.href}&search=old`, true, true), true);
    assert.equal(container.innerHTML, 'original rows');
});

test('a tab changed while search is pending remains selected when results arrive', async () => {
    const { context, window } = createPageContext();
    let resolveSearch;
    context.fetch = () => new Promise((resolve) => { resolveSearch = resolve; });
    const pending = context.loadPortalPage(window.location.href, true, true);
    window.location.href = window.location.href.replace('tab=students', 'tab=teachers');
    resolveSearch({ ok: true, text: async () => 'searched rows' });
    await pending;
    assert.equal(window.selectedTab, 'teachers');
});

test('normal navigation still replaces page chrome and resets scroll', async () => {
    const { context, window, sidebar, topbar } = createPageContext();
    assert.equal(await context.loadPortalPage('http://localhost/super-admin/programs'), true);
    assert.equal(window.location.pathname, '/super-admin/programs');
    assert.equal(sidebar.innerHTML, 'new chrome');
    assert.equal(topbar.innerHTML, 'new chrome');
    assert.deepEqual([window.scrollX, window.scrollY], [0, 0]);
});

test('a late filter response cannot overwrite the most recent selection', async () => {
    const { context, window, container } = createPageContext();
    let resolveFirst;
    const firstResponse = new Promise((resolve) => { resolveFirst = resolve; });
    context.fetch = (url) => url.includes('program=1')
        ? firstResponse
        : Promise.resolve({ ok: true, text: async () => 'second filter rows' });
    const first = context.loadPortalPage('http://localhost/super-admin/users?tab=students&program=1', true, true);
    await context.loadPortalPage('http://localhost/super-admin/users?tab=students&program=2', true, true);
    resolveFirst({ ok: true, text: async () => 'outdated rows' });
    await first;
    assert.equal(container.innerHTML, 'second filter rows');
    assert.ok(window.location.href.includes('program=2'));
});

test('role controls and tab filters work on newly fetched user forms', () => {
    const handlers = {};
    let roleSelect = { id: 'edit_base_role_7', value: 'student' };
    const fields = Object.fromEntries(['department_id', 'employee_number', 'program_id', 'student_number']
        .map((name) => [`edit_${name}_7`, { value: 'assigned' }]));
    const sections = Object.fromEntries(['teacher_department', 'teacher_employee', 'student_program', 'student_number', 'authorization']
        .map((name) => [`edit_${name}_section_7`, {}]));
    const authorization = { checked: true, dataset: { teacherTarget: roleSelect.id } };
    const activeTabInput = { value: 'teachers' };
    const filters = ['teachers', 'students'].map((name) => ({ dataset: { userFilterTab: name } }));
    const elements = { ...fields, ...sections, activeTabInput, userTabs: { closest: () => ({ dataset: { tableTabsSelected: 'students' } }) } };
    const document = {
        getElementById: (id) => elements[id],
        querySelectorAll(selector) {
            if (selector.startsWith('select')) return [roleSelect];
            if (selector.startsWith('.authorization-checkbox')) return [authorization];
            return filters;
        },
        addEventListener(name, callback) { handlers[name] = callback; },
    };
    const window = {};
    vm.runInNewContext(directoryScript, { document, window });
    window.initializeUserDirectory();
    assert.equal(fields.edit_department_id_7.disabled, true);
    assert.equal(fields.edit_program_id_7.required, true);
    assert.equal(authorization.checked, false);
    assert.equal(activeTabInput.value, 'students');
    assert.equal(filters[0].hidden, true);

    roleSelect = { id: 'edit_base_role_7', value: 'instructor' };
    handlers.change({ target: { closest: (selector) => selector.startsWith('select') ? roleSelect : null } });
    assert.equal(fields.edit_department_id_7.required, true);
    assert.equal(fields.edit_program_id_7.disabled, true);
    assert.equal(sections.edit_teacher_department_section_7.hidden, false);
    assert.equal(authorization.disabled, false);
    handlers.click({ target: { closest: () => ({ dataset: { tableTabButton: 'teachers' } }) } });
    assert.equal(activeTabInput.value, 'teachers');
    assert.equal(filters[0].hidden, false);
    assert.equal(filters[1].hidden, true);
});

const createFormContext = () => {
    const classList = (initial = []) => {
        const values = new Set(initial);
        return { add: (value) => values.add(value), remove: (value) => values.delete(value), contains: (value) => values.has(value) };
    };
    const errorBox = { textContent: '', classList: classList(['d-none']), hasAttribute: () => true };
    const button = { disabled: false };
    const modal = { classList: classList(['show']), addEventListener(_name, handler) { this.onHidden = handler; } };
    const form = {
        action: 'http://localhost/super-admin/users/test-user', method: 'POST',
        dataset: { reloadPageOnSuccess: 'true', preservePageState: 'true' },
        querySelectorAll: () => [button], querySelector: () => errorBox,
        closest: (selector) => selector === '.modal' ? modal : form,
    };
    const messages = [];
    const refreshCalls = [];
    const context = {
        document: { addEventListener(_name, handler) { context.submit = handler; } },
        URL,
        window: { location: { href: 'http://localhost/super-admin/users?tab=students&program=2' } },
        fetch: async () => ({ ok: true, headers: { get: () => 'application/json' }, json: async () => ({ message: 'Saved.' }) }),
        FormData: class {},
        formErrorMessage: async () => 'The email has already been taken.',
        localStorage: { setItem() { throw new Error('Storage disabled'); } },
        bootstrap: { Modal: { getOrCreateInstance: () => ({ hide() { context.hideCalled = true; } }) } },
        showPortalMessage: (...args) => messages.push(args), cleanupStaleBootstrapBackdrops() {},
        loadPortalPage: async (...args) => { refreshCalls.push(args); return true; },
    };
    vm.createContext(context);
    vm.runInContext(scriptSection("    document.addEventListener('submit', async", "    window.navigatePortalPage ="), context);
    const submit = () => context.submit({ target: form, preventDefault() {} });
    return { context, form, errorBox, button, modal, messages, refreshCalls, submit };
};

test('AJAX save rejects duplicate submits and waits for modal closure before preserving refresh', async () => {
    const { context, form, button, modal, messages, refreshCalls, submit } = createFormContext();
    let resolveSave;
    context.fetch = () => new Promise((resolve) => { resolveSave = resolve; });
    const pending = submit();
    await submit();
    assert.equal(button.disabled, true);
    assert.equal(form.dataset.ajaxSubmitting, 'true');
    resolveSave({ ok: true, headers: { get: () => 'application/json' }, json: async () => ({ message: 'Saved.' }) });
    await new Promise(setImmediate);
    assert.ok(context.hideCalled);
    assert.equal(refreshCalls.length, 0);
    assert.equal(button.disabled, true);
    modal.onHidden();
    await pending;
    assert.equal(refreshCalls.length, 1);
    assert.deepEqual(refreshCalls[0], [context.window.location.href, false, true]);
    assert.deepEqual(messages, [['Saved.']]);
    assert.equal(button.disabled, false);
    assert.equal(form.dataset.ajaxSubmitting, undefined);
});

test('422 validation keeps the edit modal open and allows correction and resubmission', async () => {
    const { context, form, errorBox, button, modal, refreshCalls, submit } = createFormContext();
    context.fetch = async () => ({ ok: false });
    await submit();
    assert.equal(errorBox.textContent, 'The email has already been taken.');
    assert.equal(errorBox.classList.contains('d-none'), false);
    assert.equal(modal.classList.contains('show'), true);
    assert.equal(refreshCalls.length, 0);
    assert.equal(button.disabled, false);
    assert.equal(form.dataset.ajaxSubmitting, undefined);
});

test('an expired-session HTML response cannot be treated as a successful save', async () => {
    const { context, errorBox, refreshCalls, messages, submit } = createFormContext();
    context.fetch = async () => ({ ok: true, headers: { get: () => 'text/html' } });
    await submit();
    assert.ok(errorBox.textContent.includes('session may have expired'));
    assert.equal(context.hideCalled, undefined);
    assert.equal(refreshCalls.length, 0);
    assert.equal(messages.length, 0);
});

test('refresh failure after saving reports that the data was already saved', async () => {
    const { context, modal, messages, submit } = createFormContext();
    modal.classList.remove('show');
    context.loadPortalPage = async () => false;
    await submit();
    assert.equal(messages[0][0], 'Saved.');
    assert.ok(messages[1][0].includes('Changes saved, but the list could not refresh'));
    assert.equal(messages[1][1], 'warning');
});

test('account creation shows a busy state, resets after saving, and reports email delivery failure separately', async () => {
    const { context, form, button, modal, messages, refreshCalls, submit } = createFormContext();
    form.action = 'http://localhost/super-admin/users';
    form.dataset.resetOnSuccess = 'true';
    form.dataset.submittingText = 'Creating...';
    form.reset = () => form.resets = (form.resets || 0) + 1;
    button.innerHTML = 'Create Student';
    let resolveSave;
    context.fetch = () => new Promise((resolve) => resolveSave = resolve);
    const pending = submit();
    assert.equal(button.textContent, 'Creating...');
    assert.equal(button.disabled, true);
    assert.equal(form.resets, undefined);
    resolveSave({
        ok: true, headers: { get: () => 'application/json' },
        json: async () => ({ message: 'User account created successfully.', warning: 'Initial password email was not delivered.' }),
    });
    await new Promise(setImmediate);
    modal.onHidden();
    await pending;
    assert.equal(form.resets, 1);
    assert.equal(button.innerHTML, 'Create Student');
    assert.equal(button.disabled, false);
    assert.deepEqual(messages, [
        ['User account created successfully.'], ['Initial password email was not delivered.', 'warning'],
    ]);
    assert.deepEqual(refreshCalls[0], [context.window.location.href, false, true]);
});

test('authentication errors preserve the current feature page even during ordinary navigation', async () => {
    const { context, window, container } = createPageContext();
    const originalUrl = window.location.href;
    window.portalFetch = async () => {
        window.portalAuthenticationRequired = true;
        return { ok: false, status: 401 };
    };
    assert.equal(await context.loadPortalPage('http://localhost/student/classes'), false);
    assert.equal(window.location.href, originalUrl);
    assert.equal(container.content, 'original rows');
});
