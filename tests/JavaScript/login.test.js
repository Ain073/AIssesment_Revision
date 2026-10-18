import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/login.js', import.meta.url), 'utf8');

function page(seconds = 0, ajax = false) {
    let now = 1_000_000;
    let timer;
    let requestTimeout;
    const requests = [];
    const navigations = [];
    const events = {};
    const nodes = {};
    function element(id) {
        const classes = new Set();
        return nodes[id] = {
            disabled: false, hidden: false, textContent: '', value: '', attributes: {},
            focus() { this.focused = true; },
            classList: {
                add: (...names) => names.forEach(name => classes.add(name)),
                remove: (...names) => names.forEach(name => classes.delete(name)),
                toggle: (name, value) => value ? classes.add(name) : classes.delete(name),
                contains: name => classes.has(name),
            },
            addEventListener: (name, handler) => { events[`${id}:${name}`] = handler; },
            setAttribute(name, value) { this.attributes[name] = value; },
        };
    }
    for (const id of ['loginForm', 'loginFields', 'loginButton', 'loginButtonLabel',
        'loginButtonIcon', 'loginLockoutMessage', 'loginCountdown', 'loginLockoutText', 'loginLockoutReady',
        'loginErrorMessage', 'togglePassword', 'password', 'email', 'remember', 'csrfField', 'csrfMeta']) element(id);
    nodes.loginForm.dataset = { loginRetryAfter: String(seconds) };
    nodes.loginLockoutMessage.hidden = seconds === 0;
    nodes.password.type = 'password';
    nodes.password.value = 'secret-password';
    nodes.email.value = 'student@example.test';
    nodes.csrfField.value = 'initial-token';
    nodes.loginErrorMessage.hidden = true;
    nodes.loginLockoutReady.textContent = 'You can try logging in again.';
    nodes.loginForm.action = 'https://app.example/login';
    nodes.loginForm.querySelector = () => nodes.csrfField;
    const toggleIcon = { textContent: 'visibility' };
    nodes.togglePassword.querySelector = () => toggleIcon;
    vm.runInNewContext(source, {
        Date: { now: () => now },
        URL,
        document: {
            hidden: false,
            getElementById: id => nodes[id],
            querySelector: () => nodes.csrfMeta,
            addEventListener: (name, handler) => { events[`document:${name}`] = handler; },
        },
        window: {
            ...(ajax ? {
                AbortController,
                FormData: class {
                    constructor() {
                        assert.equal(nodes.loginFields.disabled, false, 'Capture credentials before disabling fields');
                        this.values = new Map([['email', nodes.email.value], ['password', nodes.password.value], ['_token', nodes.csrfField.value], ['remember', '1']]);
                    }
                    get(name) { return this.values.get(name); }
                },
                fetch: (url, options) => new Promise((resolve, reject) => {
                    requests.push({ url, options, resolve, reject });
                    options.signal?.addEventListener('abort', () => reject(Object.assign(new Error('aborted'), { name: 'AbortError' })));
                }),
                setTimeout: handler => { requestTimeout = handler; return 2; },
                clearTimeout: () => { requestTimeout = undefined; },
            } : {}),
            location: { href: 'https://app.example/login', origin: 'https://app.example', assign: url => navigations.push(url) },
            setInterval: handler => { timer = handler; return 1; },
            clearInterval: () => { timer = undefined; },
            addEventListener: (name, handler) => { events[`window:${name}`] = handler; },
        },
    });
    return {
        nodes, toggleIcon, requests, navigations,
        timeout: () => requestTimeout?.(),
        settle: () => new Promise(resolve => setImmediate(resolve)),
        reply(status, data, contentType = 'application/json') {
            requests.at(-1).resolve({
                status, ok: status >= 200 && status < 300,
                headers: { get: name => name === 'Content-Type' ? contentType : String(data?.retry_after ?? '') },
                json: async () => data,
            });
        },
        elapse: seconds => { now += seconds * 1000; },
        tick: () => timer?.(),
        fire(name) {
            const event = { defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
            events[name](event);
            return event;
        },
    };
}

test('a blocked login disables the field group and submit button immediately', () => {
    const p = page(60);
    assert.equal(p.nodes.loginFields.disabled, true);
    assert.equal(p.nodes.loginButton.disabled, true);
    assert.equal(p.nodes.loginLockoutMessage.hidden, false);
    assert.equal(p.nodes.loginCountdown.textContent, '60');
    assert.equal(p.nodes.loginButtonLabel.textContent, 'Try again in 60s');
    assert.equal(p.fire('loginForm:submit').defaultPrevented, true);
    p.fire('togglePassword:click');
    assert.equal(p.nodes.password.type, 'password');
});

test('a delayed background timer uses elapsed time instead of counting ticks', () => {
    const p = page(60);
    p.elapse(43);
    p.tick();
    assert.equal(p.nodes.loginCountdown.textContent, '17');
    assert.equal(p.nodes.loginFields.disabled, true);
});

test('cooldown expiry automatically restores login and permits a normal submission', () => {
    const p = page(60);
    p.elapse(61);
    p.tick();
    assert.equal(p.nodes.loginFields.disabled, false);
    assert.equal(p.nodes.loginButton.disabled, false);
    assert.equal(p.nodes.loginButtonLabel.textContent, 'Log In');
    assert.equal(p.nodes.loginLockoutReady.hidden, false);
    assert.equal(p.nodes.loginLockoutText.hidden, true);
    assert.equal(p.fire('loginForm:submit').defaultPrevented, false);
    // Credentials must remain enabled so the browser includes them in the POST.
    assert.equal(p.nodes.loginFields.disabled, false);
    assert.equal(p.nodes.loginButton.disabled, true);
});

test('normal login prevents double submits without disabling credential fields', () => {
    const p = page();
    assert.equal(p.nodes.loginLockoutMessage.hidden, true);
    assert.equal(p.fire('loginForm:submit').defaultPrevented, false);
    assert.equal(p.nodes.loginButtonLabel.textContent, 'Authenticating...');
    assert.equal(p.nodes.loginFields.disabled, false);
    assert.equal(p.nodes.loginButton.disabled, true);
    assert.equal(p.fire('loginForm:submit').defaultPrevented, true);
});

test('returning through browser history restores the button without restarting a cooldown', () => {
    const blocked = page(60);
    blocked.elapse(61);
    blocked.fire('window:pageshow');
    assert.equal(blocked.nodes.loginFields.disabled, false);
    assert.equal(blocked.nodes.loginButton.disabled, false);
    const ordinary = page();
    ordinary.fire('loginForm:submit');
    ordinary.fire('window:pageshow');
    assert.equal(ordinary.nodes.loginButton.disabled, false);
    assert.equal(ordinary.nodes.loginButtonLabel.textContent, 'Log In');
});

test('password visibility still works when the form is available', () => {
    const p = page();
    p.fire('togglePassword:click');
    assert.equal(p.nodes.password.type, 'text');
    assert.equal(p.nodes.togglePassword.attributes['aria-label'], 'Hide password');
    p.fire('togglePassword:click');
    assert.equal(p.nodes.password.type, 'password');
    assert.equal(p.nodes.togglePassword.attributes['aria-label'], 'Show password');
});


test('AJAX captures credentials once, shows wrong-password errors in place, and permits correction', async () => {
    const p = page(0, true);
    assert.equal(p.fire('loginForm:submit').defaultPrevented, true);
    assert.equal(p.nodes.loginFields.disabled, true);
    assert.equal(p.nodes.loginButtonLabel.textContent, 'Authenticating...');
    p.fire('loginForm:submit');
    assert.equal(p.requests.length, 1);
    assert.equal(p.requests[0].options.body.get('password'), 'secret-password');
    assert.equal(p.requests[0].options.body.get('_token'), 'initial-token');
    assert.equal(p.requests[0].options.body.get('remember'), '1');
    assert.equal(p.requests[0].options.credentials, 'same-origin');
    assert.equal(p.requests[0].options.headers.Accept, 'application/json');
    p.reply(422, { errors: { email: ['Invalid email or password.'] } });
    await p.settle();
    assert.equal(p.nodes.loginErrorMessage.textContent, 'Invalid email or password.');
    assert.equal(p.nodes.loginErrorMessage.hidden, false);
    assert.equal(p.nodes.email.value, 'student@example.test');
    assert.equal(p.nodes.password.value, '');
    assert.equal(p.nodes.password.focused, true);
    assert.equal(p.nodes.loginFields.disabled, false);
    assert.equal(p.nodes.loginButton.disabled, false);
    assert.equal(p.navigations.length, 0);
    p.nodes.password.value = 'corrected-password';
    p.fire('loginForm:submit');
    assert.equal(p.requests.length, 2);
    assert.equal(p.requests[1].options.body.get('password'), 'corrected-password');
});

test('AJAX starts the server cooldown immediately and can render a second lockout after expiry', async () => {
    const p = page(0, true);
    p.fire('loginForm:submit');
    p.reply(429, { retry_after: 45 });
    await p.settle();
    assert.equal(p.nodes.loginCountdown.textContent, '45');
    assert.equal(p.nodes.loginFields.disabled, true);
    assert.equal(p.nodes.loginErrorMessage.hidden, true);
    p.fire('loginForm:submit');
    assert.equal(p.requests.length, 1);
    p.elapse(46);
    p.tick();
    assert.equal(p.nodes.loginFields.disabled, false);
    assert.equal(p.nodes.loginLockoutReady.hidden, false);
    p.fire('loginForm:submit');
    p.reply(429, { retry_after: 60 });
    await p.settle();
    assert.equal(p.nodes.loginLockoutText.hidden, false);
    assert.equal(p.nodes.loginLockoutReady.hidden, true);
    assert.equal(p.nodes.loginCountdown.textContent, '60');
    assert.equal(p.nodes.loginFields.disabled, true);
});

test('a rejected account refreshes CSRF state for the next attempt without reloading', async () => {
    const p = page(0, true);
    p.fire('loginForm:submit');
    p.reply(422, { errors: { email: ['Your account is inactive.'] }, csrf_token: 'rotated-token' });
    await p.settle();
    assert.equal(p.nodes.csrfField.value, 'rotated-token');
    assert.equal(p.nodes.csrfMeta.attributes.content, 'rotated-token');
    p.fire('loginForm:submit');
    assert.equal(p.requests[1].options.body.get('_token'), 'rotated-token');
    assert.equal(p.navigations.length, 0);
});

test('successful AJAX login navigates to the server-provided portal and keeps the form busy', async () => {
    const p = page(0, true);
    p.fire('loginForm:submit');
    p.reply(200, { redirect: '/student/dashboard' });
    await p.settle();
    assert.deepEqual(p.navigations, ['https://app.example/student/dashboard']);
    assert.equal(p.nodes.loginButton.disabled, true);
    assert.equal(p.nodes.loginFields.disabled, true);
});

test('unsafe redirects, expired sessions, and unexpected HTML never trigger automatic navigation', async () => {
    for (const response of [
        [200, { redirect: 'https://other.example' }],
        [200, { redirect: 'javascript:alert(1)' }],
        [419, {}],
        [500, {}, 'text/html'],
    ]) {
        const p = page(0, true);
        p.fire('loginForm:submit');
        p.reply(...response);
        await p.settle();
        assert.equal(p.nodes.loginErrorMessage.hidden, false);
        assert.equal(p.nodes.loginFields.disabled, false);
        assert.equal(p.nodes.loginButton.disabled, false);
        assert.equal(p.navigations.length, 0);
    }
});

test('network failures and timeouts restore the form without automatically retrying credentials', async () => {
    for (const timeout of [false, true]) {
        const p = page(0, true);
        p.fire('loginForm:submit');
        if (timeout) p.timeout();
        else p.requests[0].reject(new Error('offline'));
        await p.settle();
        assert.equal(p.nodes.loginErrorMessage.hidden, false);
        assert.equal(p.nodes.loginFields.disabled, false);
        assert.equal(p.nodes.loginButton.disabled, false);
        assert.equal(p.requests.length, 1);
        assert.equal(p.navigations.length, 0);
    }
});


test('a pageshow event cannot unlock a pending AJAX request and permit a duplicate submit', async () => {
    const p = page(0, true);
    p.fire('loginForm:submit');
    p.fire('window:pageshow');
    assert.equal(p.nodes.loginFields.disabled, true);
    assert.equal(p.fire('loginForm:submit').defaultPrevented, true);
    assert.equal(p.requests.length, 1);
    p.reply(422, { errors: { email: ['Invalid email or password.'] } });
    await p.settle();
    assert.equal(p.nodes.loginFields.disabled, false);
});

test('an already-expired server cooldown does not start another full minute of waiting', async () => {
    const p = page(0, true);
    p.fire('loginForm:submit');
    p.reply(429, { retry_after: 0 });
    await p.settle();
    assert.equal(p.nodes.loginFields.disabled, false);
    assert.equal(p.nodes.loginButton.disabled, false);
    assert.equal(p.nodes.loginLockoutReady.hidden, false);
});
