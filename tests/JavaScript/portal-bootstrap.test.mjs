import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

test('the portal boots its shared scripts in layout order without feature elements or network requests', () => {
    const layout = readFileSync(new URL('../../resources/views/layouts/portal.blade.php', import.meta.url), 'utf8');
    const scripts = [...layout.matchAll(/<script src="\{\{ asset\('([^']+)'\)/g)].map(match => match[1]);
    const classList = { add() {}, remove() {}, toggle() {}, contains() { return false; } };
    const window = {
        addEventListener() {}, removeEventListener() {},
        setTimeout() { return 1; }, clearTimeout() {}, setInterval() { return 1; }, clearInterval() {},
        location: { href: 'http://localhost/instructor/reports', pathname: '/instructor/reports', origin: 'http://localhost' },
        history: {},
    };
    const document = {
        addEventListener() {}, querySelector() { return null; }, querySelectorAll() { return []; },
        getElementById() { return null; }, body: { dataset: {}, classList }, head: { appendChild() {} },
        documentElement: {}, readyState: 'complete', hidden: false,
    };
    const storage = { getItem() { return null; }, setItem() {}, removeItem() {} };
    const context = vm.createContext({
        window, document, localStorage: storage, sessionStorage: storage,
        URL, URLSearchParams, AbortController, console,
        fetch() { throw new Error('An empty feature page must not start a network request.'); },
    });
    for (const script of scripts) {
        vm.runInContext(readFileSync(new URL('../../public/' + script, import.meta.url), 'utf8'), context, { filename: script });
    }
    for (const name of [
        'navigatePortalPage', 'initializeRecordSearch', 'initializeReportsPage', 'initializeTableTabs',
        'initializeAssessmentFilters', 'initializeClassForms', 'initializeDashboardPage', 'createReportAutosave',
    ]) {
        assert.equal(typeof window[name], 'function', `${name} is available after initial page loading`);
    }
});
