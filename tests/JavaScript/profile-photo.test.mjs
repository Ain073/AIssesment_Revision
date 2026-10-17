import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/profile-photo.js', import.meta.url), 'utf8');
const setup = () => {
    const listeners = new Map();
    const requests = [];
    const revoked = [];
    const sessionResponses = [];
    const avatars = [0, 1].map(() => ({ dataset: { profilePhotoAlt: 'Account profile picture' }, image: 'old', replaceChildren(image) { this.image = image; } }));
    const element = () => ({ hidden: true, textContent: '', value: 0, attrs: {},
        setAttribute(name, value) { this.attrs[name] = value; }, removeAttribute(name) { delete this.attrs[name]; },
    });
    const target = () => ({ handlers: {}, addEventListener(name, fn) { this.handlers[name] = fn; }, emit(name, event = {}) { this.handlers[name]?.(event); } });
    class XHR {
        constructor() { Object.assign(this, target(), { upload: target(), headers: {}, contentType: 'application/json' }); requests.push(this); }
        open(method, url) { this.method = method; this.url = url; }
        setRequestHeader(name, value) { this.headers[name] = value; }
        getResponseHeader() { return this.contentType; }
        send(body) { this.body = body; }
        complete(status, response, contentType = 'application/json') {
            Object.assign(this, { status, response, contentType }); this.emit('load');
        }
    }
    let urls = 0;
    class PreviewURL extends URL {
        static createObjectURL() { return `blob:preview-${++urls}`; }
        static revokeObjectURL(value) { revoked.push(value); }
    }
    const window = { location: { href: 'http://localhost/profile' }, handlePortalSessionResponse: (...args) => sessionResponses.push(args) };
    const document = {
        addEventListener(type, callback) { listeners.set(type, [...(listeners.get(type) || []), callback]); },
        querySelectorAll: () => avatars, createElement: () => ({}),
    };
    class FormData {
        constructor(form) {
            assert.equal(form.input.disabled, false, 'File must be captured before inputs are disabled');
            this.file = form.input.files[0]; this.method = 'PUT'; this.token = 'test-csrf';
        }
    }
    vm.runInNewContext(script, { window, document, XMLHttpRequest: XHR, FormData, URL: PreviewURL });
    const createForm = () => {
        const nodes = Object.fromEntries(['preview', 'preview-image', 'upload-feedback', 'upload-progress', 'progress', 'upload-status']
            .map(name => [`[data-photo-${name}]`, element()]));
        const form = {
            nodes, dataset: {}, isConnected: true, input: null,
            button: { disabled: false }, preservedButton: { disabled: true },
            attrs: {}, setAttribute(name, value) { this.attrs[name] = value; }, removeAttribute(name) { delete this.attrs[name]; },
            closest: () => form, matches: () => true,
            getAttribute: (name) => name === 'method' ? 'POST' : '/profile/photo',
            querySelector(selector) { return selector.startsWith('input') ? this.input : nodes[selector]; },
            querySelectorAll() { return [this.input, this.button, this.preservedButton]; },
        };
        form.input = { files: [], disabled: false, closest: (selector) => selector.startsWith('input') ? form.input : form };
        Object.defineProperty(form.input, 'value', { set(value) { if (! value) this.files = []; }, get() { return this.files[0]?.name || ''; } });
        return form;
    };
    const emit = (type, target) => {
        const event = { target, prevented: false, preventDefault() { this.prevented = true; } };
        (listeners.get(type) || []).forEach(fn => fn(event));
        return event;
    };
    const select = (form, file = { name: 'avatar.png', type: 'image/png', size: 1000 }) => {
        form.input.files = file ? [file] : []; emit('change', form.input);
    };
    return { window, requests, revoked, sessionResponses, avatars, createForm, emit, select };
};

