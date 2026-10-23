import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
import { readPrismConfig } from './prism-config.mjs';

const origin = (process.env.LARASVN_BASE_URL || 'http://larasvn.test').replace(/\/$/, '');
const fixtureUrl = path => {
    const url = new URL(origin + path);
    url.searchParams.set('repname', 'fixture');
    return url.href;
};
let browser;
const installTransport = async context => {
    if (process.env.LARASVN_BASE_URL) return; // Real local HTTP/Apache mount verification.
    await context.route(origin + '/**', async route => {
        const url = new URL(route.request().url());
        if (['/build/theme.js', '/build/code.js', '/build/app.css'].includes(url.pathname)) {
            return route.fulfill({ body: await readFile('public' + url.pathname), contentType: url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript' });
        }
        const response = JSON.parse(execFileSync('php', ['scripts/render-request.php', url.href], { maxBuffer: 4_000_000 }).toString());
        const headers = Object.fromEntries(Object.entries(response.headers).filter(([key]) => key !== 'set-cookie').map(([key, values]) => [key, values.join(', ')]));
        await route.fulfill({ status: response.status, headers, body: Buffer.from(response.bodyBase64, 'base64') });
    });
};
try {
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE || undefined, headless: true });
    const context = await browser.newContext({ colorScheme: 'light', viewport: { width: 1440, height: 1000 } });
    await installTransport(context);
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(fixtureUrl('/filedetails.php'));
    await page.waitForSelector('code .token');
    await page.keyboard.press('Tab');
    assert.equal(await page.locator('.skip-link').evaluate(element => element === document.activeElement), true);
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('#main').evaluate(element => element === document.activeElement), true);
    assert.equal(await page.locator('.source-row').count(), 49);
    const initial = await page.evaluate(() => document.documentElement.dataset.appearance);
    assert.equal(initial, 'light');
    await page.selectOption('#appearance', 'dark');
    await page.reload();
    assert.equal(await page.locator('#appearance').inputValue(), 'dark');
    assert.equal(await page.evaluate(() => document.documentElement.dataset.appearance), 'dark');
    const sibling = await context.newPage();
    await sibling.goto(fixtureUrl('/blame.php'));
    await page.selectOption('#appearance', 'light');
    await sibling.waitForFunction(() => document.documentElement.dataset.appearance === 'light');
    await page.selectOption('#appearance', 'system');
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.waitForFunction(() => document.documentElement.dataset.appearance === 'dark');
    assert.equal(await page.locator('#appearance').inputValue(), 'system');
    await page.goto(fixtureUrl('/diff.php?rev=20'));
    await page.waitForSelector('.diff-side code .token');
    assert(await page.locator('.new code[data-line="23"] .comment').count() > 0);
    assert(await page.locator('.old code[data-line="23"] .comment').count() > 0);
    await page.screenshot({ path: '/tmp/larasvn-diff-dark.png', fullPage: true });
    await page.selectOption('#appearance', 'light');
    await page.goto(fixtureUrl('/blame.php'));
    await page.waitForSelector('code .token');
    assert.equal(await page.locator('.attribution').count(), 49);
    await page.screenshot({ path: '/tmp/larasvn-blame-light.png', fullPage: true });
    const copiedText = await page.evaluate(() => [...document.querySelectorAll('code[data-document="new"]')].map(node => node.textContent).join('\n') + '\n');
    const source = await page.locator('#source-documents').textContent();
    assert.equal(copiedText, JSON.parse(source).new);
    await page.goto(fixtureUrl('/listing.php'));
    await page.getByRole('link', { name: 'trunk/', exact: true }).click();
    await page.getByRole('link', { name: 'src/', exact: true }).click();
    await page.getByRole('link', { name: 'renamed.php', exact: true }).click();
    await page.waitForSelector('code .token');
    assert(await page.locator('body').textContent().then(text => text.includes('Bonjour café')));
    await page.goto(fixtureUrl('/log.php'));
    await page.locator('.pagination a[rel="next"]').click();
    assert(await page.locator('.pagination').textContent().then(text => text.includes('Page 2 of 2')));
    await page.goto(fixtureUrl('/search.php?search=*.php'));
    assert(await page.locator('.data-table tbody tr').count() === 2);
    await page.getByRole('button', { name: 'Clear filename search', exact: true }).click();
    assert.equal(await page.locator('#filename-search').inputValue(), '');
    assert.equal(await page.locator('#filename-search').evaluate(element => element === document.activeElement), true);
    await page.goto(fixtureUrl('/revision.php?rev=25'));
    assert(await page.locator('.changed-paths').textContent().then(text => text.includes('before deletion')));
    await page.locator('.changed-paths a').first().click();
    await page.waitForSelector('code .token');
    assert(await page.locator('body').textContent().then(text => text.includes('Bonjour café')));
    await page.goto(fixtureUrl('/comp.php?repname=fixture&compare[0]=/example.php@10&compare[1]=/example.php@20'));
    await page.waitForSelector('[data-patch] .token');
    assert(await page.locator('[data-patch]').textContent().then(text => text.includes('+ * New message')));
    await page.getByRole('link', { name: 'Reverse comparison', exact: true }).click();
    assert(await page.locator('[data-patch]').textContent().then(text => text.includes('- * New message')));
    await page.selectOption('#appearance', 'dark');
    await page.screenshot({ path: '/tmp/larasvn-compare-dark.png', fullPage: true });
    await page.goto(fixtureUrl('/browse.php/fixture/docs'));
    assert(await page.locator('body').textContent().then(text => text.includes('README.md')));
    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('link', { name: 'ZIP', exact: true }).click(),
    ]);
    assert.equal(download.suggestedFilename(), 'fixture-docs.r27.zip');
    await page.goto(fixtureUrl('/rss.php?max=2'));
    assert((await page.content()).includes('Subversion revision history'));
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(fixtureUrl('/diff.php'));
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    await page.screenshot({ path: '/tmp/larasvn-diff-mobile.png', fullPage: true });
    await page.goto(fixtureUrl('/comp.php?compare[0]=/example.php@10&compare[1]=/example.php@20'));
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    await page.screenshot({ path: '/tmp/larasvn-compare-mobile.png', fullPage: true });
    await page.goto(fixtureUrl('/filedetails.php?rev=9999'));
    assert(await page.locator('h1').textContent() === '404');
    const noJs = await browser.newContext({ javaScriptEnabled: false });
    await installTransport(noJs);
    const plain = await noJs.newPage();
    await plain.goto(fixtureUrl('/blame.php'));
    assert.equal(await plain.locator('.source-row').count(), 49);
    assert.equal(await plain.locator('code .token').count(), 0);
    assert(await plain.locator('body').textContent().then(text => text.includes('New message')));
    await plain.goto(fixtureUrl('/listing.php'));
    await plain.getByRole('link', { name: 'docs/', exact: true }).click();
    assert(await plain.locator('body').textContent().then(text => text.includes('README.md')));
    await plain.goto(fixtureUrl('/comp.php?compare[0]=/example.php@10&compare[1]=/example.php@20'));
    assert(await plain.locator('[data-patch]').textContent().then(text => text.includes('+ * New message')));
    await page.setViewportSize({ width: 1440, height: 1000 });
    for (const path of ['/listing.php', '/filedetails.php', '/diff.php', '/blame.php', '/log.php', '/revision.php?rev=25', '/search.php', '/comp.php']) {
        await page.goto(fixtureUrl(path));
        assert.equal(await page.getByRole('main').count(), 1);
        assert.equal(await page.getByRole('heading', { level: 1 }).count(), 1);
        assert.equal(await page.locator('html').getAttribute('lang'), 'en');
        const unlabelled = await page.locator('input:not([type="hidden"]), select').evaluateAll(elements =>
            elements.filter(element => !element.labels?.length && !element.getAttribute('aria-label')).map(element => element.name));
        assert.deepEqual(unlabelled, [], 'Every visible input needs a label: ' + path);
    }
    assert.deepEqual(errors, []);
    // Exercise plugin behavior with exact whitespace and hostile source text.
    // The synthetic page still loads the real built assets under the application's CSP.
    const settings = readPrismConfig();
    const enabled = name => settings.plugins.includes(name);
    const css = '/* <img src=x onerror="window.sourceExecuted=true"> */\n.box {\n\tcolor: #ff0000;  \n\ttransform: rotate(45deg);\n\ttransition: color 1s ease;\n}\n';
    const escape = text => text.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
    const codePage = (filename, documents, mode = 'source') => `<!doctype html><html data-appearance="light"><head><meta charset="utf-8"><link rel="stylesheet" href="${origin}/build/app.css"><script type="module" src="${origin}/build/code.js"></script></head><body>
        <div class="code-toolbar"><button data-copy="new" hidden>Copy source</button><span id="copy-status"></span></div>
        <div class="code-scroll prism-surface"><div class="${mode}" data-filename="${escape(filename).replaceAll('"', '&quot;')}">${Object.entries(documents).map(([side, text]) => text.replace(/\n$/, '').split('\n').map((line, i) => `<div class="source-row">${mode === 'blame' ? '<span class="attribution">r1 fixture</span>' : ''}<a class="gutter">${i + 1}</a><code data-document="${side}" data-line="${i + 1}">${escape(line)}</code></div>`).join('')).join('')}</div></div>
        <p id="highlight-status"></p><script type="application/json" id="source-documents">${JSON.stringify(documents).replaceAll('<', '\\u003c')}</script></body></html>`;
    await context.route(origin + '/__prism-test', route => route.fulfill({
        contentType: 'text/html',
        headers: { 'Content-Security-Policy': "default-src 'self'; script-src 'self'; style-src 'self'; object-src 'none'; base-uri 'none'" },
        body: codePage('sample.css', { new: css }),
    }));
    const cspErrors = [];
    page.on('console', message => {
        if (message.type() === 'error' && /Content Security Policy|Refused to/.test(message.text())) cspErrors.push(message.text());
    });
    await page.goto(origin + '/__prism-test');
    await page.waitForSelector('code .token');
    assert.equal(await page.locator('#highlight-status').textContent(), '');
    assert.equal(await page.evaluate(() => window.sourceExecuted), undefined);
    assert.equal(await page.locator('code').evaluateAll(rows => rows.map(row => row.textContent).join('\n') + '\n'), css);
    assert.equal(await page.locator('[data-copy]').isVisible(), enabled('copy-to-clipboard'));
    assert.equal(await page.locator('.source-language').count(), enabled('show-language') ? 1 : 0);
    assert.equal(await page.locator('.code-scroll').evaluate(region => region.classList.contains('prism-no-line-numbers')), !enabled('line-numbers'));
    if (enabled('show-invisibles')) {
        assert(await page.locator('.token.space').count() > 0);
        assert(await page.locator('.token.tab').count() > 0);
        assert.equal(await page.locator('.token.lf').count(), css.split('\n').length - 1);
    }
    if (enabled('inline-color')) {
        const swatch = await page.locator('.inline-color').first().evaluate(element => ({ html: element.outerHTML, color: getComputedStyle(element).backgroundColor }));
        assert.equal(swatch.color, 'rgb(255, 0, 0)', JSON.stringify({ swatch, cspErrors }));
    }
    if (enabled('match-braces')) {
        const brace = page.locator('.brace-open[id]').first();
        await brace.hover();
        assert.equal(await page.locator('.brace-hover').count(), 2);
        await brace.focus();
        assert.equal(await page.locator('.brace-hover').count(), 2);
    }
    if (enabled('previewers')) {
        await page.locator('.token.color').first().focus();
        await page.waitForSelector('.prism-previewer-color.active');
    }
    assert.deepEqual(cspErrors, []);
    const lightSurface = await page.locator('.prism-surface').evaluate(surface => getComputedStyle(surface).backgroundColor);
    await page.evaluate(() => { document.documentElement.dataset.appearance = 'dark'; });
    const darkSurface = await page.locator('.prism-surface').evaluate(surface => getComputedStyle(surface).backgroundColor);
    assert.notEqual(lightSurface, 'rgba(0, 0, 0, 0)');
    assert.notEqual(darkSurface, 'rgba(0, 0, 0, 0)');
    if (settings.theme === 'prism.min.css') assert.equal(lightSurface, 'rgb(245, 242, 240)');
    if (settings.darkTheme === 'prism-tomorrow.min.css') assert.equal(darkSurface, 'rgb(45, 45, 45)');
    await page.screenshot({ path: '/tmp/larasvn-prism-plugins.png', fullPage: true });
    const pascal = "program Demo;\nbegin\n  Writeln('café');\nend.\n";
    const languageCases = [
        ['Demo.PAS', pascal, 'Pascal'], ['Project.dpr', pascal, 'Pascal'],
        ['Example.cs', 'public class Demo { public int Value = 42; }\n', 'C#'],
        ['Example.tsx', 'const view = <Widget count={42} />;\n', 'React TSX'],
        ['settings.json', '{"ok": true, "value": 42}\n', 'JSON'],
        ['file.unknown', pascal, 'Plain text'],
    ];
    await context.route(origin + '/__prism-language-test*', route => {
        const url = new URL(route.request().url());
        const [filename, text] = languageCases[Number(url.searchParams.get('case'))];
        const mode = url.searchParams.get('mode');
        return route.fulfill({ contentType: 'text/html', body: codePage(filename, mode === 'diff' ? { old: text, new: text } : { new: text }, mode) });
    });
    for (const [index, [filename, text, title]] of languageCases.entries()) {
        for (const mode of filename.endsWith('.PAS') ? ['source', 'diff', 'blame'] : ['source']) {
            await page.goto(origin + '/__prism-language-test?case=' + index + '&mode=' + mode);
            assert.equal(await page.locator('#highlight-status').textContent(), '', filename);
            if (enabled('show-language')) assert.equal(await page.locator('.source-language').textContent(), title, filename);
            assert.equal(await page.locator('code[data-document="new"]').evaluateAll(rows => rows.map(row => row.textContent).join('\n') + '\n'), text, filename);
            const syntax = await page.locator('code .token.keyword, code .token.tag, code .token.property').count();
            assert.equal(syntax > 0, title !== 'Plain text', filename);
            if (mode === 'diff') assert(await page.locator('code[data-document="old"] .token.keyword').count() > 0);
        }
    }
    assert.deepEqual(errors, []);
    assert.deepEqual(cspErrors, []);
    console.log('Language checks passed: Pascal/Delphi source, diff and blame; C#, TSX, JSON, uppercase extensions, exact text, and plain-text fallback.');
    console.log('Prism configuration checks passed: exact source text, escaped markup, CSP, theme surfaces, and enabled plugin behavior. Plugins: ' + (settings.plugins.join(', ') || 'none'));
    console.log('Browser checks passed: source, multiline diff, blame, themes, persistence, system changes, cross-tab updates, mobile width, missing revision, no-JS reading, directory navigation, history pagination, filename search, deletion links, arbitrary comparison, reversal, aliases, downloads, RSS, skip-link focus, landmarks, and control labels.');
} finally {
    await browser?.close();
}
