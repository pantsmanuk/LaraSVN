import Prism from 'prismjs';
import 'prismjs/components/prism-markup-templating.js';
import 'prismjs/components/prism-php.js';
import 'prismjs/components/prism-diff.js';
import { tokenLines } from './token-lines.js';

const payload = document.querySelector('#source-documents');
if (payload) {
    const documents = JSON.parse(payload.textContent);
    const language = document.querySelector('[data-language]')?.dataset.language ?? 'plain';
    try {
        const tokens = Object.fromEntries(Object.entries(documents).map(([name, text]) => [name, tokenLines(Prism, text, language)]));
        for (const code of document.querySelectorAll('code[data-document]')) {
            const line = tokens[code.dataset.document][Number(code.dataset.line) - 1];
            if (!line) throw new Error('Source line mismatch');
            const fragment = document.createDocumentFragment();
            for (const part of line) {
                const node = document.createElement('span');
                if (part.classes.length) node.className = 'token ' + part.classes.join(' ');
                node.textContent = part.text;
                fragment.append(node);
            }
            code.replaceChildren(fragment);
        }
    } catch {
        document.querySelector('#highlight-status').textContent = 'Syntax highlighting is unavailable. Source remains readable.';
    }
    for (const button of document.querySelectorAll('[data-copy]')) {
        button.hidden = false;
        button.addEventListener('click', async () => {
            const status = document.querySelector('#copy-status');
            try {
                await navigator.clipboard.writeText(documents[button.dataset.copy]);
                status.textContent = 'Source copied.';
            } catch {
                status.textContent = 'Clipboard unavailable. Select the source text to copy it.';
            }
        });
    }
}

// Search is committed by GET navigation, with no background request races.
for (const button of document.querySelectorAll('[data-clear-search]')) {
    button.hidden = false;
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.clearSearch);
        input.value = '';
        input.focus();
        const form = input.form;
        form.action = form.action.split('#')[0] + '#' + input.id;
        form.requestSubmit();
    });
}
if (['#filename-search', '#history-search'].includes(window.location.hash)) {
    document.querySelector(window.location.hash)?.focus();
}

// Native SVN patches include directory and property changes as well as file text.
const patch = document.querySelector('[data-patch]');
if (patch) {
    try {
        patch.innerHTML = Prism.highlight(patch.textContent, Prism.languages.diff, 'diff');
    } catch {
        document.querySelector('#highlight-status').textContent = 'Syntax highlighting is unavailable. Patch remains readable.';
    }
}
