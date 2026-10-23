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
    const env = { code: text, grammar: prism.languages[language], language };
    // Run grammar plugins once for the entire document, before distributing rows.
    if (prism.plugins.NormalizeWhitespace) {
        env.code = prism.plugins.NormalizeWhitespace.normalize(text, {
            'remove-trailing': false, 'remove-indent': false, 'left-trim': false, 'right-trim': false,
        });
    }
    prism.hooks.run('before-highlight', env);
    prism.hooks.run('before-tokenize', env);
    env.tokens = env.grammar ? prism.tokenize(env.code, env.grammar) : [env.code];
    prism.hooks.run('after-tokenize', env);
    walk(env.tokens);
    if (text.endsWith('\n')) lines.pop();
    return lines;
}
