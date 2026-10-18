import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/login.js', import.meta.url), 'utf8');

function page(seconds = 0) {
    let now = 1_000_000;
    let timer;
    const events = {};
    const nodes = {};
    function element(id) {
        const classes = new Set();
        return nodes[id] = {
            disabled: false, hidden: false, textContent: '', attributes: {},
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
        'loginButtonIcon', 'loginLockoutMessage', 'loginCountdown', 'togglePassword', 'password']) element(id);
    nodes.loginForm.dataset = { loginRetryAfter: String(seconds) };
    nodes.loginLockoutMessage.hidden = seconds === 0;
    nodes.password.type = 'password';
    const toggleIcon = { textContent: 'visibility' };
    nodes.togglePassword.querySelector = () => toggleIcon;
    vm.runInNewContext(source, {
        Date: { now: () => now },
        document: {
            hidden: false,
            getElementById: id => nodes[id],
            addEventListener: (name, handler) => { events[`document:${name}`] = handler; },
        },
        window: {
            setInterval: handler => { timer = handler; return 1; },
            clearInterval: () => { timer = undefined; },
            addEventListener: (name, handler) => { events[`window:${name}`] = handler; },
        },
    });
    return {
        nodes, toggleIcon,
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
    assert.equal(p.nodes.loginLockoutMessage.textContent, 'You can try logging in again.');
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
