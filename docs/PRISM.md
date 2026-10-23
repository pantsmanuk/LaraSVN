# Configure Prism

The project-root [prism.config.json](../prism.config.json) selects code themes,
language mappings, and plugins at build time. Changes require `npm run build`; `php artisan dev`
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

## File types and grammars

`languages` is the shared registry for file-extension detection and grammar
bundling. Each key is a canonical Prism language ID; its value is an array of
lowercase extensions without leading dots. For example:

```json
"pascal": ["pas", "pp", "dpr", "dpk", "lpr", "dfm", "lfm"]
```

The default registry includes Pascal/Delphi, PHP, JavaScript, TypeScript, JSX/TSX,
JSON, markup/XML, CSS/SCSS/Less, C/C++, C#, Visual Basic .NET, Python, Ruby, Java,
Go, Rust, SQL, shell/PowerShell, YAML, TOML, INI, Markdown, and diff files.
JSON uses its own grammar. Text Delphi forms are supported; binary forms remain
subject to the existing binary-file handling.

Matching uses the last extension and ignores filename case, so `.PAS` and `.pas`
both select Pascal. Unknown extensions, extensionless files, and bare dotfiles
remain plain text. Source, side-by-side diff, and blame share the same registry.
Unified comparisons use the diff grammar because they may contain multiple file
types and property changes. MIME policy still decides whether a file is displayed
as source or returned raw.

To add or change a mapping, edit this registry and rebuild. The build validates
language IDs against the installed Prism package, resolves grammar dependencies
using Prism's own loader, and derives display labels from its metadata. Duplicate
extensions and unknown grammar IDs fail the build. Move an extension between
entries to resolve an ambiguous file type; for example, `.h` defaults to C and
`.inc` is deliberately unmapped. No server-side extension list needs updating.

## Plugins

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
| `show-invisibles` | Marks rendered spaces, tabs, and line endings without changing text or modifying language grammars |
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

Whitespace markers use Prism's styling with LaraSVN's renderer. This avoids the
upstream show-invisibles hook's failure on optional grammar entries, including
Pascal's, and keeps the same behaviour across source, blame, and patches.

Run `npm test` and the [browser checks](UAT.md#automated-browser-verification)
after configuration changes. Browser checks cover enabled controls, exact source
text, whitespace markers, brace matching, colour swatches, keyboard previews,
the existing CSP, and theme backgrounds alongside the existing workflow checks.
Language checks verify the generated bundle, dependencies, Pascal source/diff/blame,
case-insensitive extensions, JSON, C#, TSX, and the plain-text fallback.
