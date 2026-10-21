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

const setup = ({ copy = false, tab = true, screenshot = false, limit = 0, oneQuestion = false, questionSeconds = 0, count = 3, modalDelay = 0 } = {}) => {
    let now = 10000;
    let focused = true;
    let timerId = 0;
    const timers = new Map();
    const intervals = new Set();
    const modals = new Map();
    const requests = [];
    const window = target();
    const element = () => {
        const classes = new Set();
        return { ...target(), style: {}, textContent: '', disabled: false,
            classList: { add: (...names) => names.forEach(name => classes.add(name)), remove: (...names) => names.forEach(name => classes.delete(name)),
                toggle: (name, on) => on ? classes.add(name) : classes.delete(name), contains: name => classes.has(name) },
            matches: () => false, focus() { this.focused = true; } };
    };
    const nodes = Object.fromEntries([
        'assessmentAttemptForm', 'submitConfirmModal', 'warningCount', 'answeredCount',
        'progressBar', 'timeRemaining', 'submitConfirmMessage', 'submitConfirmAnswered',
        'confirmSubmitAssessment',
    ].map(id => [id, element()]));
    if (oneQuestion) {
        nodes.currentQuestion = element();
        for (const id of ['nextQuestionConfirmModal', 'nextQuestionNumber', 'leavingQuestionNumber', 'nextQuestionAnswerStatus', 'confirmNextQuestion', 'stayOnQuestion']) nodes[id] = element();
    }
    const cards = oneQuestion ? Array.from({ length: count }, (_, index) => {
        const card = { ...element(), radio: { ...element(), checked: false }, remaining: element(), bar: element(), next: index < count - 1 ? element() : null,
            dataset: { questionIndex: String(index) }, scrollIntoView() {},
            querySelector(selector) {
                if (selector === '[data-next-question]') return this.next;
                if (selector === '[data-question-time-remaining]') return this.remaining;
                if (selector === '[data-question-time-bar]') return this.bar;
                return selector === 'input[type="radio"]:checked' && this.radio.checked ? this.radio : null;
            },
            querySelectorAll(selector) { return selector === 'input[type="radio"]' ? [this.radio] : []; },
        };
        return card;
    }) : [];
    const jumps = cards.map(element);
    nodes.assessmentAttemptForm.submit = () => { nodes.assessmentAttemptForm.submitted = true; };
    nodes.assessmentAttemptForm.requestSubmit = () => {
        if (!nodes.assessmentAttemptForm.emit('submit').prevented) nodes.assessmentAttemptForm.submit();
    };
    const document = {
        ...target(), hidden: false, activeElement: element(), hasFocus: () => focused,
        documentElement: { clientWidth: 1000, clientHeight: 800 },
        querySelectorAll: selector => selector === '[data-question-card]' ? cards : selector === '[data-question-jump]' ? jumps : [],
        querySelector: () => ({ value: 'test-csrf' }),
        getElementById: id => nodes[id] || null,
    };
    Object.assign(window, {
        innerWidth: 1000, innerHeight: 800,
        visualViewport: { ...target(), width: 1000, height: 800, scale: 1 },
        setTimeout(fn, delay) { const id = ++timerId; timers.set(id, { fn, at: now + delay }); return id; },
        setInterval(fn, delay) { const id = ++timerId; intervals.add(id); timers.set(id, { fn, at: now + delay, interval: delay }); return id; },
        clearInterval(id) { intervals.delete(id); timers.delete(id); },
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
        '$isOneQuestionMode': oneQuestion,
        '$questionTimeLimitSeconds': questionSeconds,
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
        bootstrap: { Modal: class {
            constructor(node) { this.node = node; this.shown = false; this.transitioning = false; modals.set(node, this); }
            transition(done) { if (modalDelay) window.setTimeout(done, modalDelay); else done(); }
            show() {
                if (this.shown || this.transitioning) return;
                this.shown = true; this.transitioning = true; this.node.emit('show.bs.modal');
                this.transition(() => { this.transitioning = false; this.node.emit('shown.bs.modal'); });
            }
            hide() {
                if (!this.shown || this.transitioning) return;
                this.transitioning = true;
                this.transition(() => { this.shown = false; this.transitioning = false; this.node.emit('hidden.bs.modal'); });
            }
        } },
    });
    const advance = async (milliseconds = 150) => {
        const until = now + milliseconds;
        while (true) {
            const next = [...timers].filter(([, timer]) => timer.at <= until).sort((a, b) => a[1].at - b[1].at)[0];
            if (!next) break;
            const [id, timer] = next;
            timers.delete(id); now = timer.at; timer.fn(); await settle();
            if (timer.interval && intervals.has(id)) timers.set(id, { ...timer, at: now + timer.interval });
        }
        now = until; await settle();
    };
    return { window, document, nodes, cards, jumps, modals, requests, advance, focus(value) { focused = value; window.emit(value ? 'focus' : 'blur'); } };
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

test('accidental Next only opens confirmation; cancelling keeps the question editable', () => {
    const env = setup({ oneQuestion: true });
    env.cards[0].next.emit('click');
    assert.equal(env.nodes.currentQuestion.textContent, '1');
    assert.equal(env.cards[0].classList.contains('question-expired'), false);
    assert.equal(env.nodes.stayOnQuestion.focused, true);
    assert.match(env.nodes.nextQuestionAnswerStatus.textContent, /not answered/);
    env.modals.get(env.nodes.nextQuestionConfirmModal).hide();
    assert.equal(env.nodes.currentQuestion.textContent, '1');
    assert.equal(env.cards[0].radio.tabIndex, undefined);
    assert.equal(env.nodes.assessmentAttemptForm.submitted, undefined);
});

test('Confirm and Next locks only the previous question and ignores duplicate confirmation taps', () => {
    const env = setup({ oneQuestion: true });
    env.cards[0].radio.checked = true;
    env.cards[0].next.emit('click');
    assert.match(env.nodes.nextQuestionAnswerStatus.textContent, /answer will be kept/);
    env.nodes.confirmNextQuestion.emit('click');
    env.nodes.confirmNextQuestion.emit('click');
    assert.equal(env.nodes.currentQuestion.textContent, '2');
    assert.equal(env.cards[0].classList.contains('question-expired'), true);
    assert.equal(env.cards[0].radio.tabIndex, -1);
    assert.equal(env.cards[1].classList.contains('active-question'), true);
    assert.equal(env.cards[1].classList.contains('question-expired'), false);
    env.cards[0].next.emit('click');
    assert.equal(env.modals.get(env.nodes.nextQuestionConfirmModal).shown, false);
});

test('early implicit submission is blocked; the last question retains submission confirmation', () => {
    const env = setup({ oneQuestion: true });
    env.nodes.assessmentAttemptForm.requestSubmit();
    env.nodes.confirmSubmitAssessment.emit('click');
    assert.equal(env.nodes.assessmentAttemptForm.submitted, undefined);
    assert.equal(env.modals.get(env.nodes.submitConfirmModal).shown, false);
    for (let index = 0; index < 2; index++) {
        env.cards[index].next.emit('click');
        env.nodes.confirmNextQuestion.emit('click');
    }
    env.nodes.assessmentAttemptForm.requestSubmit();
    assert.equal(env.modals.get(env.nodes.submitConfirmModal).shown, true);
    assert.equal(env.nodes.assessmentAttemptForm.submitted, undefined);
    env.nodes.confirmSubmitAssessment.emit('click');
    assert.equal(env.nodes.assessmentAttemptForm.submitted, true);
});

test('a single-question assessment can submit immediately with confirmation', () => {
    const env = setup({ oneQuestion: true, count: 1 });
    env.nodes.assessmentAttemptForm.requestSubmit();
    assert.equal(env.modals.get(env.nodes.submitConfirmModal).shown, true);
    assert.equal(env.nodes.assessmentAttemptForm.submitted, undefined);
});

test('question timer keeps running during confirmation and expiry discards stale confirmation', async () => {
    const env = setup({ oneQuestion: true, questionSeconds: 2 });
    env.cards[0].next.emit('click');
    await env.advance(1000);
    assert.equal(env.cards[0].remaining.textContent, '00:01');
    await env.advance(1700);
    assert.equal(env.nodes.currentQuestion.textContent, '2');
    assert.equal(env.modals.get(env.nodes.nextQuestionConfirmModal).shown, false);
    env.nodes.confirmNextQuestion.emit('click');
    assert.equal(env.nodes.currentQuestion.textContent, '2');
    assert.equal(env.cards[1].classList.contains('question-expired'), false);
});

test('cancelling Next does not reset or pause the remaining question time', async () => {
    const env = setup({ oneQuestion: true, questionSeconds: 4 });
    env.cards[0].next.emit('click');
    await env.advance(1000);
    env.modals.get(env.nodes.nextQuestionConfirmModal).hide();
    await env.advance(1000);
    assert.equal(env.nodes.currentQuestion.textContent, '1');
    assert.equal(env.cards[0].remaining.textContent, '00:02');
});

test('expiry during the modal opening transition still closes it and advances once', async () => {
    const env = setup({ oneQuestion: true, questionSeconds: 1, modalDelay: 400 });
    await env.advance(800);
    env.cards[0].next.emit('click');
    await env.advance(900);
    assert.equal(env.nodes.currentQuestion.textContent, '2');
    assert.equal(env.modals.get(env.nodes.nextQuestionConfirmModal).shown, false);
});

test('expiry while a confirmed modal is closing advances once without skipping the next question', async () => {
    const env = setup({ oneQuestion: true, questionSeconds: 1, modalDelay: 400 });
    env.cards[0].next.emit('click');
    await env.advance(800);
    env.nodes.confirmNextQuestion.emit('click');
    await env.advance(900);
    assert.equal(env.nodes.currentQuestion.textContent, '2');
    assert.equal(env.cards[1].classList.contains('question-expired'), false);
});

test('confirmation tapped during its opening transition is applied after it closes', async () => {
    const env = setup({ oneQuestion: true, modalDelay: 400 });
    env.cards[0].next.emit('click');
    env.nodes.confirmNextQuestion.emit('click');
    await env.advance(800);
    assert.equal(env.nodes.currentQuestion.textContent, '2');
    assert.equal(env.modals.get(env.nodes.nextQuestionConfirmModal).shown, false);
});

test('last-question timer expiry still auto-submits without manual confirmation', async () => {
    const env = setup({ oneQuestion: true, questionSeconds: 1, count: 1 });
    await env.advance(1900);
    assert.equal(env.nodes.assessmentAttemptForm.submitted, true);
});

test('Next confirmation does not disable tab-switch warnings', async () => {
    const env = setup({ oneQuestion: true });
    env.cards[0].next.emit('click');
    env.document.hidden = true;
    env.document.emit('visibilitychange');
    await settle();
    assert.deepEqual(env.requests, ['tab_hidden']);
});

test('security auto-submission still works before the last question with Next confirmation open', async () => {
    const env = setup({ oneQuestion: true, limit: 1 });
    env.cards[0].next.emit('click');
    env.document.hidden = true;
    env.document.emit('visibilitychange');
    await settle();
    await env.advance(1200);
    assert.equal(env.nodes.currentQuestion.textContent, '1');
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
