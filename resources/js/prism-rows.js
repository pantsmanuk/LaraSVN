// Keep upstream plugins on Prism's lifecycle while retaining server-rendered rows.
export function renderPart(Prism, part, language) {
    if (!part.classes.length) return document.createTextNode(part.text);
    const template = document.createElement('template');
    template.innerHTML = Prism.Token.stringify(Prism.util.encode(new Prism.Token(part.classes[0], part.text, part.classes.slice(1))), language);
    return template.content;
}

export function activateColors(container) {
    for (const swatch of container.querySelectorAll('[data-prism-color]')) {
        swatch.style.backgroundColor = swatch.dataset.prismColor;
        delete swatch.dataset.prismColor;
    }
}

// Decorate rendered text, not grammars. Prism's recursive show-invisibles hook
// cannot handle optional/null grammar entries used by languages such as Pascal.
export function markWhitespace(container) {
    const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    for (const node of nodes) {
        if (!/[ \t\r\n]/.test(node.textContent)) continue;
        const fragment = document.createDocumentFragment();
        for (const part of node.textContent.split(/([ \t\r\n])/)) {
            if (!part) continue;
            const type = { ' ': 'space', '\t': 'tab', '\r': 'cr', '\n': 'lf' }[part];
            if (type) {
                const span = document.createElement('span');
                span.className = 'token ' + type;
                span.textContent = part;
                fragment.append(span);
            } else {
                fragment.append(document.createTextNode(part));
            }
        }
        node.replaceWith(fragment);
    }
}

export function enhanceRows(Prism, language, plugins) {
    const region = document.querySelector('.code-scroll');
    if (!region) return;
    if (plugins.includes('match-braces')) {
        for (const side of ['new', 'old']) {
            let group = [];
            let previous = 0;
            const finish = () => {
                if (!group.length) return;
                const pre = document.createElement('pre');
                pre.className = 'match-braces';
                const code = pre.appendChild(document.createElement('code'));
                const rows = group.map(row => ({ row, content: code.appendChild(document.createElement('span')) }));
                for (const { row, content } of rows) while (row.firstChild) content.append(row.firstChild);
                Prism.hooks.run('complete', { element: code, language });
                // Move nodes, preserving Prism's brace listeners and identifiers.
                for (const { row, content } of rows) while (content.firstChild) row.append(content.firstChild);
                group = [];
            };
            for (const row of document.querySelectorAll(`code[data-document="${side}"]`)) {
                const number = Number(row.dataset.line);
                // Elided diff lines are a boundary; never pair across a hidden hunk.
                if (number !== previous + 1) finish();
                group.push(row);
                previous = number;
            }
            finish();
        }
        region.addEventListener('mousedown', () => {
            for (const brace of region.querySelectorAll('.brace-selected')) brace.classList.remove('brace-selected');
        });
    }
    activateColors(region);
    if (plugins.includes('previewers')) Prism.plugins.Previewer.initEvents(region, language);
    const focusable = [];
    if (plugins.includes('match-braces')) focusable.push('.brace-open[id]', '.brace-close[id]');
    if (plugins.includes('previewers')) focusable.push('.token.color', '.token.gradient', '.token.angle', '.token.time', '.token.easing');
    if (!focusable.length) return;
    for (const token of region.querySelectorAll(focusable.join(', '))) {
        token.tabIndex = 0;
        token.addEventListener('focus', () => {
            token.dispatchEvent(new MouseEvent('mouseenter'));
            token.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
        });
        token.addEventListener('blur', () => {
            token.dispatchEvent(new MouseEvent('mouseleave'));
            token.dispatchEvent(new MouseEvent('mouseout', { bubbles: true }));
        });
    }
}
