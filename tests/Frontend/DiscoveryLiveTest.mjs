import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source = readFileSync(new URL('../../public/discovery-live.js', import.meta.url), 'utf8');
const flush = async () => { for (let i = 0; i < 4; i++) await new Promise(resolve => setImmediate(resolve)); };
const json = (data, status = 200) => new Response(JSON.stringify(data), {status, headers: {'content-type': 'application/json'}});
function mount(handler, restored = null) {
    const nodes = new Map();
    const make = (id, dataset = {}) => {
        const node = {dataset, hidden: false, textContent: '', innerHTML: '', disabled: false, listeners: {}, attributes: {},
            setAttribute(key, value) { this.attributes[key] = value; },
            addEventListener(event, callback) { this.listeners[event] = callback; },
            querySelector() { return label; }};
        nodes.set(id, node);
        return node;
    };
    const label = {textContent: 'Jalankan Pencarian'};
    const panel = make('discovery-live-status', {storageKey: 'test-user'});
    const health = make('discovery-live-health');
    const button = make('discovery-run-button', {unavailable: 'false'});
    const stop = make('discovery-stop-tracking');
    const form = make('discovery-run-form');
    form.action = 'https://example.test/discovery/run';
    form.values = {source: 'python', keyword_id: '2'};
    const rows = make('discovery-run-rows');
    const metric = {textContent: ''};
    const timers = new Map();
    let timerId = 0;
    const storage = new Map(restored ? [['test-user', JSON.stringify(restored)]] : []);
    const document = {visibilityState: 'visible', getElementById: id => nodes.get(id), querySelector: () => metric, addEventListener() {}};
    const context = {document, URL, AbortSignal, console, Date,
        window: {location: {href: 'https://example.test/discovery'}, setTimeout(callback) { timers.set(++timerId, callback); return timerId; }, clearTimeout(id) { timers.delete(id); }},
        sessionStorage: {getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key)},
        FormData: class { constructor(input) { this.values = input.values; } get(key) { return this.values[key]; } },
        fetch: handler};
    vm.runInNewContext(source, context);
    return {panel, health, button, stop, form, rows, metric, storage, timers,
        async submit() { await form.listeners.submit({preventDefault() {}}); await flush(); },
        async tick() { const [id, callback] = [...timers][0]; timers.delete(id); await callback(); await flush(); }};
}

const snapshot = tracked => ({history_html: '<tr><td>updated</td></tr>', metrics: {totalRuns: 3}, tracked});

test('queued request progresses to running and completion without refresh or duplicate timers', async () => {
    let tracked = null;
    const app = mount(async (_, options = {}) => options.method === 'POST'
        ? json({status: 'queued', source: 'python', keyword_id: 2, after_id: 1, message: 'Queued'}, 202)
        : json(snapshot(tracked)));
    await flush();
    await app.submit();
    assert.equal(app.button.disabled, true);
    assert.equal(app.storage.size, 1);
    assert.equal(app.timers.size, 1);
    tracked = {status: 'running'};
    await app.tick();
    assert.match(app.panel.textContent, /sedang berjalan/);
    tracked = {status: 'completed', source_type: 'python', fetched_count: 4, added_count: 1};
    await app.tick();
    assert.match(app.panel.textContent, /4 postingan diterima, 1 lead baru/);
    assert.equal(app.button.disabled, false);
    assert.equal(app.storage.size, 0);
    assert.equal(app.metric.textContent, '3');
    assert.match(app.rows.innerHTML, /updated/);
});

test('synchronous Threads zero response reports API scope warning and reenables button', async () => {
    const app = mount(async (_, options = {}) => options.method === 'POST'
        ? json({status: 'completed', source: 'threads', fetched: 0, added: 0}) : json(snapshot(null)));
    await flush();
    app.form.values.source = 'threads';
    await app.submit();
    assert.match(app.panel.textContent, /Threads API mengembalikan 0/);
    assert.match(app.panel.textContent, /threads_keyword_search/);
    assert.equal(app.button.disabled, false);
});

test('permission failure remains an error instead of a zero result', async () => {
    const app = mount(async (_, options = {}) => options.method === 'POST'
        ? json({message: 'Periksa izin Threads'}, 502) : json(snapshot(null)));
    await flush();
    await app.submit();
    assert.equal(app.panel.attributes.role, 'alert');
    assert.equal(app.panel.textContent, 'Periksa izin Threads');
    assert.equal(app.button.disabled, false);
});

test('restored queued request warns about delayed worker and can stop tracking', async () => {
    const app = mount(async () => json(snapshot(null)), {source: 'python', keyword_id: 2, after_id: 1, started: Date.now() - 180000});
    await flush();
    assert.match(app.panel.textContent, /Periksa worker/);
    assert.equal(app.button.disabled, true);
    app.stop.listeners.click();
    assert.equal(app.button.disabled, false);
    assert.equal(app.storage.size, 0);
    assert.match(app.panel.textContent, /tetap dapat berjalan/);
});

test('polling connection failures are visible and retry without losing pending state', async () => {
    const app = mount(async () => { throw new Error('Network unavailable'); }, {source: 'python', keyword_id: 2, after_id: 1, started: Date.now()});
    await flush();
    assert.match(app.health.textContent, /Network unavailable/);
    assert.equal(app.button.disabled, true);
    assert.equal(app.timers.size, 1);
});
