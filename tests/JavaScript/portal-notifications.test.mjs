import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/portal-notifications.js', import.meta.url), 'utf8');
const flush = () => new Promise(setImmediate);
const response = (count = 2, extra = {}) => ({
    ok: true, status: 200, redirected: false,
    headers: { get: () => 'application/json' },
    json: async () => ({ unread_count: count, html: `menu-${count}`, ...extra }),
});

const context = () => {
    const events = {};
    const windowEvents = {};
    const timers = new Map();
    const requests = [];
    const messages = [];
    const navigations = [];
    let timerId = 0;
    const badge = { hidden: false, textContent: '2' };
    const menu = { innerHTML: 'initial-menu', contains: () => false, querySelectorAll: () => [] };
    const dropdown = { setAttribute() {} };
    const root = {
        dataset: { notificationsUrl: 'http://localhost/notifications' },
        querySelector(selector) {
            if (selector.includes('count')) return badge;
            if (selector.includes('menu')) return menu;
            return dropdown;
        },
    };
    const document = {
        hidden: false, activeElement: null,
        querySelector: () => root,
        addEventListener(name, callback) { events[name] = callback; },
    };
    const window = {
        location: { href: 'http://localhost/profile', origin: 'http://localhost', assign: (url) => navigations.push(url) },
        setTimeout(callback, delay) { const id = ++timerId; timers.set(id, { callback, delay }); return id; },
        clearTimeout(id) { timers.delete(id); },
        addEventListener(name, callback) { windowEvents[name] = callback; },
        showPortalMessage: (...args) => messages.push(args),
        navigatePortalPage: async (url) => navigations.push(url),
    };
    vm.runInNewContext(script, {
        window, document, AbortController, URL,
        localStorage: { setItem() {} },
        FormData: class { constructor(form) { this.token = form.token; } },
        fetch: (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject })),
    });
    const submit = (action = 'http://localhost/notifications/read-all') => {
        const button = { disabled: false };
        const form = { action, token: 'csrf-token', querySelectorAll: () => [button] };
        const event = { target: { closest: () => form }, preventDefault() { this.prevented = true; } };
        return { promise: events.submit(event), button, event };
    };
    return { events, windowEvents, timers, requests, messages, navigations, badge, menu, document, window, submit };
};

test('polls every 30 seconds, suppresses overlaps, and pauses while the document is hidden', async () => {
    const ctx = context();
    assert.equal([...ctx.timers.values()][0].delay, 30000);
    const pending = ctx.window.refreshPortalNotifications();
    await ctx.window.refreshPortalNotifications();
    assert.equal(ctx.requests.length, 1);
    assert.equal(ctx.requests[0].options.cache, 'no-store');
    ctx.document.hidden = true;
    ctx.events.visibilitychange();
    assert.equal(ctx.requests[0].options.signal.aborted, true);
    ctx.requests[0].resolve(response(9));
    await pending;
    assert.equal(ctx.badge.textContent, '2');
    assert.equal(ctx.timers.size, 0);
    ctx.document.hidden = false;
    ctx.events.visibilitychange();
    ctx.requests[1].resolve(response(12));
    await flush();
    assert.equal(ctx.badge.textContent, '9+');
    assert.equal(ctx.menu.innerHTML, 'menu-12');
    assert.equal([...ctx.timers.values()][0].delay, 30000);
});

test('mark all read sends CSRF form data and stale poll responses cannot restore the unread count', async () => {
    const ctx = context();
    const poll = ctx.window.refreshPortalNotifications();
    const submission = ctx.submit();
    assert.equal(submission.event.prevented, true);
    assert.equal(submission.button.disabled, true);
    assert.equal(ctx.requests[0].options.signal.aborted, true);
    assert.equal(ctx.requests[1].options.method, 'POST');
    assert.equal(ctx.requests[1].options.body.token, 'csrf-token');
    const duplicate = ctx.submit();
    await duplicate.promise;
    assert.equal(ctx.requests.length, 2);
    ctx.requests[1].resolve(response(0, { message: 'Notifications marked as read.' }));
    await submission.promise;
    ctx.requests[0].resolve(response(8));
    await poll;
    assert.equal(ctx.badge.hidden, true);
    assert.equal(ctx.badge.textContent, '0');
    assert.equal(submission.button.disabled, false);
    assert.equal(ctx.messages[0][0], 'Notifications marked as read.');
});

