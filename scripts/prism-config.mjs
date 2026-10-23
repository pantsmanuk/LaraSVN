import { readFileSync } from 'node:fs';
import components from 'prismjs/components.js';
import getLoader from 'prismjs/dependencies.js';

export const supportedPlugins = ['line-numbers', 'match-braces', 'show-language', 'copy-to-clipboard', 'inline-color', 'diff-highlight', 'normalize-whitespace', 'show-invisibles', 'previewers'];
const themes = ['prism', 'prism-coy', 'prism-dark', 'prism-funky', 'prism-okaidia', 'prism-solarizedlight', 'prism-tomorrow', 'prism-twilight'];

export function readPrismConfig() {
    return validatePrismConfig(JSON.parse(readFileSync('prism.config.json', 'utf8')));
}

export function validatePrismConfig(settings) {
    for (const key of Object.keys(settings)) {
        if (!['theme', 'darkTheme', 'plugins', 'languages'].includes(key)) throw Error('Unknown Prism setting: ' + key);
    }
    for (const key of ['theme', 'darkTheme']) {
        if (typeof settings[key] !== 'string' || !themes.some(name => [name + '.css', name + '.min.css'].includes(settings[key]))) {
            throw Error('Invalid Prism ' + key + ': choose a bundled Prism CSS theme');
        }
    }
    if (!Array.isArray(settings.plugins) || settings.plugins.some(name => !supportedPlugins.includes(name))) {
        throw Error('Invalid Prism plugins: supported values are ' + supportedPlugins.join(', '));
    }
    if (!settings.languages || typeof settings.languages !== 'object' || Array.isArray(settings.languages)) {
        throw Error('Invalid Prism languages: map grammar IDs to arrays of file extensions');
    }
    const used = new Set();
    for (const [language, extensions] of Object.entries(settings.languages)) {
        if (language === 'meta' || !Object.hasOwn(components.languages, language)) throw Error('Unknown Prism language: ' + language);
        if (!Array.isArray(extensions) || !extensions.length) throw Error('Invalid Prism extensions for ' + language);
        for (const extension of extensions) {
            if (typeof extension !== 'string' || !/^[a-z0-9]+$/.test(extension) || used.has(extension)) {
                throw Error('Invalid or duplicate Prism extension: ' + extension);
            }
            used.add(extension);
        }
    }
    return settings;
}

// Apply upstream palettes to LaraSVN's reading surfaces; its stylesheet owns geometry.
function themeCss(filename) {
    const css = readFileSync('node_modules/prismjs/themes/' + filename.replace('.min.css', '.css'), 'utf8')
        // Inline-code rules stay untouched: the repository view is a block.
        .replace(/(?<!:not\(pre\) > )code\[class\*="language-"\]/g, '.prism-surface')
        .replaceAll('pre[class*="language-"]', '.prism-surface');
    // Preserve the default palette's hues with sufficient contrast on source/diff backgrounds.
    return css + (['prism.css', 'prism.min.css'].includes(filename) ? `
        .token.comment,.token.prolog,.token.doctype,.token.cdata { color:#596a72; }
        .token.punctuation { color:#53616b; }
        .token.selector,.token.attr-name,.token.string,.token.char,.token.builtin { color:#496c00; }
        .token.operator,.token.entity,.token.url,.language-css .token.string,.style .token.string { color:#785323; background:none; }
        .token.atrule,.token.attr-value,.token.keyword { color:#00638f; }
        .token.function,.token.class-name { color:#af2347; }
        .token.regex,.token.important,.token.variable { color:#935b00; }
    ` : '');
}

export const prismBuildPlugin = {
    name: 'larasvn-prism',
    setup(build) {
        build.onResolve({ filter: /^larasvn:prism$/ }, () => ({ path: 'settings', namespace: 'prism' }));
        build.onResolve({ filter: /^larasvn:prism-style$/ }, () => ({ path: 'styles', namespace: 'prism' }));
        build.onLoad({ filter: /.*/, namespace: 'prism' }, ({ path }) => {
            const settings = readPrismConfig();
            const native = settings.plugins.filter(name => !['line-numbers', 'show-language', 'copy-to-clipboard', 'show-invisibles'].includes(name));
            const watchFiles = ['prism.config.json'];
            if (path === 'settings') {
                const requested = [...Object.keys(settings.languages), 'diff'];
                if (native.some(name => ['inline-color', 'previewers'].includes(name))) requested.push('css-extras');
                const grammars = getLoader(components, requested, ['markup', 'css', 'clike', 'javascript']).getIds();
                const languageTitles = Object.fromEntries(Object.keys(settings.languages).map(id => {
                    const entry = components.languages[id];
                    return [id, typeof entry === 'string' ? entry : entry.title];
                }));
                return {
                    loader: 'js', resolveDir: process.cwd(), watchFiles,
                    contents: [
                        "import Prism from 'prismjs';",
                        ...grammars.map(id => `import 'prismjs/components/prism-${id}.js';`),
                        ...native.map(name => `import 'prismjs/plugins/${name}/prism-${name}.js';`),
                        'Prism.manual = true;',
                        "Prism.plugins.NormalizeWhitespace?.setDefaults({ 'remove-trailing': false, 'remove-indent': false, 'left-trim': false, 'right-trim': false });",
                        `export const settings = ${JSON.stringify(settings)};`,
                        `export const languageTitles = ${JSON.stringify({ ...languageTitles, plain: 'Plain text', diff: 'Diff' })};`,
                        'export default Prism;',
                    ].join('\n'),
                };
            }
            const light = themeCss(settings.theme);
            const dark = themeCss(settings.darkTheme);
            const styled = native.filter(name => name !== 'normalize-whitespace');
            if (settings.plugins.includes('show-invisibles')) styled.push('show-invisibles');
            const plugins = styled
                .map(name => `@import 'prismjs/plugins/${name}/prism-${name}.css';`).join('\n');
            return {
                loader: 'css', resolveDir: process.cwd(), watchFiles,
                contents: `${plugins}\n@layer prism {
                    :root[data-appearance=light] { ${light} }
                    :root[data-appearance=dark] { ${dark} }
                    @media (prefers-color-scheme: light) { :root:not([data-appearance]) { ${light} } }
                    @media (prefers-color-scheme: dark) { :root:not([data-appearance]) { ${dark} } }
                }`,
            };
        });
    },
};
