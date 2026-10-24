import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const portal = readFileSync(new URL('../../public/js/portal.js', import.meta.url), 'utf8');
const script = portal.slice(portal.indexOf('    const syncPageStyles ='), portal.indexOf('    let latestPortalPageRequest'));
const setup = () => {
    const previous = { dataset: { pageStyle: '' }, removed: false, remove() { this.removed = true; } };
    const inserted = [];
    const next = ['LINK', 'STYLE'].map(tagName => ({ cloneNode() {
        return { tagName, dataset: {}, media: '', removed: false, remove() { this.removed = true; } };
    } }));
    const context = vm.createContext({ document: {
        querySelectorAll: () => [previous, ...inserted].filter(node => !node.removed && 'pageStyle' in node.dataset),
        head: { appendChild(node) { inserted.push(node); } },
    } });
    vm.runInContext(script, context);
    const sync = vm.runInContext('syncPageStyles', context);
    return { previous, inserted, sync: isCurrent => sync({ head: { querySelectorAll: () => next } }, isCurrent) };
};

test('AJAX navigation waits for external page CSS and swaps it together with inline print settings', async () => {
    const app = setup();
    const loading = app.sync();
    assert.equal(app.previous.removed, false);
    assert.ok(app.inserted.every(node => node.media === 'not all'));
    app.inserted[0].onload();
    assert.equal(await loading, true);
    assert.equal(app.previous.removed, true);
    assert.ok(app.inserted.every(node => node.media === '' && !node.removed));
});

test('a stylesheet failure keeps the current page styles and discards the failed additions', async () => {
    const app = setup();
    const loading = app.sync();
    app.inserted[0].onerror();
    await assert.rejects(loading, /stylesheet could not be loaded/);
    assert.equal(app.previous.removed, false);
    assert.ok(app.inserted.every(node => node.removed));
});

test('an outdated navigation response cannot replace the latest page styles', async () => {
    const app = setup();
    const loading = app.sync(() => false);
    app.inserted[0].onload();
    assert.equal(await loading, false);
    assert.equal(app.previous.removed, false);
    assert.ok(app.inserted.every(node => node.removed));
});

test('overlapping navigation leaves pending loads intact until each response can discard or commit its own styles', async () => {
    const app = setup();
    let current = 1;
    const first = app.sync(() => current === 1);
    current = 2;
    const second = app.sync(() => current === 2);
    app.inserted[2].onload();
    assert.equal(await second, true);
    assert.equal(app.inserted[0].removed, false);
    assert.equal(app.inserted[2].removed, false);
    app.inserted[0].onload();
    assert.equal(await first, false);
    assert.equal(app.inserted[0].removed, true);
    assert.equal(app.inserted[2].removed, false);
});
