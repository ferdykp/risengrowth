import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');

function mount(open = false) {
    const listeners = [];
    let opens = 0;
    let focused = false;
    const phrase = {value: '', focus() { focused = true; }, scrollIntoView() {}, dispatchEvent() {}};
    const form = {elements: {phrase, category: {value: 'direct_demand'}, notes: {value: 'Catatan saya'}, search_type: {value: 'TOP'}}};
    const dialog = {open, querySelector: () => form, showModal() { this.open = true; opens++; }};
    const document = {addEventListener(name, handler) { if (name === 'click') listeners.push(handler); }, getElementById: () => dialog};
    vm.runInNewContext(source, {document, Event: class {}});
    return {form, get opens() { return opens; }, get focused() { return focused; }, click(phrase, category) {
        const suggestion = {dataset: {keywordSuggestion: phrase, keywordCategory: category}};
        const event = {target: {closest(selector) { return selector === '[data-keyword-suggestion]' ? suggestion : null; }}};
        listeners.forEach(handler => handler(event));
    }};
}

test('suggestion opens a prefilled form without submitting or replacing other fields', () => {
    const app = mount();
    app.click('butuh website', 'direct_demand');
    assert.equal(app.form.elements.phrase.value, 'butuh website');
    assert.equal(app.form.elements.category.value, 'direct_demand');
    assert.equal(app.form.elements.notes.value, 'Catatan saya');
    assert.equal(app.form.elements.search_type.value, 'TOP');
    assert.equal(app.opens, 1);
    assert.equal(app.focused, true);
});

test('suggestions inside an open modal update the category without reopening it', () => {
    const app = mount(true);
    app.click('cerita ngoding', 'developer_community');
    assert.equal(app.form.elements.phrase.value, 'cerita ngoding');
    assert.equal(app.form.elements.category.value, 'developer_community');
    assert.equal(app.opens, 0);
});
