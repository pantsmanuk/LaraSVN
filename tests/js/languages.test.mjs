import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createRequire } from 'node:module';
import { build } from 'esbuild';
import { prismBuildPlugin } from '../../scripts/prism-config.mjs';
import { languageForFilename } from '../../resources/js/languages.js';
import { tokenLines } from '../../resources/js/token-lines.js';

test('the actual configured bundle contains every mapped grammar and its dependencies', async () => {
    const result = await build({
        stdin: { contents: "export { default as Prism, settings, languageTitles } from 'larasvn:prism';", resolveDir: process.cwd() },
        plugins: [prismBuildPlugin], bundle: true, platform: 'node', format: 'cjs', write: false,
    });
    const directory = mkdtempSync(join(tmpdir(), 'larasvn-grammars-'));
    try {
        const filename = join(directory, 'prism.cjs');
        writeFileSync(filename, result.outputFiles[0].contents);
        const { Prism, settings, languageTitles } = createRequire(import.meta.url)(filename);
        for (const [language, extensions] of Object.entries(settings.languages)) {
            assert(Prism.languages[language], 'Missing bundled grammar: ' + language);
            assert(languageTitles[language], 'Missing title: ' + language);
            for (const extension of extensions) {
                assert.equal(languageForFilename('Example.' + extension.toUpperCase(), settings.languages), language);
            }
        }
        for (const filename of ['README', '.hidden', 'folder.pas/README', 'file.', 'file.unmapped']) {
            assert.equal(languageForFilename(filename, settings.languages), 'plain');
        }
        for (const [filename, source] of [
            ['Unit1.PAS', "unit Demo;\ninterface\nimplementation\nbegin\n  Writeln('café');\nend.\n"],
            ['Project.dpr', "program Demo;\nbegin\nend.\n"],
            ['Example.cs', 'public class Demo { public int Value = 42; }\n'],
            ['Example.tsx', 'const view = <Widget count={42} />;\n'],
            ['settings.json', '{"ok": true, "value": 42}\n'],
        ]) {
            const language = languageForFilename(filename, settings.languages);
            const lines = tokenLines(Prism, source, language);
            assert(lines.flat().some(part => part.classes.includes('keyword') || part.classes.includes('tag') || part.classes.includes('property')), filename);
            assert.equal(lines.map(line => line.map(part => part.text).join('')).join('\n') + '\n', source, filename);
        }
        assert.equal(languageTitles.pascal, 'Pascal');
        assert.equal(languageForFilename('settings.json', settings.languages), 'json');
    } finally {
        rmSync(directory, { recursive: true, force: true });
    }
});