test('reading a notification navigates through AJAX after updating its count', async () => {
    const ctx = context();
    const submission = ctx.submit('http://localhost/notifications/uuid/read');
    ctx.requests[0].resolve(response(1, { url: 'http://localhost/student/assessments' }));
    await submission.promise;
    assert.equal(ctx.badge.textContent, '1');
    assert.deepEqual(ctx.navigations, ['http://localhost/student/assessments']);
});

test('reading a notification does not interrupt a page the user navigated to during the request', async () => {
    const ctx = context();
    const submission = ctx.submit('http://localhost/notifications/uuid/read');
    ctx.window.location.href = 'http://localhost/student/classes';
    ctx.requests[0].resolve(response(1, { url: 'http://localhost/student/assessments' }));
    await submission.promise;
    assert.equal(ctx.navigations.length, 0);
});

test('expired sessions stop polling and retain the last displayed notifications', async () => {
    const ctx = context();
    const pending = ctx.window.refreshPortalNotifications();
    ctx.requests[0].resolve({ ok: true, status: 200, redirected: true, headers: { get: () => 'text/html' } });
    await pending;
    assert.equal(ctx.menu.innerHTML, 'initial-menu');
    assert.equal(ctx.timers.size, 0);
    await ctx.window.refreshPortalNotifications();
    assert.equal(ctx.requests.length, 1);
});

test('failed read requests restore controls and polling without marking items read locally', async () => {
    const ctx = context();
    const submission = ctx.submit();
    ctx.requests[0].reject(new Error('Connection lost'));
    await submission.promise;
    assert.equal(submission.button.disabled, false);
    assert.equal(ctx.badge.textContent, '2');
    assert.equal(ctx.messages[0][1], 'warning');
    assert.equal([...ctx.timers.values()][0].delay, 30000);
});

test('read updates in another tab trigger one refresh', async () => {
    const ctx = context();
    ctx.windowEvents.storage({ key: 'unrelated' });
    assert.equal(ctx.requests.length, 0);
    ctx.windowEvents.storage({ key: 'portal-notifications-updated' });
    ctx.requests[0].resolve(response(0));
    await flush();
    assert.equal(ctx.badge.hidden, true);
});

test('a read update from another tab cancels a pending stale poll', async () => {
    const ctx = context();
    const pending = ctx.window.refreshPortalNotifications();
    ctx.windowEvents.storage({ key: 'portal-notifications-updated' });
    assert.equal(ctx.requests[0].options.signal.aborted, true);
    ctx.requests[1].resolve(response(0));
    await flush();
    ctx.requests[0].resolve(response(6));
    await pending;
    assert.equal(ctx.badge.hidden, true);
});

test('restoring a page from browser history restarts notification polling', async () => {
    const ctx = context();
    ctx.windowEvents.pagehide();
    assert.equal(ctx.timers.size, 0);
    ctx.windowEvents.pageshow({ persisted: true });
    ctx.requests[0].resolve(response(3));
    await flush();
    assert.equal(ctx.badge.textContent, '3');
    assert.equal([...ctx.timers.values()][0].delay, 30000);
});

test('ordinary permission errors keep polling and do not masquerade as an expired session', async () => {
    const ctx = context();
    const submission = ctx.submit();
    ctx.requests[0].resolve({ ok: false, status: 403, headers: { get: () => 'application/json' }, json: async () => ({ message: 'Forbidden.' }) });
    await submission.promise;
    assert.equal(ctx.messages[0][0], 'Forbidden.');
    assert.equal([...ctx.timers.values()][0].delay, 30000);
});

test('notification polling stops after another feature detects authentication loss', async () => {
    const ctx = context();
    ctx.window.portalAuthenticationRequired = true;
    await ctx.window.refreshPortalNotifications();
    assert.equal(ctx.requests.length, 0);
});
