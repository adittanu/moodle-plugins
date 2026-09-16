// Run with NODE_PATH pointing to installed jsdom, or install jsdom locally for this test.
const {JSDOM} = require('jsdom');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const flush = () => new Promise(resolve => setImmediate(resolve));
const source = fs.readFileSync(process.env.WCG_TEST_SOURCE || path.resolve(__dirname,
    '../../amd/src/live_dashboard.js'), 'utf8');

function setup(jquery = false) {
    const dom = new JSDOM(`<div id="quizaccess-webcamguard-live-dashboard" class="modal">
        <select data-region="webcamguard-live-filter"><option value="priority">Priority</option></select>
        <input data-region="webcamguard-live-search">
        <button data-action="webcamguard-live-start-selection"></button>
        <button data-action="webcamguard-live-stop-all"></button>
        <button data-action="webcamguard-live-refresh"></button>
        <div data-region="webcamguard-live-count"></div>
        <div class="quizaccess-webcamguard-livegrid" data-region="webcamguard-live-grid"></div>
    </div>`, {runScripts: 'outside-only'});
    const w = dom.window;
    const root = w.document.getElementById('quizaccess-webcamguard-live-dashboard');
    const grid = root.querySelector('[data-region="webcamguard-live-grid"]');
    let seq = 0;
    const timers = new Map();
    const intervals = new Map();
    w.setTimeout = (fn, delay) => { timers.set(++seq, {fn, delay}); return seq; };
    w.clearTimeout = id => timers.delete(id);
    w.setInterval = (fn, delay) => { intervals.set(++seq, {fn, delay}); return seq; };
    w.clearInterval = id => intervals.delete(id);
    const candidates = Array.from({length: 20}, (_, i) => ({attemptid: i + 1,
        fullname: `Student ${i + 1}`, email: `student${i}@example.invalid`,
        riskScore: 40 - i, violationCount: 20 - i, riskLevel: 'high',
        lastEventTime: 1, lastEventName: 'Event', lastEventDisplay: 'Now'}));
    const api = {candidates, requests: [], rooms: [], hold: false, pending: [], failConnect: false};
    class Room {
        constructor() { this.handlers = {}; this.disconnected = 0; api.rooms.push(this); }
        on(event, fn) { this.handlers[event] = fn; }
        connect() {
            if (api.failConnect) { return Promise.reject(new Error('Fixture connection failure')); }
            this.handlers.track?.({kind: 'video', attach: () => w.document.createElement('video')});
            return Promise.resolve();
        }
        disconnect() { this.disconnected++; this.handlers.disconnected?.(); }
    }
    w.LivekitClient = {Room, RoomEvent: {TrackSubscribed: 'track', Disconnected: 'disconnected'}};
    if (jquery) {
        w.jQuery = element => ({hasClass: name => element.classList.contains(name),
            closest: () => ({length: 0}), on: (event, fn) => element.addEventListener(event, fn)});
    }
    const ajax = {call: ([request]) => {
        api.requests.push(request);
        if (request.methodname.endsWith('poll_live_candidates')) {
            return [Promise.resolve({candidates: api.candidates.map(c => ({...c}))})];
        }
        if (request.methodname.endsWith('poll_live_stats')) return [Promise.resolve({attempts: []})];
        const result = {active: request.args.action === 'start', url: 'wss://example.invalid', token: 'fixture'};
        if (api.hold && request.args.action === 'start') {
            return [new Promise(resolve => api.pending.push(() => resolve(result)))];
        }
        return [Promise.resolve(result)];
    }};
    let mod;
    w.define = (deps, factory) => { mod = factory(ajax, (deps, resolve) => resolve(w.LivekitClient)); };
    w.eval(source);
    const config = {courseid: 1, quizid: 1, cmid: 1, limit: 20, candidates,
        strings: {idle: 'Idle', starting: 'Starting', stopped: 'Stopped', waiting: 'Waiting',
            connected: 'Connected', failed: 'Failed'}};
    mod.init(config);
    async function tick(delay) {
        for (const [id, timer] of [...timers]) {
            if (timer.delay === delay) { timers.delete(id); timer.fn(); }
        }
        await flush();
    }
    return {api, grid, root, mod, config, w, tick,
        async open() { root.dispatchEvent(new w.Event('shown.bs.modal')); await tick(500); await tick(250); },
        async poll() { [...intervals.values()].filter(t => t.delay === 5000).forEach(t => t.fn()); await flush(); },
        async retry() { [...intervals.values()].filter(t => t.delay >= 30000).forEach(t => t.fn()); await flush(); },
        click(action) { root.querySelector(`[data-action="webcamguard-live-${action}"]`).click(); },
    };
}

test('20 participants render as siblings, never nested cards', () => {
    const h = setup();
    assert.equal(h.grid.children.length, 20);
    assert.equal(h.grid.querySelectorAll('[data-tile-for] [data-tile-for]').length, 0);
});

