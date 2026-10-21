import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/class-roster-import.js', import.meta.url), 'utf8');
const classList = () => {
    const values = new Set();
    return { toggle: (name, on) => on ? values.add(name) : values.delete(name), contains: name => values.has(name) };
};
const setup = () => {
    const forms = [], modals = [], shown = [], handlers = {};
    const window = { addEventListener(name, handler) { handlers[name] = handler; } };
    const document = { addEventListener(name, handler) { handlers[name] = handler; },
        querySelectorAll: selector => selector === '[data-class-roster-import]' ? forms : modals.filter(modal => modal.autoOpen) };
    vm.runInNewContext(script, { window, document, bootstrap: { Modal: { getOrCreateInstance: modal => ({ show: () => shown.push(modal) }) } } });
    const addForm = (statuses, excluded = []) => {
        const counters = Object.fromEntries(['ready', 'enrolled', 'review', 'excluded', 'confirm'].map(name => [`[data-roster-${name}-count]`, { textContent: '' }]));
        const confirm = { disabled: false };
        const rows = [];
        const form = { dataset: {}, querySelectorAll: () => rows, querySelector: selector => selector === '[data-roster-confirm]' ? confirm : counters[selector], closest: () => form };
        for (const [index, status] of statuses.entries()) {
            const exclusion = { disabled: !excluded.includes(index) };
            const badge = { dataset: {}, textContent: status, className: `badge ${status}` };
            const label = { textContent: '' }, icon = { textContent: '' };
            const button = { disabled: true, classList: classList(), setAttribute(name, value) { this[name] = value; },
                closest: selector => selector === '[data-roster-toggle]' ? button : selector === '[data-roster-row]' ? row : form };
            const row = { dataset: { rosterStatus: status }, classList: classList(),
                querySelector: selector => ({ '[data-roster-exclusion]': exclusion, '[data-roster-status-badge]': badge,
                    '[data-roster-toggle]': button, '[data-roster-toggle-label]': label, '[data-roster-toggle-icon]': icon,
                    '.class-import-identity': { textContent: `23-SC-${4217 + index}` } })[selector], exclusion, badge, button, label, icon };
            rows.push(row);
        }
        forms.push(form);
        return { form, rows, confirm, count: name => counters[`[data-roster-${name}-count]`].textContent };
    };
    const submit = form => {
        const event = { target: form, prevented: false, preventDefault() { this.prevented = true; } };
        handlers.submit(event);
        return event;
    };
    return { forms, modals, shown, handlers, window, addForm, submit, click: row => handlers.click({ target: row.button }) };
};

test('class import preview initializes after AJAX navigation and auto-opens once', () => {
    const env = setup(), preview = env.addForm(['ready', 'ready', 'not_found']);
    env.modals.push({ autoOpen: true, removeAttribute() { this.autoOpen = false; } });
    env.window.initializeClassRosterImports();
    env.window.initializeClassRosterImports();
    assert.equal(preview.count('ready'), '2');
    assert.equal(preview.count('review'), '1');
    assert.equal(preview.rows[0].button.disabled, false);
    assert.equal(env.shown.length, 1);
});

test('Remove excludes the row from submission and updates the Ready count', () => {
    const env = setup(), preview = env.addForm(['ready', 'ready']);
    env.window.initializeClassRosterImports();
    env.click(preview.rows[1]);
    assert.equal(preview.rows[1].exclusion.disabled, false);
    assert.equal(preview.rows[1].classList.contains('class-import-excluded'), true);
    assert.equal(preview.rows[1].label.textContent, 'Undo');
    assert.equal(preview.rows[1].badge.textContent, 'Removed from import');
    assert.equal(preview.count('ready'), '1');
    assert.equal(preview.count('confirm'), '1');
    assert.equal(preview.count('excluded'), '1');
    assert.equal(preview.form.dataset.rosterSubmitting, undefined);
});

test('Undo restores the original status and leaves no exclusion to submit', () => {
    const env = setup(), preview = env.addForm(['ready']);
    env.window.initializeClassRosterImports();
    env.click(preview.rows[0]);
    env.click(preview.rows[0]);
    assert.equal(preview.rows[0].exclusion.disabled, true);
    assert.equal(preview.rows[0].badge.textContent, 'ready');
    assert.equal(preview.rows[0].badge.className, 'badge ready');
    assert.equal(preview.count('ready'), '1');
    assert.equal(preview.count('excluded'), '0');
    assert.equal(preview.confirm.disabled, false);
});

test('removing all Ready students blocks confirmation until a Ready row is restored', () => {
    const env = setup(), preview = env.addForm(['ready', 'inactive']);
    env.window.initializeClassRosterImports();
    env.click(preview.rows[0]);
    assert.equal(preview.confirm.disabled, true);
    assert.equal(env.submit(preview.form).prevented, true);
    env.click(preview.rows[1]);
    env.click(preview.rows[1]);
    assert.equal(preview.confirm.disabled, true);
    env.click(preview.rows[0]);
    assert.equal(preview.confirm.disabled, false);
});

test('non-ready removals and Undo never make invalid or already-enrolled students Ready', () => {
    const env = setup(), preview = env.addForm(['ready', 'already_enrolled', 'inactive', 'not_found']);
    env.window.initializeClassRosterImports();
    for (const row of preview.rows.slice(1)) env.click(row);
    assert.equal(preview.count('ready'), '1');
    assert.equal(preview.count('enrolled'), '0');
    assert.equal(preview.count('review'), '0');
    for (const row of preview.rows.slice(1)) env.click(row);
    assert.equal(preview.count('ready'), '1');
    assert.equal(preview.count('enrolled'), '1');
    assert.equal(preview.count('review'), '2');
});

test('validation redirects restore exclusions and reinitialization preserves current removals', () => {
    const env = setup(), preview = env.addForm(['ready', 'ready'], [1]);
    env.window.initializeClassRosterImports();
    env.window.initializeClassRosterImports();
    assert.equal(preview.count('ready'), '1');
    assert.equal(preview.count('excluded'), '1');
    assert.equal(preview.rows[1].label.textContent, 'Undo');
});

test('double confirmation and edits while submitting are blocked; browser Back restores the controls', () => {
    const env = setup(), preview = env.addForm(['ready', 'ready']);
    env.window.initializeClassRosterImports();
    env.click(preview.rows[1]);
    assert.equal(env.submit(preview.form).prevented, false);
    assert.equal(env.submit(preview.form).prevented, true);
    env.click(preview.rows[1]);
    assert.equal(preview.rows[1].exclusion.disabled, false);
    env.handlers.pageshow();
    assert.equal(preview.rows[1].button.disabled, false);
    env.click(preview.rows[1]);
    assert.equal(preview.rows[1].exclusion.disabled, true);
});
