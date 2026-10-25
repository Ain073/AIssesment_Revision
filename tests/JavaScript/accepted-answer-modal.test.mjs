import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/accepted-answer-modal.js', import.meta.url), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));
const attrName = name => name.replace(/[A-Z]/g, value => `-${value.toLowerCase()}`);
class Element {
    constructor(tag = 'div', data = {}) {
        this.tagName = tag.toUpperCase(); this.dataset = data; this.children = []; this.value = '';
        this.disabled = false; this.isConnected = true; this.className = ''; this._text = '';
        this.classList = {
            add: name => { if (!this.classList.contains(name)) this.className += ` ${name}`; },
            remove: name => { this.className = this.className.split(/\s+/).filter(value => value !== name).join(' '); },
            contains: name => this.className.split(/\s+/).includes(name),
        };
    }
    matches(selector) {
        return selector.split(',').some(part => Object.keys(this.dataset).some(key => part.trim() === `[data-${attrName(key)}]`));
    }
    querySelectorAll(selector) {
        return this.children.flatMap(child => [...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)]);
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
    closest(selector) { return this.matches(selector) ? this : this.parent?.closest(selector) || null; }
    append(...nodes) { nodes.forEach(node => { node.parent = this; this.children.push(node); }); }
    replaceChildren(...nodes) { this.children = []; this._text = ''; this.append(...nodes); }
    set textContent(value) { this._text = String(value); this.children = []; }
    get textContent() { return this._text + this.children.map(child => child.textContent).join(''); }
    set innerHTML(_) { throw new Error('Untrusted content must be rendered as text.'); }
}
const itemData = (key = 'item-a') => ({ item_key: key, question: `Question ${key}`, points: 1,
    accepted_answers: ['Humanistic psychologists'], preview_url: `/${key}/preview`, store_url: `/${key}/save` });
const previewData = { alternative: 'Humanistic psychologist', students_count: 1, attempts_count: 1, preview_token: 'signed-preview',
    changes: [{ student_name: '<img src=x onerror=alert(1)>', student_number: 'STU-1', attempt_number: 1, answer_text: 'Humanistic psychologist', before: 0, after: 1 }] };
const savedData = { item_key: 'item-a', accepted_answers: ['Humanistic psychologists', 'Humanistic psychologist'], message: 'Alternative saved.' };
const setup = () => {
    const root = new Element(), handlers = {}, calls = [], responses = [];
    const document = {
        querySelectorAll: selector => root.querySelectorAll(selector), querySelector: selector => root.querySelector(selector),
        createElement: tag => new Element(tag), addEventListener: (name, handler) => { handlers[name] = handler; },
    };
    const env = { root, document, calls, responses, window: { location: { href: 'https://example.test/grade' } } };
    const response = (data, status = 200, type = 'application/json') => ({ ok: status < 400, status, redirected: false,
        headers: { get: () => type }, json: async () => data, text: async () => typeof data === 'string' ? data : '' });
    env.reply = (data, status, type) => responses.push(() => response(data, status, type));
    env.defer = () => {
        let resolve;
        const promise = new Promise(done => { resolve = done; });
        responses.push(() => promise);
        return data => resolve(response(data));
    };
    class FormData {
        constructor(form) { this.values = Object.fromEntries(form.controls.map(control => [control.name, control.value])); }
        get(name) { return this.values[name]; }
    }
    const fetch = async (url, options) => {
        calls.push({ url, ...options });
        assert.ok(responses.length, `Unexpected request to ${url}`);
        return responses.shift()();
    };
    vm.runInNewContext(source, { document, window: env.window, fetch, FormData, AbortController, setTimeout, clearTimeout,
        DOMParser: class { parseFromString() { return env.fresh; } } });
    env.event = (name, target, extra = {}) => {
        const event = { target, prevented: false, preventDefault() { this.prevented = true; }, ...extra };
        handlers[name]?.(event);
        return event;
    };
    env.addModal = () => {
        const modal = new Element('div', { acceptedAnswerModal: '' }), fields = {};
        const names = ['message', 'input', 'token', 'preview', 'content', 'loading', 'question', 'accepted', 'previewButton', 'saveButton', 'summary', 'changes', 'close'];
        names.forEach(name => {
            const key = `alternative${name.charAt(0).toUpperCase()}${name.slice(1)}`;
            fields[name] = new Element('div', { [key]: '' });
            modal.append(fields[name]);
        });
        const previewForm = new Element('form', { alternativePreviewForm: '' });
        const saveForm = new Element('form', { alternativeSaveForm: '' });
        fields.input.name = 'alternative_answer'; fields.token.name = 'preview_token';
        const csrf = { name: '_token', value: 'csrf-token' };
        previewForm.controls = [fields.input, csrf]; saveForm.controls = [fields.token, csrf];
        modal.append(previewForm, saveForm); root.append(modal);
        return { modal, fields, previewForm, saveForm };
    };
    env.open = (ui, key = 'item-a') => env.event('show.bs.modal', ui.modal, { relatedTarget: { dataset: { acceptedAnswerUrl: `/${key}` } } });
    env.preview = async (ui) => {
        ui.fields.input.value = 'Humanistic psychologist'; env.event('input', ui.fields.input);
        env.reply(previewData); env.event('submit', ui.previewForm); await tick();
    };
    return env;
};
const loaded = async () => {
    const env = setup(), ui = env.addModal();
    env.reply(itemData()); env.open(ui); await tick();
    return { env, ui };
};

