// This file is part of Moodle - http://moodle.org/

/**
 * Lightweight lifecycle regression test for the Live Monitor AMD module.
 *
 * Run with: node tests/js/live_dashboard_lifecycle_test.js
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../amd/src/live_dashboard.js'), 'utf8');
const timers = [];
const intervals = [];
const modalHandlers = {};
let disconnectCount = 0;
let moduleUnderTest;

const passiveControl = {addEventListener: () => {}};
const filter = {value: 'priority', addEventListener: () => {}};
const grid = {innerHTML: ''};
const count = {textContent: '', dataset: {}};
const paginationNodes = {
    '[data-region="webcamguard-live-page"]': {textContent: ''},
    '[data-region="webcamguard-live-pages"]': {textContent: ''},
    '[data-action="webcamguard-live-prev"]': {style: {}, disabled: false, addEventListener: () => {}},
    '[data-action="webcamguard-live-next"]': {style: {}, disabled: false, addEventListener: () => {}},
};
const pagination = {
    style: {},
    querySelector: selector => paginationNodes[selector] || null,
};
const root = {
    addEventListener: () => {},
    querySelector: selector => {
        if (selector === '[data-region="webcamguard-live-grid"]') return grid;
        if (selector === '[data-region="webcamguard-live-count"]') return count;
        if (selector === '[data-region="webcamguard-live-filter"]') return filter;
        if (selector === '[data-region="webcamguard-live-pagination"]') return pagination;
        if (selector === '[data-region="webcamguard-live-search"]') return passiveControl;
        if (selector.startsWith('[data-action=')) return passiveControl;
        return null;
    },
};

class Room {
    constructor() {
        this.handlers = {};
    }

    on(event, handler) {
        this.handlers[event] = handler;
    }

    connect() {
        return Promise.resolve();
    }

    disconnect() {
        disconnectCount++;
        if (this.handlers.disconnected) {
            this.handlers.disconnected();
        }
    }
}

const livekit = {
    Room,
    RoomEvent: {
        TrackSubscribed: 'trackSubscribed',
        Disconnected: 'disconnected',
    },
};

const ajax = {
    call: calls => [Promise.resolve(calls[0].methodname.includes('request_live') ? {
        active: true,
        url: 'wss://example.invalid',
        token: 'test-token',
    } : {candidates: [], attempts: []})],
};

const context = {
    console,
    Promise,
    Set,
    Date,
    Math,
    document: {
        getElementById: () => root,
        querySelector: () => null,
        createElement: () => ({
            innerHTML: '',
            set textContent(value) {
                this.innerHTML = String(value);
            },
        }),
    },
    window: {
        LivekitClient: livekit,
        setTimeout: (callback, delay) => {
            timers.push({callback, delay});
            return timers.length;
        },
        setInterval: (callback, delay) => {
            intervals.push({callback, delay});
            return intervals.length;
        },
        clearInterval: () => {},
        jQuery: () => ({
            hasClass: className => className === 'modal',
            closest: () => ({length: 0}),
            on: (event, handler) => {
                modalHandlers[event] = handler;
            },
        }),
    },
    define: (dependencies, factory) => {
        moduleUnderTest = factory(ajax, (dependenciesToLoad, resolve) => resolve(livekit));
    },
};

vm.runInNewContext(source, context);

const config = {
    courseid: 1,
    cmid: 2,
    quizid: 3,
    scriptUrl: '/livekit.js',
    limit: 20,
    candidates: [{
        attemptid: 10,
        fullname: 'Test Student',
        email: 'student@example.invalid',
        riskScore: 1,
        riskLevel: 'low',
        violationCount: 1,
        lastEventTime: 1,
        lastEventId: 1,
        lastViolationEventId: 1,
        lastEventName: 'Event',
        lastEventDisplay: 'Now',
        topViolationName: 'No face detected',
    }],
    strings: {
        activeAttempts: 'active attempts',
        idle: 'Idle',
        starting: 'Starting',
        waiting: 'Waiting',
        connected: 'Connected',
        stopped: 'Stopped',
        failed: 'Failed',
    },
};

const flushPromises = () => new Promise(resolve => setImmediate(resolve));
const runTimer = delay => {
    const index = timers.findIndex(timer => timer.delay === delay);
    if (index === -1) {
        throw new Error(`Expected a ${delay}ms timer`);
    }
    timers.splice(index, 1)[0].callback();
};

(async() => {
    moduleUnderTest.init(config);

    // The hidden modal must not connect before it is shown.
    await flushPromises();
    if (timers.some(timer => timer.delay === 500)) {
        runTimer(500);
        await flushPromises();
        await flushPromises();
    }

    modalHandlers['shown.bs.modal']();
    runTimer(500);
    await flushPromises();
    await flushPromises();

    if (disconnectCount !== 0) {
        throw new Error(`Opening Live Monitor caused ${disconnectCount} reconnect(s)`);
    }

    const selectionInterval = intervals.find(interval => interval.delay >= 30000);
    if (!selectionInterval) {
        throw new Error('Live selection retry interval was not registered');
    }
    selectionInterval.callback();
    await flushPromises();
    if (disconnectCount !== 0) {
        throw new Error(`Selection retry caused ${disconnectCount} reconnect(s)`);
    }

    // A duplicate shown event must be idempotent for already connected rooms.
    modalHandlers['shown.bs.modal']();
    runTimer(500);
    await flushPromises();
    await flushPromises();
    if (disconnectCount !== 0) {
        throw new Error(`Repeated modal event caused ${disconnectCount} reconnect(s)`);
    }

    console.log('PASS: modal lifecycle keeps existing LiveKit rooms connected');
})().catch(error => {
    console.error(`FAIL: ${error.message}`);
    process.exitCode = 1;
});