test('selection previews locally, releases replaced previews, and never uploads before submission', () => {
    const ctx = setup(); const form = ctx.createForm();
    ctx.select(form);
    assert.equal(form.nodes['[data-photo-preview]'].hidden, false);
    assert.equal(form.nodes['[data-photo-preview-image]'].src, 'blob:preview-1');
    assert.equal(ctx.requests.length, 0);
    assert.equal(ctx.avatars[0].image, 'old');
    ctx.select(form);
    assert.deepEqual(ctx.revoked, ['blob:preview-1']);
    ctx.select(form, null);
    assert.deepEqual(ctx.revoked, ['blob:preview-1', 'blob:preview-2']);
    assert.equal(form.nodes['[data-photo-preview]'].hidden, true);
});

test('invalid types and files over 2 MB show feedback without uploading or changing avatars', () => {
    const ctx = setup(); const form = ctx.createForm();
    for (const file of [
        { name: 'bad.svg', type: 'image/svg+xml', size: 100 },
        { name: 'large.png', type: 'image/png', size: 2097153 },
    ]) {
        ctx.select(form, file); ctx.emit('submit', form);
        assert.equal(form.nodes['[data-photo-upload-feedback]'].hidden, false);
        assert.equal(ctx.requests.length, 0);
        assert.equal(ctx.avatars[0].image, 'old');
    }
});

test('multipart upload captures the file before disabling controls and only updates pictures after saving', () => {
    const ctx = setup(); const form = ctx.createForm(); ctx.select(form);
    const file = form.input.files[0]; ctx.emit('submit', form);
    const xhr = ctx.requests[0];
    assert.equal(xhr.body.file, file);
    assert.equal(xhr.method, 'POST'); assert.equal(xhr.url, '/profile/photo');
    assert.equal(xhr.headers.Accept, 'application/json');
    assert.equal(xhr.headers['Content-Type'], undefined);
    assert.equal(form.input.disabled, true); assert.equal(form.button.disabled, true);
    xhr.upload.emit('progress', { lengthComputable: true, loaded: 40, total: 100 });
    assert.equal(form.nodes['[data-photo-progress]'].value, 40);
    xhr.upload.emit('load');
    assert.ok(form.nodes['[data-photo-upload-status]'].textContent.includes('Saving'));
    assert.equal(ctx.avatars[0].image, 'old');
    xhr.complete(200, { photo_url: '/profile/photo?v=new-content', message: 'Profile picture updated.' });
    assert.equal(ctx.avatars[0].image.src, 'http://localhost/profile/photo?v=new-content');
    assert.equal(ctx.avatars[1].image.src, ctx.avatars[0].image.src);
    assert.equal(form.nodes['[data-photo-upload-status]'].textContent, 'Saved.');
    assert.equal(form.input.disabled, false); assert.equal(form.button.disabled, false);
    assert.equal(form.preservedButton.disabled, true);
    assert.equal(form.input.files.length, 0);
    assert.deepEqual(ctx.revoked, ['blob:preview-1']);
    assert.equal(ctx.window.location.href, 'http://localhost/profile');
});

test('unknown upload size stays indeterminate and duplicate submissions cannot send another request', () => {
    const ctx = setup(); const form = ctx.createForm(); ctx.select(form); ctx.emit('submit', form);
    const xhr = ctx.requests[0]; xhr.upload.emit('progress', { lengthComputable: false });
    assert.equal(form.nodes['[data-photo-progress]'].attrs.value, undefined);
    ctx.emit('submit', form); assert.equal(ctx.requests.length, 1);
    const newForm = ctx.createForm(); ctx.select(newForm); ctx.emit('submit', newForm);
    assert.equal(ctx.requests.length, 1);
    assert.ok(newForm.nodes['[data-photo-upload-feedback]'].textContent.includes('still running'));
});

test('422 feedback is plain text, preserves the selection and supports retry', () => {
    const ctx = setup(); const form = ctx.createForm(); ctx.select(form); ctx.emit('submit', form);
    ctx.requests[0].complete(422, { errors: { profile_photo: ['<script>Invalid file</script>'] } });
    assert.equal(form.nodes['[data-photo-upload-feedback]'].textContent, '<script>Invalid file</script>');
    assert.equal(form.nodes['[data-photo-upload-feedback]'].innerHTML, undefined);
    assert.equal(form.input.files.length, 1); assert.equal(form.input.disabled, false);
    assert.equal(ctx.avatars[0].image, 'old');
    ctx.emit('submit', form); assert.equal(ctx.requests.length, 2);
});

