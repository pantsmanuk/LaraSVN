import test from 'node:test';
import assert from 'node:assert/strict';
import { readPrismConfig, validatePrismConfig } from '../../scripts/prism-config.mjs';

test('Prism settings accept an empty plugin list and reject unsupported assets', () => {
    const settings = readPrismConfig();
    assert.deepEqual(validatePrismConfig({ ...settings, plugins: [] }).plugins, []);
    for (const change of [
        { theme: '../app.css' }, { darkTheme: 'https://example.test/theme.css' },
        { plugins: ['unknown'] }, { plugins: 'all' }, { extra: true },
    ]) assert.throws(() => validatePrismConfig({ ...settings, ...change }), /Prism/);
});
