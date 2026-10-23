# Configure Prism

The project-root [prism.config.json](../prism.config.json) selects the code themes
and plugins at build time. Changes require `npm run build`; `php artisan dev`
also watches this file. Deploy the rebuilt `public/build` assets. No Laravel
configuration-cache rebuild or external CDN access is needed.

`theme` is the light-mode palette and defaults to `prism.min.css`. `darkTheme`
defaults to `prism-tomorrow.min.css`. The Appearance selector controls which
palette applies, including System mode and reading without JavaScript. Set both
to the same filename for a fixed palette.

Supported theme files are `prism.min.css`, `prism-coy.min.css`,
`prism-dark.min.css`, `prism-funky.min.css`, `prism-okaidia.min.css`,
`prism-solarizedlight.min.css`, `prism-tomorrow.min.css`, and
`prism-twilight.min.css`. Their unminified `.css` names are also accepted.
LaraSVN uses their palettes while retaining its reading layout. The standard
theme's lighter token colours are darkened for contrast; custom palette choices
need visual review against source and changed-line backgrounds.

`plugins` is an array. All options below are enabled by default. Remove an entry
to disable it; use `[]` to disable all optional enhancements. Unknown settings,
theme paths, and plugin names cause the build to fail with an error.

| Option | Behaviour |
| --- | --- |
| `line-numbers` | Uses LaraSVN's revision-aware gutters, preserving line fragments without duplicate numbering |
| `match-braces` | Prism matches braces in source and blame, within contiguous visible diff lines, and in unified patches; omitted hunks are boundaries |
| `show-language` | Adds the language to the source/diff/blame toolbar or labels a unified patch as Diff |
| `copy-to-clipboard` | Enables Copy source on source/blame and Copy patch on unified comparisons; copies text without gutters or visual markers |
| `inline-color` | Shows Prism colour swatches using validated colours and DOM styles compatible with the existing CSP |
| `diff-highlight` | Adds Prism prefix highlighting to unified patches; side-by-side comparisons retain SVN's existing change markers |
| `normalize-whitespace` | Loads Prism's normalizer with trimming and indentation removal disabled to preserve source text, line numbers, and blame alignment |
| `show-invisibles` | Marks spaces, tabs, and line endings without changing source text or clipboard content |
| `previewers` | Enables Prism's supported CSS value previews, also reachable by keyboard focus |

Source and blame already normalize displayed CRLF/CR endings to LF on the server;
raw files and downloads preserve their stored bytes. Normalization does not trim
blank lines, remove indentation, or rewrite stored source. The plugin's usual
Markdown-code-block trimming would be incorrect for repository browsing.

Plugin dependencies are bundled automatically. These are Prism 1.30 assets from
the project's installed package, independent of the MantisMarkdown checkout.
LaraSVN adapts plugins to its server-rendered rows instead of replacing them with
Prism's standalone code-block layout. With JavaScript disabled, the server's
source, line links, attribution, and comparison content remain available.

Run `npm test` and the [browser checks](UAT.md#automated-browser-verification)
after configuration changes. Browser checks cover enabled controls, exact source
text, whitespace markers, brace matching, colour swatches, keyboard previews,
the existing CSP, and theme backgrounds alongside the existing workflow checks.
