import test from 'node:test';
import assert from 'node:assert/strict';
import Prism from 'prismjs';
import 'prismjs/components/prism-markup-templating.js';
import 'prismjs/components/prism-php.js';
import { tokenLines } from '../../resources/js/token-lines.js';

test('full-document PHP tokens preserve multiline comment context inside a compact hunk', () => {
    const source = '<?php\n/*\n' + Array.from({length:30}, (_,i) => ` * line ${i}`).join('\n') + '\n */\necho "café 😀";\n';
    const lines = tokenLines(Prism, source, 'php');
    assert(lines[20].every(part => part.classes.includes('comment')));
    assert.equal(lines.map(line => line.map(part => part.text).join('')).join('\n') + '\n', source);
});

test('heredoc, blank lines, Unicode, and no final newline round-trip unchanged', () => {
    const source = '<?php\n$x = <<<TEXT\nnaïve café 😀\n\nTEXT;\necho $x;';
    const lines = tokenLines(Prism, source, 'php');
    assert.equal(lines.map(line => line.map(part => part.text).join('')).join('\n'), source);
    assert(lines[2].some(part => part.classes.includes('string')));
});

test('unknown grammar, empty source, and a single blank line stay readable', () => {
    assert.deepEqual(tokenLines(Prism, '', 'php'), []);
    assert.deepEqual(tokenLines(Prism, '\n', 'php'), [[]]);
    const source = '<script>alert("untrusted")</script>\n\tlast';
    assert.equal(tokenLines(Prism, source, 'unknown').map(line => line.map(p => p.text).join('')).join('\n'), source);
});
