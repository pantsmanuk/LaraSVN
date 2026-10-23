import { context } from 'esbuild';
import { mkdir } from 'node:fs/promises';
import { prismBuildPlugin } from './prism-config.mjs';

await mkdir('public/build', { recursive: true });
const watch = process.argv.includes('--watch');
const contexts = await Promise.all([
    context({ entryPoints: ['resources/js/theme.js'], outfile: 'public/build/theme.js', bundle: true, minify: true, format: 'iife' }),
    context({ entryPoints: ['resources/js/code.js'], outfile: 'public/build/code.js', bundle: true, minify: true, format: 'esm', plugins: [prismBuildPlugin] }),
    context({ entryPoints: ['resources/css/app.css'], outfile: 'public/build/app.css', bundle: true, minify: true, plugins: [prismBuildPlugin] }),
]);
const dispose = async () => { await Promise.all(contexts.map(build => build.dispose())); };
try {
    await Promise.all(contexts.map(build => build.rebuild()));
    if (watch) {
        await Promise.all(contexts.map(build => build.watch()));
        console.log('Watching LaraSVN CSS and JavaScript. Reload the browser after changes.');
        for (const signal of ['SIGINT', 'SIGTERM']) {
            process.once(signal, async () => { await dispose(); process.exit(0); });
        }
    } else {
        await dispose();
    }
} catch (error) {
    await dispose();
    console.error(error.message);
    process.exitCode = 1;
}
