import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/report-selection.js', import.meta.url), 'utf8');
const setup = () => {
    const handlers = new Map();
    let counts = 0;
    let searches = 0;
    const group = type => {
        const checkbox = { checked: false, dataset: { reportCheckbox: type } };
        const selectAll = { checked: false, dataset: { reportSelectAll: type } };
        const submit = { disabled: false };
        const count = { set textContent(value) { this.value = value; counts++; } };
        return { checkbox, selectAll, submit, count };
    };
    let groups = { formative: group('formative'), summative: group('summative') };
    const tabs = ['formative', 'summative'].map(type => ({
        dataset: { reportTab: type }, classList: { toggle() {} },
    }));
    const panels = tabs.map(tab => ({ dataset: { reportPanel: tab.dataset.reportTab } }));
    const input = { value: 'formative' };
    const document = {
        addEventListener(type, fn) { handlers.set(type, [...(handlers.get(type) || []), fn]); },
        querySelectorAll(selector) {
            if (selector === '[data-report-tab]') return tabs;
            if (selector === '[data-report-panel]') return panels;
            if (selector === '[data-report-type-input]') return [input];
            const type = selector.match(/="(formative|summative)"/)?.[1];
            return type ? [groups[type].checkbox] : [];
        },
        querySelector(selector) {
            const type = selector.match(/="(formative|summative)"/)?.[1];
            if (!type) return null;
            return groups[type][selector.includes('selected-count') ? 'count' : selector.includes('submit') ? 'submit' : 'selectAll'];
        },
    };
    const window = {
        location: { href: 'http://localhost/instructor/reports?search=Quiz' },
        history: { replaceState(_state, _title, url) { window.location.href = url; } },
        initializeRecordSearch() { searches++; },
    };
    const context = vm.createContext({ document, window, URL });
    const load = () => vm.runInContext(script, context);
    load();
    const emit = (event, selector, node) => {
        const result = { target: { closest: target => target === selector ? node : null }, prevented: false,
            preventDefault() { this.prevented = true; } };
        for (const fn of handlers.get(event) || []) fn(result);
        return result;
    };
    return { window, tabs, panels, input, load, emit, groups: () => groups,
        counts: () => counts, searches: () => searches,
        replace() { groups = { formative: group('formative'), summative: group('summative') }; },
    };
};

test('one checkbox change updates selection once, even if initialization or script loading repeats', () => {
    const app = setup();
    app.window.initializeReportsPage();
    app.window.initializeReportsPage();
    app.load();
    const before = app.counts();
    const group = app.groups().formative;
    group.checkbox.checked = true;
    app.emit('change', '[data-report-checkbox]', group.checkbox);
    assert.equal(app.counts() - before, 1);
    assert.equal(group.count.value, '1 selected');
    assert.equal(group.submit.disabled, false);
    assert.equal(group.selectAll.checked, true);
});

test('selection works on report controls replaced by AJAX', () => {
    const app = setup();
    app.replace();
    app.window.initializeReportsPage();
    const group = app.groups().summative;
    group.selectAll.checked = true;
    app.emit('change', '[data-report-select-all]', group.selectAll);
    assert.equal(group.checkbox.checked, true);
    assert.equal(group.submit.disabled, false);
    assert.equal(app.groups().formative.checkbox.checked, false);
});

test('report tabs preserve the search URL and synchronize the selected report type', () => {
    const app = setup();
    app.emit('click', '[data-report-tab]', app.tabs[1]);
    const url = new URL(app.window.location.href);
    assert.equal(url.searchParams.get('type'), 'summative');
    assert.equal(url.searchParams.get('search'), 'Quiz');
    assert.equal(app.input.value, 'summative');
    assert.equal(app.panels[0].hidden, true);
    assert.equal(app.panels[1].hidden, false);
    assert.equal(app.searches(), 1);
});

test('empty report selection cannot submit', () => {
    const app = setup();
    const form = { dataset: { reportSelectForm: 'formative' }, querySelectorAll: () => [] };
    assert.equal(app.emit('submit', '[data-report-select-form]', form).prevented, true);
});