test('candidate polling preserves video nodes, room connections and message drafts', async() => {
    const h = setup(); await h.open();
    const video = h.grid.querySelector('[data-video-for="1"] video');
    assert.ok(video);
    h.grid.querySelector('[data-warning-for="1"]').value = 'Draft message';
    h.api.candidates = h.api.candidates.slice(0, 19);
    await h.poll();
    assert.equal(h.grid.querySelector('[data-video-for="1"] video'), video);
    assert.equal(h.grid.querySelector('[data-warning-for="1"]').value, 'Draft message');
    assert.equal(h.api.rooms[0].disconnected, 0);
    assert.equal(h.api.rooms.length, 20);
});

test('initializing twice and opening twice creates just one room per participant', async() => {
    const h = setup(); h.mod.init(h.config); await h.open(); await h.open(); await h.retry();
    assert.equal(h.api.rooms.length, 20);
    assert.ok(h.api.rooms.every(room => room.disconnected === 0));
});

test('Stop all cancels outstanding starts and remains stopped on polling/retry', async() => {
    const h = setup(); h.api.hold = true; await h.open(); h.click('stop-all');
    h.api.pending.splice(0).forEach(resolve => resolve()); await flush();
    await h.retry(); await h.poll();
    assert.equal(h.api.rooms.length, 0);
    assert.equal(h.api.requests.filter(r => r.args.action === 'start').length, 20);
});

test('closed modal does not start rooms from delayed callbacks', async() => {
    const h = setup(); h.api.hold = true; await h.open();
    h.root.dispatchEvent(new h.w.Event('hidden.bs.modal'));
    h.api.pending.splice(0).forEach(resolve => resolve()); await flush();
    assert.equal(h.api.rooms.length, 0);
});

test('jQuery modal events and search retain matching active rooms', async() => {
    const h = setup(true); await h.open();
    const video = h.grid.querySelector('[data-video-for="1"] video');
    const search = h.root.querySelector('[data-region="webcamguard-live-search"]');
    search.value = 'Student 1'; search.dispatchEvent(new h.w.Event('input')); await flush();
    assert.equal(h.grid.querySelector('[data-video-for="1"] video'), video);
    assert.equal(h.api.rooms[0].disconnected, 0);
    assert.equal(h.api.rooms.length, 20);
});

test('rapid stop/start serializes server actions and ignores old room events', async() => {
    const h = setup(); h.api.hold = true; await h.open();
    h.click('stop-all'); h.api.hold = false; h.click('start-selection');
    h.api.pending.splice(0).forEach(resolve => resolve()); await flush();
    assert.equal(h.api.rooms.length, 20);
    assert.deepEqual(h.api.requests.filter(r => r.args.attemptid === 1).map(r => r.args.action),
        ['start', 'stop', 'start']);
    const oldRoom = h.api.rooms[0]; h.click('stop-all'); await flush();
    h.click('start-selection'); await flush();
    oldRoom.handlers.disconnected(); await h.retry();
    assert.equal(h.api.rooms.length, 40);
});

test('failed connects release room state so manual retry succeeds', async() => {
    const h = setup(); h.api.failConnect = true; await h.open();
    assert.ok(h.api.rooms.every(room => room.disconnected === 1));
    h.api.failConnect = false; h.click('start-selection'); await flush();
    assert.equal(h.grid.querySelectorAll('video').length, 20);
    assert.equal(h.root.querySelector('[data-status-for="1"]').textContent, 'Connected');
});

test('browser renders the real card markup in 4, 3, 2, 1 columns', {
    skip: !process.env.WCG_BROWSER_QA,
}, async() => {
    const {chromium} = require('playwright');
    const browser = await chromium.launch({headless: true, executablePath: process.env.WCG_BROWSER_QA});
    try {
        const page = await browser.newPage();
        const h = setup();
        const css = fs.readFileSync(path.resolve(__dirname, '../../styles.css'), 'utf8');
        await page.setContent(`<style>*{box-sizing:border-box}body{margin:16px;font:14px Arial}${css}</style>${h.grid.outerHTML}`);
        for (const [width, columns] of [[1440, 4], [1000, 3], [700, 2], [500, 1]]) {
            await page.setViewportSize({width, height: 1000});
            const rowCount = await page.locator('[data-tile-for]').evaluateAll(tiles => {
                const first = tiles[0].getBoundingClientRect().top;
                return tiles.filter(tile => Math.abs(tile.getBoundingClientRect().top - first) < 1).length;
            });
            assert.equal(rowCount, columns, `columns at ${width}px`);
        }
        if (process.env.WCG_SCREENSHOT) {
            await page.setViewportSize({width: 1440, height: 1000});
            await page.screenshot({path: process.env.WCG_SCREENSHOT});
        }
    } finally { await browser.close(); }
});