test('a modal inserted after AJAX navigation loads the selected question without navigating', async () => {
    const { env, ui } = await loaded();
    assert.equal(ui.fields.question.textContent, 'Question item-a');
    assert.equal(ui.fields.accepted.textContent, 'Humanistic psychologists');
    assert.equal(ui.fields.content.classList.contains('d-none'), false);
    assert.equal(env.calls[0].headers.Accept, 'application/json');
    assert.equal(env.window.location.href, 'https://example.test/grade');
});

test('preview and save stay in the modal, send the reviewed token and CSRF, and update the question list', async () => {
    const { env, ui } = await loaded();
    const list = new Element('ul', { acceptedAnswerList: 'item-a' }); env.root.append(list);
    await env.preview(ui);
    assert.equal(ui.fields.saveButton.disabled, false);
    assert.match(ui.fields.changes.textContent, /<img src=x onerror=alert\(1\)>/);
    env.reply(savedData); const event = env.event('submit', ui.saveForm); await tick();
    assert.equal(event.prevented, true);
    assert.equal(env.calls.at(-1).body.get('preview_token'), 'signed-preview');
    assert.equal(env.calls.at(-1).body.get('_token'), 'csrf-token');
    assert.equal(env.calls.at(-1).credentials, 'same-origin');
    assert.match(list.textContent, /Humanistic psychologistAlternative/);
    assert.equal(ui.fields.input.value, '');
    assert.equal(ui.fields.saveButton.disabled, true);
    assert.equal(ui.fields.message.textContent, 'Alternative saved.');
    assert.equal(env.window.location.href, 'https://example.test/grade');
});

test('editing an alternative invalidates both an existing preview and a late preview response', async () => {
    const { env, ui } = await loaded();
    await env.preview(ui);
    ui.fields.input.value = 'Changed answer'; env.event('input', ui.fields.input);
    assert.equal(ui.fields.token.value, ''); assert.equal(ui.fields.saveButton.disabled, true);
    const resolve = env.defer(); env.event('submit', ui.previewForm);
    ui.fields.input.value = 'Another answer'; env.event('input', ui.fields.input);
    resolve(previewData); await tick();
    assert.equal(ui.fields.token.value, ''); assert.equal(ui.fields.preview.classList.contains('d-none'), true);
    env.event('submit', ui.saveForm); await tick();
    assert.equal(env.calls.length, 3);
});

test('validation and stale-preview errors remain in the modal and preserve the typed answer', async () => {
    const { env, ui } = await loaded();
    await env.preview(ui);
    env.reply({ errors: { preview_token: ['Preview the alternative again.'] } }, 422);
    env.event('submit', ui.saveForm); await tick();
    assert.equal(ui.fields.message.textContent, 'Preview the alternative again.');
    assert.equal(ui.fields.input.value, 'Humanistic psychologist');
    assert.equal(ui.fields.token.value, ''); assert.equal(ui.fields.saveButton.disabled, true);
    assert.equal(env.window.location.href, 'https://example.test/grade');
});

