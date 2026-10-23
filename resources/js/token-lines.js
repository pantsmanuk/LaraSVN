// Tokenize the complete document, then distribute token text over its source lines.
// Never split generated HTML: each record is text plus trusted grammar classes.
export function tokenLines(prism, text, language) {
    if (text === '') return [];
    const lines = [[]];
    const walk = (token, classes = []) => {
        if (Array.isArray(token)) {
            token.forEach(child => walk(child, classes));
        } else if (typeof token === 'string') {
            const chunks = token.split('\n');
            chunks.forEach((chunk, index) => {
                if (index) lines.push([]);
                if (chunk) lines.at(-1).push({ text: chunk, classes });
            });
        } else {
            walk(token.content, [...classes, token.type, ...[token.alias ?? []].flat()]);
        }
    };
    const grammar = prism.languages[language];
    walk(grammar ? prism.tokenize(text, grammar) : text);
    if (text.endsWith('\n')) lines.pop();
    return lines;
}
