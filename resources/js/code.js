import Prism, { settings, languageTitles } from 'larasvn:prism';
import { languageForFilename } from './languages.js';
import { tokenLines } from './token-lines.js';
import { renderPart, enhanceRows, activateColors, markWhitespace } from './prism-rows.js';

const enabled = name => settings.plugins.includes(name);
// Rewrite Prism's generated swatches before HTML parsing; apply their validated
// colors through DOM properties after insertion without weakening the style CSP.
Prism.hooks.add('wrap', env => {
    env.content = env.content.replace(/<span class="inline-color" style="background-color:([^"]+);">/g, '<span class="inline-color" data-prism-color="$1">');
});
for (const region of document.querySelectorAll('.code-scroll')) {
    region.classList.add('prism-surface');
    region.classList.toggle('prism-no-line-numbers', !enabled('line-numbers'));
    if (!enabled('line-numbers')) {
        for (const gutter of region.querySelectorAll('.gutter')) gutter.tabIndex = -1;
    }
}

function showLanguage(language, toolbar) {
    if (!enabled('show-language') || !toolbar) return;
    const label = document.createElement('span');
    label.className = 'source-language';
    label.textContent = languageTitles[language];
    toolbar.prepend(label);
}

function enableCopy(button, text, status) {
    button.hidden = !enabled('copy-to-clipboard');
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(text);
            status.textContent = 'Source copied.';
        } catch {
            status.textContent = 'Clipboard unavailable. Select the source text to copy it.';
        }
    });
}

const payload = document.querySelector('#source-documents');
if (payload) {
    const documents = JSON.parse(payload.textContent);
    const filename = document.querySelector('[data-filename]')?.dataset.filename ?? '';
    const language = languageForFilename(filename, settings.languages);
    try {
        const tokens = Object.fromEntries(Object.entries(documents).map(([name, text]) => [name, tokenLines(Prism, text, language)]));
        for (const code of document.querySelectorAll('code[data-document]')) {
            const line = tokens[code.dataset.document][Number(code.dataset.line) - 1];
            if (!line) throw new Error('Source line mismatch');
            const fragment = document.createDocumentFragment();
            for (const part of line) {
                fragment.append(renderPart(Prism, part, language));
            }
            if (enabled('show-invisibles') && (Number(code.dataset.line) < tokens[code.dataset.document].length || documents[code.dataset.document].endsWith('\n'))) {
                const ending = document.createElement('span');
                ending.className = 'token lf';
                ending.setAttribute('aria-hidden', 'true');
                fragment.append(ending);
            }
            code.replaceChildren(fragment);
            if (enabled('show-invisibles')) markWhitespace(code);
        }
        enhanceRows(Prism, language, settings.plugins);
        showLanguage(language, document.querySelector('.code-toolbar'));
    } catch {
        document.querySelector('#highlight-status').textContent = 'Syntax highlighting is unavailable. Source remains readable.';
    }
    for (const button of document.querySelectorAll('[data-copy]')) {
        enableCopy(button, documents[button.dataset.copy], document.querySelector('#copy-status'));
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
    const text = patch.textContent;
    try {
        patch.classList.add('language-diff', 'no-whitespace-normalization');
        patch.parentElement.classList.toggle('match-braces', enabled('match-braces'));
        patch.classList.toggle('diff-highlight', enabled('diff-highlight'));
        Prism.highlightElement(patch);
        if (enabled('show-invisibles')) markWhitespace(patch);
        activateColors(patch);
    } catch {
        document.querySelector('#highlight-status').textContent = 'Syntax highlighting is unavailable. Patch remains readable.';
    }
    const toolbar = patch.closest('.code-scroll').previousElementSibling;
    showLanguage('diff', toolbar);
    if (enabled('copy-to-clipboard') && toolbar) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'Copy patch';
        const status = document.createElement('span');
        status.setAttribute('role', 'status');
        toolbar.append(button, status);
        enableCopy(button, text, status);
    }
}