test('duplicate saves and closing while a save is pending are blocked', async () => {
    const { env, ui } = await loaded();
    await env.preview(ui);
    const resolve = env.defer(); env.event('submit', ui.saveForm); env.event('submit', ui.saveForm);
    assert.equal(env.event('hide.bs.modal', ui.modal).prevented, true);
    assert.equal(ui.fields.close.disabled, true); assert.equal(env.calls.length, 3);
    resolve(savedData); await tick();
    assert.equal(env.event('hide.bs.modal', ui.modal).prevented, false);
    assert.equal(ui.fields.close.disabled, false);
});

test('closing and opening another question cannot display the previous question response', async () => {
    const env = setup(), ui = env.addModal(), resolve = env.defer();
    env.open(ui); env.event('hidden.bs.modal', ui.modal);
    env.reply(itemData('item-b')); env.open(ui, 'item-b'); await tick();
    resolve(itemData()); await tick();
    assert.equal(ui.fields.question.textContent, 'Question item-b');
    env.reply(previewData); ui.fields.input.value = 'Humanistic psychologist';
    env.event('submit', ui.previewForm); await tick();
    assert.equal(env.calls.at(-1).url, '/item-b/preview');
});

test('an expired-session HTML response never appears as a successful save', async () => {
    const { env, ui } = await loaded();
    await env.preview(ui);
    env.reply('<html>Sign in</html>', 200, 'text/html'); env.event('submit', ui.saveForm); await tick();
    assert.match(ui.fields.message.textContent, /Unexpected response/);
    assert.equal(ui.fields.message.className.includes('alert-success'), false);
    assert.equal(ui.fields.token.value, '');
});

const addGrade = root => {
    const summary = new Element('p', { gradingScoreSummary: '' }), row = new Element('article', { gradingItem: 'item-a' });
    const score = new Element('span', { gradingItemScore: '' }), answer = new Element('div', { gradingStudentAnswer: '' });
    row.append(score, answer); root.append(summary, row);
    return { summary, row, score, answer };
};

test('saving refreshes grade text and colors while preserving unsaved essay inputs and the current page', async () => {
    const { env, ui } = await loaded(), old = addGrade(env.root);
    const essay = new Element('textarea'); essay.value = 'Unsaved essay feedback'; env.root.append(essay);
    old.summary.textContent = '0 / 1'; old.score.textContent = '0 / 1'; old.answer.className = 'answer-box-wrong';
    env.fresh = new Element(); const fresh = addGrade(env.fresh);
    fresh.summary.textContent = '1 / 1'; fresh.score.textContent = '1 / 1'; fresh.answer.className = 'answer-box-correct';
    await env.preview(ui);
    env.reply(savedData); env.reply('<html>Fresh grade page</html>'); env.event('submit', ui.saveForm); await tick();
    assert.equal(old.summary.textContent, '1 / 1'); assert.equal(old.score.textContent, '1 / 1');
    assert.equal(old.answer.className, 'answer-box-correct');
    assert.equal(essay.value, 'Unsaved essay feedback');
    assert.equal(env.window.location.href, 'https://example.test/grade');
});

test('a failed score refresh reports that the alternative was saved without retrying the save', async () => {
    const { env, ui } = await loaded(); addGrade(env.root);
    await env.preview(ui);
    env.reply(savedData); env.reply('', 500); env.event('submit', ui.saveForm); await tick();
    assert.match(ui.fields.message.textContent, /alternative was saved; reload/);
    assert.equal(ui.fields.message.className.includes('alert-success'), true);
    assert.equal(env.calls.filter(call => call.url === '/item-a/save').length, 1);
});

test('unrelated forms retain their normal submission behavior', () => {
    const env = setup();
    assert.equal(env.event('submit', new Element('form')).prevented, false);
});
