import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/designation-picker.js', import.meta.url), 'utf8');

const createContext = () => {
    const handlers = {};
    const elements = {};
    const calls = [];
    const window = {};
    const document = {
        getElementById: (id) => elements[id],
        addEventListener(name, callback) { handlers[name] = callback; },
    };
    const bootstrap = { Modal: { getOrCreateInstance: (element) => ({
        show() { calls.push(['show', element.id]); },
        hide() { calls.push(['hide', element.id]); },
    }) } };
    // The script loads on a page without any designation markup, then AJAX adds it.
    vm.runInNewContext(script, { window, document, bootstrap });
    return { handlers, elements, calls, window };
};

const addPicker = (context, type) => {
    const ids = type === 'dean'
        ? ['grantAdminDeanModal', 'deanConfirmModal', 'deanConfirmCollege', 'noDeanFacultyFound']
        : ['grantDepartmentChairModal', 'chairConfirmModal', 'chairConfirmDept', 'noChairFacultyFound'];
    const classes = (initial = []) => {
        const values = new Set(initial);
        return { contains: (name) => values.has(name), toggle(name, on) { on ? values.add(name) : values.delete(name); } };
    };
    const candidate = {
        dataset: { designationCandidate: type, userId: '42', name: `Ana "Teacher" O'Neil`, email: 'ana@example.test', unit: 'IT',
            scopeId: '1', search: 'ana teacher it', designationAction: '/designation/grant' },
        classList: classes(), closest: () => candidate,
    };
    const scope = { value: '', dataset: { designationFilter: type } };
    const search = { value: '', dataset: { designationSearch: type } };
    const userInput = {};
    const form = { querySelector: () => type === 'dean' ? userInput : null };
    const picker = {
        id: ids[0], isConnected: true, classList: classes(['show']),
        querySelector(selector) { return selector.includes('filter') ? scope : search; },
        querySelectorAll() { return [candidate]; },
        addEventListener(name, callback, options) { assert.equal(options.once, true); this[name === 'shown.bs.modal' ? 'onShown' : 'onHidden'] = callback; },
        hasAttribute: () => false,
    };
    const confirmation = { id: ids[1], isConnected: true, querySelector: () => form };
    context.elements[ids[0]] = picker;
    context.elements[ids[1]] = confirmation;
    context.elements[ids[2]] = {};
    context.elements[ids[3]] = { classList: classes(['d-none']) };
    for (const name of ['Name', 'Email', 'Avatar']) context.elements[`${type}Confirm${name}`] = {};
    const click = () => context.handlers.click({ target: candidate, preventDefault() {} });
    return { candidate, picker, confirmation, form, userInput, scope, search, unit: context.elements[ids[2]], empty: context.elements[ids[3]], click };
};

for (const type of ['dean', 'chair']) {
    test(`first ${type} teacher selection works after AJAX navigation and waits for picker closure`, () => {
        const context = createContext();
        const { picker, confirmation, form, userInput, click } = addPicker(context, type);
        context.window.initializeDesignationPickers();
        click();
        assert.deepEqual(context.calls, [['hide', picker.id]]);
        assert.equal(context.elements[`${type}ConfirmName`].textContent, `Ana "Teacher" O'Neil`);
        assert.equal(context.elements[`${type}ConfirmEmail`].textContent, 'ana@example.test');
        assert.equal(form.action, '/designation/grant');
        if (type === 'dean') assert.equal(userInput.value, '42');
        picker.onHidden();
        assert.deepEqual(context.calls.at(-1), ['show', confirmation.id]);
        context.handlers['hidden.bs.modal']({ target: confirmation });
        assert.deepEqual(context.calls.at(-1), ['show', picker.id]);
    });
}

test('duplicate teacher clicks cannot overwrite the selection during modal transition', () => {
    const context = createContext();
    const { candidate, picker, click } = addPicker(context, 'dean');
    click();
    candidate.dataset.name = 'Second Teacher';
    click();
    assert.equal(context.elements.deanConfirmName.textContent, `Ana "Teacher" O'Neil`);
    assert.equal(context.calls.length, 1);
    picker.onHidden();
});

test('selection made during the picker opening animation waits before hiding it', () => {
    const context = createContext();
    const { picker, confirmation, click } = addPicker(context, 'dean');
    context.handlers['show.bs.modal']({ target: picker });
    click();
    assert.equal(context.calls.length, 0);
    picker.onShown();
    context.handlers['shown.bs.modal']({ target: picker });
    assert.deepEqual(context.calls, [['hide', picker.id]]);
    picker.onHidden();
    assert.deepEqual(context.calls.at(-1), ['show', confirmation.id]);
});

test('a modal detached by page navigation cannot open its confirmation later', () => {
    const context = createContext();
    const { picker, confirmation, click } = addPicker(context, 'chair');
    click();
    picker.isConnected = confirmation.isConnected = false;
    picker.onHidden();
    context.handlers['hidden.bs.modal']({ target: confirmation });
    assert.deepEqual(context.calls, [['hide', picker.id]]);
});

test('search and scope filters work on newly inserted teacher lists', () => {
    const context = createContext();
    const { candidate, scope, search, empty } = addPicker(context, 'chair');
    search.value = 'missing';
    context.handlers.input({ target: { closest: () => search } });
    assert.equal(candidate.classList.contains('d-none'), true);
    assert.equal(empty.classList.contains('d-none'), false);
    search.value = 'ANA';
    scope.value = '2';
    context.handlers.change({ target: { closest: () => scope } });
    assert.equal(candidate.classList.contains('d-none'), true);
    scope.value = '1';
    context.handlers.change({ target: { closest: () => scope } });
    assert.equal(candidate.classList.contains('d-none'), false);
    assert.equal(empty.classList.contains('d-none'), true);
});