test('network failure, abort and timeout do not claim success or change displayed pictures', () => {
    for (const event of ['error', 'timeout', 'abort']) {
        const ctx = setup(); const form = ctx.createForm(); ctx.select(form); ctx.emit('submit', form);
        ctx.requests[0].emit(event);
        assert.ok(form.nodes['[data-photo-upload-feedback]'].textContent.includes('could not be confirmed'));
        assert.equal(form.input.disabled, false); assert.equal(form.input.files.length, 1);
        assert.equal(ctx.avatars[0].image, 'old');
        ctx.emit('submit', form); assert.equal(ctx.requests.length, 2);
    }
});

test('session and CSRF errors use the shared notice handler without reloading the page', () => {
    const ctx = setup(); const form = ctx.createForm(); ctx.select(form); ctx.emit('submit', form);
    ctx.requests[0].complete(419, { message: 'Session expired.' });
    assert.equal(ctx.sessionResponses[0][0], 419);
    assert.equal(form.nodes['[data-photo-upload-feedback]'].textContent, 'Session expired.');
    assert.equal(ctx.window.location.href, 'http://localhost/profile');
    assert.equal(ctx.avatars[0].image, 'old');
});

test('unexpected HTML, server errors and unsafe photo URLs never become successful uploads', () => {
    for (const [status, data, type] of [
        [200, null, 'text/html'], [500, { message: 'Internal details' }, 'application/json'],
        [200, { photo_url: 'javascript:alert(1)' }, 'application/json'],
    ]) {
        const ctx = setup(); const form = ctx.createForm(); ctx.select(form); ctx.emit('submit', form);
        ctx.requests[0].complete(status, data, type);
        assert.ok(form.nodes['[data-photo-upload-feedback]'].textContent.includes('could not be confirmed'));
        assert.equal(ctx.avatars[0].image, 'old'); assert.equal(form.button.disabled, false);
    }
});

test('reset and modal dismissal release preview URLs while an in-flight upload may finish safely', () => {
    const ctx = setup(); const form = ctx.createForm();
    ctx.select(form); ctx.emit('reset', form); assert.deepEqual(ctx.revoked, ['blob:preview-1']);
    ctx.select(form); ctx.emit('hidden.bs.modal', { querySelectorAll: () => [form] });
    assert.equal(form.input.files.length, 0);
    ctx.select(form); ctx.emit('submit', form);
    ctx.emit('hidden.bs.modal', { querySelectorAll: () => [form] });
    assert.equal(form.input.disabled, true);
    ctx.requests[0].complete(200, { photo_url: '/new-photo.png' });
    assert.equal(ctx.avatars[0].image.src, 'http://localhost/new-photo.png');
});

test('AJAX page replacement releases previews and detached requests update only currently displayed avatars', () => {
    const ctx = setup(); const old = ctx.createForm(); ctx.select(old); ctx.emit('submit', old);
    ctx.window.cleanupProfilePhotoPreviews({ querySelectorAll: () => [old] });
    old.isConnected = false;
    assert.deepEqual(ctx.revoked, ['blob:preview-1']);
    ctx.requests[0].complete(200, { photo_url: '/new-photo.png' });
    assert.equal(ctx.avatars[0].image.src, 'http://localhost/new-photo.png');
    assert.equal(old.button.disabled, false);
    const newForm = ctx.createForm(); ctx.select(newForm); ctx.emit('submit', newForm);
    assert.equal(ctx.requests.length, 2);
});

test('unrelated forms keep their normal submission behavior', () => {
    const ctx = setup();
    assert.equal(ctx.emit('submit', { closest: () => null }).prevented, false);
    assert.equal(ctx.requests.length, 0);
});
