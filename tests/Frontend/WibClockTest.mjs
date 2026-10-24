import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source = readFileSync(new URL('../../public/wib-clock.js', import.meta.url), 'utf8');

function mount(serverTime, deviceTime = '2026-01-01T00:00:00Z') {
    let now = Date.parse(deviceTime);
    const listeners = {};
    const makeText = () => ({textContent: '', attributes: {}, setAttribute(key, value) { this.attributes[key] = value; }});
    const dates = [makeText(), makeText()];
    const times = [makeText(), makeText()];
    const nodes = dates.map((date, index) => ({dataset: {serverTime}, querySelector(selector) { return selector === '[data-wib-date]' ? date : times[index]; }}));
    let callback;
    let interval;
    class DeviceDate extends Date {
        constructor(...args) { super(...(args.length ? args : [now])); }
        static now() { return now; }
    }
    const document = {visibilityState: 'visible', querySelectorAll: () => nodes, addEventListener(name, handler) { listeners[name] = handler; }};
    vm.runInNewContext(source, {Date: DeviceDate, Intl, document, window: {setInterval(handler, ms) { callback = handler; interval = ms; }}});
    return {dates, times, interval, tick(ms = 1000) { now += ms; callback(); }, resume(ms) { now += ms; listeners.visibilitychange(); }};
}

test('desktop and mobile clocks use server time and Jakarta regardless of device date', () => {
    const app = mount('2026-10-24T12:30:40Z');
    assert.equal(app.times[0].textContent, '19:30:40 WIB');
    assert.equal(app.times[1].textContent, '19:30:40 WIB');
    assert.match(app.dates[0].textContent, /24 Okt 2026/);
    assert.equal(app.interval, 1000);
    app.tick();
    assert.equal(app.times[0].textContent, '19:30:41 WIB');
});

test('clock rolls over at midnight WIB rather than midnight UTC', () => {
    const app = mount('2026-10-24T16:59:59Z');
    assert.equal(app.times[0].textContent, '23:59:59 WIB');
    app.tick();
    assert.equal(app.times[0].textContent, '00:00:00 WIB');
    assert.match(app.dates[0].textContent, /25 Okt 2026/);
    assert.equal(app.times[0].attributes.datetime, '2026-10-24T17:00:00.000Z');
});

test('returning to a background tab catches up without counting missed ticks', () => {
    const app = mount('2026-10-24T12:00:00Z');
    app.resume(60000);
    assert.equal(app.times[0].textContent, '19:01:00 WIB');
});
