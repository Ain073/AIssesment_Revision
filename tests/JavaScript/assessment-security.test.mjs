import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const template = readFileSync(new URL('../../resources/views/student/assessments/assessment-attempt-page-code.blade.php', import.meta.url), 'utf8');
const target = () => ({
    listeners: new Map(),
    addEventListener(name, listener) {
        this.listeners.set(name, [...(this.listeners.get(name) || []), listener]);
    },
    emit(name, extras = {}) {
        const event = { prevented: false, preventDefault() { this.prevented = true; }, ...extras };
        for (const listener of this.listeners.get(name) || []) listener(event);
        return event;
    },
});
const settle = async () => { for (let i = 0; i < 8; i++) await Promise.resolve(); };

const setup = ({ copy = false, tab = true, screenshot = false, limit = 0 } = {}) => {
    let now = 10000;
    let focused = true;
    let timerId = 0;
    const timers = new Map();
    const requests = [];
    const window = target();
    const element = () => ({ ...target(), style: {}, classList: { add() {}, remove() {}, toggle() {} }, matches: () => false });
    const nodes = Object.fromEntries([
        'assessmentAttemptForm', 'submitConfirmModal', 'warningCount', 'answeredCount',
        'progressBar', 'timeRemaining', 'submitConfirmMessage', 'submitConfirmAnswered',
    ].map(id => [id, element()]));
    nodes.assessmentAttemptForm.submit = () => { nodes.assessmentAttemptForm.submitted = true; };
    nodes.assessmentAttemptForm.requestSubmit = () => {
        if (!nodes.assessmentAttemptForm.emit('submit').prevented) nodes.assessmentAttemptForm.submit();
    };
    const document = {
        ...target(), hidden: false, activeElement: element(), hasFocus: () => focused,
        documentElement: { clientWidth: 1000, clientHeight: 800 },
        querySelectorAll: () => [], querySelector: () => ({ value: 'test-csrf' }),
        getElementById: id => nodes[id] || null,
    };
    Object.assign(window, {
        innerWidth: 1000, innerHeight: 800,
        visualViewport: { ...target(), width: 1000, height: 800, scale: 1 },
        setTimeout(fn, delay) { const id = ++timerId; timers.set(id, { fn, at: now + delay }); return id; },
        setInterval: () => 1, clearInterval() {},
        portalFetch: async (url, options) => {
            requests.push(JSON.parse(options.body).event_type);
            const warningCount = limit > 0 ? Math.min(requests.length, limit) : 0;
            return { ok: true, json: async () => ({ warning_count: warningCount, should_auto_submit: limit > 0 && warningCount >= limit }) };
        },
    });
    const values = {
        '$warningLimit': limit,
        '$publishAssessment->prevent_copy_paste': copy,
        '$publishAssessment->detect_tab_switch': tab,
        '$publishAssessment->screenshot_protection': screenshot,
        '$isOneQuestionMode': false,
        '$questionTimeLimitSeconds': 0,
        "route('student.assessments.security-events.store', $publishAssessment)": '/security-events',
        '$dueIso': null,
        '$submission->warning_count': 0,
    };
    let script = template.replace(/^<script>\s*|\s*<\/script>\s*$/g, '');
    for (const [expression, value] of Object.entries(values)) script = script.replaceAll(`@json(${expression})`, JSON.stringify(value));
    assert.equal(script.includes('@json'), false, 'Every Blade value must have an explicit test fixture');
    vm.runInNewContext(script, {
        window, document, console,
        Date: class extends Date { static now() { return now; } },
        bootstrap: { Modal: class { show() { nodes.submitConfirmModal.emit('shown.bs.modal'); } } },
    });
    const advance = async (milliseconds = 150) => {
        const until = now + milliseconds;
        while (true) {
            const next = [...timers].filter(([, timer]) => timer.at <= until).sort((a, b) => a[1].at - b[1].at)[0];
            if (!next) break;
            const [id, timer] = next;
            timers.delete(id); now = timer.at; timer.fn(); await settle();
        }
        now = until; await settle();
    };
    return { window, document, nodes, requests, advance, focus(value) { focused = value; window.emit(value ? 'focus' : 'blur'); } };
};

