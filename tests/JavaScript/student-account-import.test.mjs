import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/student-account-import.js', import.meta.url), 'utf8');
const setup = () => {
    const forms = [], modals = [], shown = [], handlers = {};
    const window = { addEventListener(name, handler) { handlers[name] = handler; } };
    const document = {
        querySelectorAll: selector => selector === '[data-student-import-review-form]' ? forms : modals.filter(modal => modal.autoOpen),
        addEventListener(name, handler) { handlers[name] = handler; },
    };
    vm.runInNewContext(script, { window, document, bootstrap: { Modal: { getOrCreateInstance: modal => ({ show: () => shown.push(modal) }) } } });
    const addForm = (count = 1, readyCount = count, savedInput = '') => {
        const values = new Set(['d-none']);
        const pending = { classList: { remove: name => values.delete(name), contains: name => values.has(name) } };
        const create = { disabled: true, matches: selector => selector === '[data-import-create]' };
        const review = { disabled: true, focus() { this.focused = true; }, matches: () => false };
        const draft = { value: savedInput };
        const badges = [], messages = [];
        const rows = [];
        const form = {
            dataset: { readyCount: String(readyCount) }, closest: () => form,
            querySelectorAll: () => rows,
            querySelector(selector) {
                if (selector === '[name="draft_rows"]') return draft;
                if (selector === '[data-import-create]') return create;
                if (selector === '[data-import-review]') return review;
                if (selector === '[data-import-pending]') return pending;
                const index = Number(selector.match(/="(\d+)"/)?.[1]);
                return selector.includes('row-validation') ? badges[index] : messages[index];
            },
        };
        for (let index = 0; index < count; index++) {
            const row = { dataset: { importEditorRow: String(index) }, fields: [], querySelectorAll() { return this.fields; } };
            for (const [field, value] of Object.entries({ first_name: 'Ana', middle_name: '', last_name: 'Cruz', student_number: `23-SC-${4217 + index}`, email: `ana${index}@example.test`, program_id: '1' })) {
                const control = { dataset: { importField: field }, value,
                    closest: selector => selector === '[data-import-field]' ? control : selector === '[data-import-editor-row]' ? row : form };
                row.fields.push(control);
            }
            rows.push(row); badges.push({ textContent: 'Ready' }); messages.push({ textContent: '' });
        }
        forms.push(form);
        return { form, rows, draft, create, review, pending, badges };
    };
    const submit = (form, submitter) => {
        const event = { target: form, submitter, prevented: false, preventDefault() { this.prevented = true; } };
        handlers.submit(event);
        return event;
    };
    return { forms, modals, shown, handlers, window, addForm, submit };
};

test('editable import initializes after AJAX navigation and auto-opens only once', () => {
    const env = setup();
    const editor = env.addForm();
    const modal = { autoOpen: true, removeAttribute() { this.autoOpen = false; } };
    env.modals.push(modal);
    env.window.initializeStudentAccountImports();
    env.window.initializeStudentAccountImports();
    assert.equal(editor.create.disabled, false);
    assert.equal(editor.review.disabled, false);
    assert.equal(JSON.parse(editor.draft.value)[0].student_number, '23-SC-4217');
    assert.deepEqual(env.shown, [modal]);
});

test('an edit blocks account creation and marks only the changed row for validation', () => {
    const env = setup(), editor = env.addForm(2);
    env.window.initializeStudentAccountImports();
    const field = editor.rows[1].fields[0];
    field.value = 'Corrected';
    env.handlers.input({ target: field });
    assert.equal(editor.create.disabled, true);
    assert.equal(editor.pending.classList.contains('d-none'), false);
    assert.equal(editor.badges[0].textContent, 'Ready');
    assert.equal(editor.badges[1].textContent, 'Needs validation');
    assert.equal(env.submit(editor.form, editor.create).prevented, true);
    assert.equal(editor.review.focused, true);
});

test('Save and Validate serializes current values including quotes without submitting Create', () => {
    const env = setup(), editor = env.addForm();
    env.window.initializeStudentAccountImports();
    const field = editor.rows[0].fields[0];
    field.value = 'Ana "A" <Student>';
    env.handlers.change({ target: field });
    assert.equal(env.submit(editor.form, editor.review).prevented, false);
    assert.equal(JSON.parse(editor.draft.value)[0].first_name, 'Ana "A" <Student>');
    assert.equal(editor.create.disabled, true);
});

test('unsaved values survive a server validation redirect and still require revalidation', () => {
    const env = setup(), editor = env.addForm(1, 1, JSON.stringify([{ first_name: 'Unsaved correction', program_id: 2 }]));
    env.window.initializeStudentAccountImports();
    assert.equal(editor.rows[0].fields[0].value, 'Unsaved correction');
    assert.equal(editor.rows[0].fields[5].value, '2');
    assert.equal(editor.create.disabled, true);
    assert.equal(editor.review.disabled, false);
});

test('invalid previews remain editable with Create disabled', () => {
    const env = setup(), editor = env.addForm(1, 0);
    env.window.initializeStudentAccountImports();
    assert.equal(editor.create.disabled, true);
    assert.equal(editor.review.disabled, false);
});

test('all 200 records are serialized in one JSON field', () => {
    const env = setup(), editor = env.addForm(200);
    env.window.initializeStudentAccountImports();
    editor.rows[199].fields[0].value = 'Last correction';
    env.submit(editor.form, editor.review);
    const payload = JSON.parse(editor.draft.value);
    assert.equal(payload.length, 200);
    assert.equal(payload[199].first_name, 'Last correction');
    assert.equal(payload[0].student_number, '23-SC-4217');
});

test('double submission is blocked and browser Back restores the ability to submit', () => {
    const env = setup(), editor = env.addForm();
    env.window.initializeStudentAccountImports();
    assert.equal(env.submit(editor.form, editor.review).prevented, false);
    assert.equal(env.submit(editor.form, editor.review).prevented, true);
    env.handlers.pageshow();
    assert.equal(env.submit(editor.form, editor.review).prevented, false);
});
