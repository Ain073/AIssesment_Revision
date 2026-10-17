import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/portal-session.js', import.meta.url), 'utf8');
const context = (response) => {
    const input = { value: 'Unsaved assessment answer' };
    const elements = [];
    const element = () => ({ dataset: {}, style: {}, setAttribute() {}, replaceChildren(...children) { this.children = children; } });
    const document = {
        querySelector: () => elements.find((item) => item.dataset.portalSessionNotice),
        createElement: element,
        body: { dataset: { portalLoginUrl: '/login', passwordSetupUrl: '/password/setup' }, appendChild: (item) => elements.push(item) },
    };
    const window = { location: { href: 'http://localhost/student/assessments/take' } };
    vm.runInNewContext(script, { window, document, fetch: async () => response });
    return { window, elements, input };
};
const json = (status, data) => new Response(JSON.stringify(data), { status, headers: { 'content-type': 'application/json' } });

test('expired sessions show a notice without consuming the response or replacing unsaved inputs', async () => {
    const ctx = context(json(401, { message: 'Your session has ended.', code: 'authentication_required' }));
    const result = await ctx.window.portalFetch('/notifications');
    assert.equal((await result.json()).code, 'authentication_required');
    assert.equal(ctx.window.portalAuthenticationRequired, true);
    assert.equal(ctx.input.value, 'Unsaved assessment answer');
    assert.equal(ctx.window.location.href, 'http://localhost/student/assessments/take');
    assert.equal(ctx.elements[0].children[1].href, '/login');
});

test('inactive accounts show the reason and safely display messages as text', async () => {
    const message = '<script>alert(1)</script> Account inactive.';
    const ctx = context(json(403, { message, code: 'account_inactive' }));
    await ctx.window.portalFetch('/profile');
    assert.equal(ctx.elements[0].children[0].textContent, message);
    assert.equal(ctx.elements[0].children[0].innerHTML, undefined);
    assert.equal(ctx.window.portalAuthenticationRequired, true);
});

test('ordinary permission errors do not mark the session expired', async () => {
    const ctx = context(json(403, { message: 'You cannot manage this record.' }));
    const result = await ctx.window.portalFetch('/users/other');
    assert.equal(result.status, 403);
    assert.equal(ctx.window.portalAuthenticationRequired, undefined);
    assert.equal(ctx.elements.length, 0);
});

test('an HTML CSRF error shows a notice without breaking callers', async () => {
    const ctx = context(new Response('<html>Page expired</html>', { status: 419, headers: { 'content-type': 'text/html' } }));
    const result = await ctx.window.portalFetch('/reports/save');
    assert.equal(await result.text(), '<html>Page expired</html>');
    assert.equal(ctx.window.portalAuthenticationRequired, true);
    assert.ok(ctx.elements[0].children[0].textContent.includes('could not be verified'));
});

test('first-login password requirements link to password setup', async () => {
    const ctx = context(json(403, { message: 'Change your initial password.', code: 'password_change_required' }));
    await ctx.window.portalFetch('/notifications');
    assert.equal(ctx.elements[0].children[1].href, '/password/setup');
    assert.equal(ctx.elements[0].children[1].textContent, 'Set your password');
});

test('normal successful responses are returned without notices', async () => {
    const ctx = context(json(200, { saved: true }));
    assert.deepEqual(await (await ctx.window.portalFetch('/reports/save')).json(), { saved: true });
    assert.equal(ctx.elements.length, 0);
});

test('XHR uploads use the shared session notice without treating ordinary permission errors as expiry', () => {
    const ctx = context(null);
    ctx.window.handlePortalSessionResponse(403, { message: 'Not allowed.' });
    assert.equal(ctx.elements.length, 0);
    ctx.window.handlePortalSessionResponse(419, null);
    assert.equal(ctx.window.portalAuthenticationRequired, true);
    assert.equal(ctx.elements[0].children[1].href, '/login');
    assert.equal(ctx.input.value, 'Unsaved assessment answer');
});
