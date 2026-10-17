import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/user-availability.js', import.meta.url), 'utf8');

const setup = () => {
    const handlers = new Map();
    const timers = new Map();
    const calls = [];
    let nextTimer = 0;
    const form = {
        dataset: { userAvailabilityUrl: '/super-admin/users/check-availability' },
        fields: [],
        querySelector: () => ({ value: 'csrf-token' }),
        querySelectorAll() { return this.fields; },
    };
    const window = {
        setTimeout(callback, delay) { const id = ++nextTimer; timers.set(id, { callback, delay }); return id; },
        clearTimeout(id) { timers.delete(id); },
        portalFetch: async (url, options) => {
            calls.push({ url, options });
            return window.response(url, options);
        },
        response: async (_url, options) => ({ ok: true, json: async () => ({
            field: JSON.parse(options.body).field, available: true,
        }) }),
    };
    const document = {
        addEventListener(type, handler) {
            handlers.set(type, [...(handlers.get(type) || []), handler]);
        },
        createElement() { return { textContent: '', setAttribute() {} }; },
    };
    vm.runInNewContext(script, { window, document, AbortController });
    const addField = (name, owner = form) => {
        const attrs = new Map([['aria-describedby', 'existing-help']]);
        const field = {
            name, value: '', form: owner, disabled: false, isConnected: true, customValidity: '',
            validity: { typeMismatch: false },
            closest: () => field,
            insertAdjacentElement(_position, element) { this.feedback = element; },
            getAttribute: (name) => attrs.get(name) || null,
            setAttribute: (name, value) => attrs.set(name, value),
            removeAttribute: (name) => attrs.delete(name),
            setCustomValidity(value) { this.customValidity = value; },
        };
        owner.fields.push(field);
        return field;
    };
    const emit = (type, target) => (handlers.get(type) || []).forEach(handler => handler({ target }));
    const fire = (delay = 450) => {
        const [id, timer] = [...timers].find(([, timer]) => timer.delay === delay) || [];
        assert.ok(timer, `Expected a ${delay}ms timer`);
        timers.delete(id);
        return timer.callback();
    };
    const response = (field, available, message = 'Already in use.') => ({
        ok: true, json: async () => ({ field, available, message }),
    });
    return { window, form, calls, timers, addField, emit, fire, response };
};

test('typing debounces checks and sends only the current field and value with CSRF protection', async () => {
    const ctx = setup();
    const field = ctx.addField('email');
    field.value = 'first@example.test';
    ctx.emit('input', field);
    field.value = ' latest@example.test ';
    ctx.emit('input', field);
    assert.equal(ctx.timers.size, 1);
    assert.equal(ctx.calls.length, 0);
    await ctx.fire();
    assert.equal(ctx.calls.length, 1);
    const { url, options } = ctx.calls[0];
    assert.equal(url, '/super-admin/users/check-availability');
    assert.equal(options.method, 'POST');
    assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.deepEqual(JSON.parse(options.body), { field: 'email', value: 'latest@example.test' });
    assert.equal(field.feedback.textContent, 'No duplicate found.');
    assert.ok(field.getAttribute('aria-describedby').startsWith('existing-help '));
});

test('duplicate feedback blocks a known duplicate and clears immediately when corrected', async () => {
    const ctx = setup();
    const field = ctx.addField('student_number');
    field.value = 'STU-123';
    ctx.window.response = async () => ctx.response(field.name, false, 'The student number has already been taken.');
    ctx.emit('input', field);
    await ctx.fire();
    assert.ok(field.customValidity.includes('already been taken'));
    assert.equal(field.getAttribute('aria-invalid'), 'true');
    assert.equal(field.feedback.className, 'small mt-1 text-danger');
    ctx.emit('focusout', field);
    assert.equal(ctx.timers.size, 0);
    assert.ok(field.customValidity); // Blurring a confirmed duplicate must not reopen Save.
    field.value = 'STU-456';
    ctx.emit('input', field);
    assert.equal(field.customValidity, '');
    assert.equal(field.getAttribute('aria-invalid'), null);
    ctx.window.response = async () => ctx.response(field.name, true);
    await ctx.fire();
    assert.equal(field.feedback.className, 'small mt-1 text-success');
});

test('late responses cannot replace new input even if the user returns to the original value', async () => {
    const ctx = setup();
    const field = ctx.addField('employee_number');
    let resolveOld;
    ctx.window.response = () => new Promise(resolve => { resolveOld = resolve; });
    field.value = 'EMP-123';
    ctx.emit('input', field);
    const old = ctx.fire();
    field.value = 'EMP-456';
    ctx.emit('input', field);
    field.value = 'EMP-123';
    ctx.emit('input', field);
    assert.equal(ctx.calls[0].options.signal.aborted, true);
    resolveOld(ctx.response(field.name, false));
    await old;
    assert.equal(field.customValidity, '');
    assert.equal(field.feedback.textContent, 'Checking...');
    ctx.window.response = async () => ctx.response(field.name, true);
    await ctx.fire();
    assert.equal(field.feedback.textContent, 'No duplicate found.');
});