test('switching tabs records a warning and returning permits a later warning', async () => {
    const env = setup();
    env.document.hidden = true; env.focus(false); env.document.emit('visibilitychange');
    await env.advance();
    assert.deepEqual(env.requests, ['tab_hidden']);
    env.document.hidden = false; env.document.emit('visibilitychange'); env.focus(true);
    await env.advance(1300);
    env.document.hidden = true; env.document.emit('visibilitychange');
    assert.deepEqual(env.requests, ['tab_hidden', 'tab_hidden']);
});

test('losing window focus records a warning even when an answer field remains active', async () => {
    const env = setup();
    env.document.activeElement = { matches: () => true };
    env.focus(false); await env.advance();
    assert.deepEqual(env.requests, ['window_blur']);
    env.focus(true); await env.advance(1300);
    env.focus(false); await env.advance();
    assert.deepEqual(env.requests, ['window_blur', 'window_blur']);
});

test('blur followed by a hidden tab records only one warning for the departure', async () => {
    const env = setup();
    env.focus(false); await env.advance();
    env.document.hidden = true; env.document.emit('visibilitychange');
    assert.deepEqual(env.requests, ['window_blur']);
});

test('a hidden tab followed by blur records only one warning', async () => {
    const env = setup();
    env.document.hidden = true; env.document.emit('visibilitychange'); env.focus(false);
    await env.advance();
    assert.deepEqual(env.requests, ['tab_hidden']);
});

test('answer-field focus and a brief blur that recovers do not record a departure', async () => {
    const env = setup();
    env.document.activeElement = { matches: () => true };
    env.window.emit('blur'); await env.advance();
    env.focus(false); env.focus(true); await env.advance();
    assert.deepEqual(env.requests, []);
});

test('resizing, zooming and rotating do not generate floating-window warnings', async () => {
    const env = setup();
    await env.advance(2000);
    env.window.innerWidth = 400; env.window.innerHeight = 350;
    Object.assign(env.window.visualViewport, { width: 200, height: 175, scale: 2 });
    env.window.emit('resize'); env.window.visualViewport.emit('resize'); env.window.emit('orientationchange');
    await env.advance(2000);
    assert.deepEqual(env.requests, []);
});

test('submission confirmation suppresses security warnings', async () => {
    const env = setup();
    env.nodes.submitConfirmModal.emit('shown.bs.modal');
    env.focus(false); await env.advance();
    env.document.hidden = true; env.document.emit('visibilitychange');
    assert.deepEqual(env.requests, []);
});

test('a window departure still triggers automatic submission at the warning limit', async () => {
    const env = setup({ limit: 1 });
    env.focus(false); await env.advance();
    await env.advance(1200);
    assert.deepEqual(env.requests, ['window_blur']);
    assert.equal(env.nodes.assessmentAttemptForm.submitted, true);
});

for (const copy of [false, true]) for (const tab of [false, true]) for (const screenshot of [false, true]) {
    test(`security settings stay independent: copy=${copy}, tab=${tab}, screenshot=${screenshot}`, async () => {
        const env = setup({ copy, tab, screenshot });
        for (const name of ['copy', 'cut', 'paste', 'contextmenu']) {
            const event = env.document.emit(name, { target: { matches: () => false } });
            assert.equal(event.prevented, copy);
        }
        assert.equal(env.document.emit('keydown', { key: 'PrintScreen' }).prevented, screenshot);
        env.document.emit('keyup', { key: 'PrintScreen' });
        assert.equal(env.document.emit('keydown', { key: 'p', ctrlKey: true }).prevented, screenshot);
        env.focus(false); await env.advance();
        env.document.hidden = true; env.document.emit('visibilitychange');
        await settle();
        assert.deepEqual(env.requests, [
            ...(copy ? ['copy_attempt', 'cut_attempt', 'paste_attempt', 'context_menu_attempt'] : []),
            ...(screenshot ? ['screenshot_shortcut', 'print_shortcut'] : []),
            ...(tab ? ['window_blur'] : []),
        ]);
    });
}
