import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const code = readFileSync(new URL('../../resources/js/theme.js', import.meta.url), 'utf8');
function setup(saved, dark = false, blocked = false) {
    const events = {};
    const media = { matches: dark, addEventListener: (_, callback) => { events.system = callback; } };
    const storage = { getItem: () => { if (blocked) throw Error('blocked'); return saved; }, setItem: (_, value) => { if (blocked) throw Error('blocked'); saved = value; } };
    const control = { value: '', closest: () => ({ hidden: true }), addEventListener: (_, callback) => { events.select = callback; } };
    const document = { documentElement: { dataset: {} }, querySelector: () => control, addEventListener: (_, callback) => { events.ready = callback; } };
    const window = { matchMedia: () => media, addEventListener: (_, callback) => { events.storage = callback; } };
    runInNewContext(code, { window, document, localStorage: storage });
    return { events, control, media, document, stored: () => saved };
}

test('saved overrides apply before DOM readiness and System follows changes', () => {
    const page = setup('dark');
    assert.equal(page.document.documentElement.dataset.appearance, 'dark');
    page.events.ready();
    assert.equal(page.control.value, 'dark');
    page.events.system();
    assert.equal(page.document.documentElement.dataset.appearance, 'dark');
    page.control.value = 'system';
    page.events.select();
    assert.equal(page.stored(), 'system');
    assert.equal(page.document.documentElement.dataset.appearance, 'light');
    page.media.matches = true;
    page.events.system();
    assert.equal(page.document.documentElement.dataset.appearance, 'dark');
    assert.equal(page.control.value, 'system');
});

test('invalid values, cross-tab changes, and cleared storage resolve honestly', () => {
    const page = setup('invalid', true);
    assert.equal(page.document.documentElement.dataset.appearance, 'dark');
    page.events.ready();
    assert.equal(page.control.value, 'system');
    page.events.storage({ key: 'larasvn.appearance', newValue: 'light' });
    assert.equal(page.document.documentElement.dataset.appearance, 'light');
    page.events.storage({ key: null, newValue: null });
    assert.equal(page.control.value, 'system');
    assert.equal(page.document.documentElement.dataset.appearance, 'dark');
});

test('unavailable storage preserves page-local control', () => {
    const page = setup(null, false, true);
    page.events.ready();
    page.control.value = 'dark';
    page.events.select();
    assert.equal(page.document.documentElement.dataset.appearance, 'dark');
});