test('empty, disabled, malformed and excessive values do not trigger requests', () => {
    const ctx = setup();
    const field = ctx.addField('email');
    ctx.emit('input', field);
    field.value = 'invalid';
    field.validity.typeMismatch = true;
    ctx.emit('input', field);
    field.validity.typeMismatch = false;
    field.value = 'x'.repeat(256);
    ctx.emit('input', field);
    field.value = 'valid@example.test';
    field.disabled = true;
    ctx.emit('input', field);
    assert.equal(ctx.timers.size, 0);
    assert.equal(ctx.calls.length, 0);
});

test('blur checks immediately and delegated handlers also work on newly inserted edit forms', async () => {
    const ctx = setup();
    const editForm = { ...ctx.form, fields: [], dataset: { userAvailabilityUrl: '/admin-dean/users/public-id/check-availability' } };
    const field = ctx.addField('email', editForm);
    field.value = 'edited@example.test';
    ctx.emit('focusout', field);
    await ctx.fire(0);
    assert.equal(ctx.calls[0].url, editForm.dataset.userAvailabilityUrl);
    assert.equal(field.feedback.textContent, 'No duplicate found.');
});

test('network failures, throttling and unexpected HTML leave final Save validation available', async () => {
    for (const kind of ['network', '429', 'html', 'wrong-field']) {
        const ctx = setup();
        const field = ctx.addField('email');
        field.value = 'test@example.test';
        ctx.window.response = async () => {
            if (kind === 'network') throw new Error('Offline');
            if (kind === '429') return { ok: false };
            if (kind === 'html') return { ok: true, json: async () => { throw new Error('Unexpected HTML'); } };
            return ctx.response('student_number', true);
        };
        ctx.emit('input', field);
        await ctx.fire();
        assert.equal(field.customValidity, '');
        assert.equal(field.feedback.className, 'small mt-1 text-warning');
        assert.ok(field.feedback.textContent.includes('Save will validate'));
    }
});

test('a stalled check times out and allows the existing Save validation', async () => {
    const ctx = setup();
    const field = ctx.addField('email');
    field.value = 'test@example.test';
    ctx.window.response = (_url, options) => new Promise((_resolve, reject) => {
        options.signal.addEventListener('abort', () => reject(new Error('Aborted')));
    });
    ctx.emit('input', field);
    const pending = ctx.fire();
    ctx.fire(10000);
    await pending;
    assert.equal(field.customValidity, '');
    assert.equal(field.feedback.className, 'small mt-1 text-warning');
});

test('reset, modal closure and submission cancel checks and cannot show stale errors later', async () => {
    for (const event of ['reset', 'hidden.bs.modal', 'submit']) {
        const ctx = setup();
        const field = ctx.addField('student_number');
        let resolve;
        ctx.window.response = () => new Promise(done => { resolve = done; });
        field.value = 'STU-123';
        ctx.emit('input', field);
        const pending = ctx.fire();
        ctx.emit(event, ctx.form);
        assert.equal(ctx.calls[0].options.signal.aborted, true);
        resolve(ctx.response(field.name, false));
        await pending;
        assert.equal(field.customValidity, '');
        assert.equal(field.feedback.textContent, '');
    }
});

test('role switches clear disabled-field errors and detached pages cannot display old results', async () => {
    const ctx = setup();
    const field = ctx.addField('employee_number');
    field.value = 'EMP-123';
    ctx.window.response = async () => ctx.response(field.name, false);
    ctx.emit('input', field);
    await ctx.fire();
    field.disabled = true;
    ctx.emit('change', { form: ctx.form });
    assert.equal(field.customValidity, '');
    assert.equal(field.feedback.textContent, '');
    field.disabled = false;
    let resolve;
    ctx.window.response = () => new Promise(done => { resolve = done; });
    ctx.emit('input', field);
    const pending = ctx.fire();
    field.isConnected = false;
    resolve(ctx.response(field.name, false));
    await pending;
    assert.equal(field.customValidity, '');
});

test('server messages are rendered as text and unrelated forms send no checks', async () => {
    const ctx = setup();
    const field = ctx.addField('email');
    field.value = 'test@example.test';
    ctx.window.response = async () => ctx.response(field.name, false, '<img src=x onerror=alert(1)>');
    ctx.emit('input', field);
    await ctx.fire();
    assert.equal(field.feedback.textContent, '<img src=x onerror=alert(1)>');
    assert.equal(field.feedback.innerHTML, undefined);
    const other = ctx.addField('email', { ...ctx.form, fields: [], dataset: {} });
    other.value = 'other@example.test';
    ctx.emit('input', other);
    assert.equal(ctx.calls.length, 1);
    assert.equal(other.feedback, undefined);
});
