// Tests for resources/js/paged-table.js with Node's own test runner (`npm run test:js`), no browser needed:
// Alpine and the DOM are replaced by the few pieces the component touches.
import { test } from 'node:test';
import assert from 'node:assert/strict';

let factory;
globalThis.window = { Alpine: { data: (name, fn) => { factory = fn; } } };
globalThis.document = { addEventListener: (event, callback) => callback() };
globalThis.MutationObserver = class { observe() {} disconnect() {} };
await import('../../resources/js/paged-table.js');

const row = (key) => ({ hidden: false, getAttribute: () => key, querySelector: () => null, textContent: key });

// A card whose rows can be swapped, the way a Livewire morph replaces them.
function table(keys) {
    const card = { rows: keys.map(row), querySelectorAll: () => card.rows };
    const pager = factory({ perPage: 10 });
    pager.$el = card;
    pager.init();

    return { pager, card, shown: () => card.rows.filter((r) => !r.hidden).map((r) => r.textContent) };
}

const keys = (prefix, count) => Array.from({ length: count }, (_, i) => `${prefix}${i + 1}`);

test('shows ten rows a page and moves with next and previous', () => {
    const { pager, shown } = table(keys('a', 25));
    assert.deepEqual([pager.pages, pager.from, pager.to], [3, 1, 10]);

    pager.go(pager.page + 1);
    assert.deepEqual(shown(), keys('a', 20).slice(10));

    pager.go(pager.page + 1);
    pager.go(pager.page + 1);
    assert.deepEqual([pager.page, pager.from, pager.to], [3, 21, 25]);

    pager.go(pager.page - 1);
    assert.equal(pager.page, 2);
});

test('a search or filter goes back to page 1', () => {
    const { pager, card, shown } = table(keys('a', 50));
    pager.go(5);

    card.rows = keys('b', 30).map(row);
    pager.render();
    assert.deepEqual([pager.page, pager.pages], [1, 3]);
    assert.deepEqual(shown(), keys('b', 10));
});

test('a re-render of the same rows keeps the page and hides rows again', () => {
    const { pager, card, shown } = table(keys('a', 50));
    pager.go(3);

    card.rows = keys('a', 50).map(row); // Livewire morphs reset the hidden attribute.
    pager.render();
    assert.equal(pager.page, 3);
    assert.deepEqual(shown(), keys('a', 30).slice(20));
});

test('a short result fits on one page', () => {
    const { pager, card, shown } = table(keys('a', 50));
    pager.go(5);

    card.rows = keys('c', 6).map(row);
    pager.render();
    assert.deepEqual([pager.page, pager.pages, pager.from, pager.to], [1, 1, 1, 6]);
    assert.equal(shown().length, 6);
});

test('the empty-state row is not counted', () => {
    const { pager, card } = table([]);
    card.rows = [{ hidden: false, getAttribute: () => null, querySelector: () => ({}), textContent: 'No match' }];
    pager.render();
    assert.deepEqual([pager.total, pager.from, pager.to], [0, 0, 0]);
});
