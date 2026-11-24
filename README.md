# LaraSVN

A read-only Subversion browser built with Laravel 13 and PrismJS, inspired by WebSVN.

Browse repositories, directories, source, properties, and history. Inspect revisions,
compare files and directories, view line attribution, and search filenames or commit
history. RSS feeds, downloads, raw file responses, and legacy WebSVN URLs are also
supported. Source and line attribution remain readable with JavaScript disabled.

LaraSVN supports a [documented subset of WebSVN behaviour](docs/COMPATIBILITY.md).

## Local startup

Requirements: PHP 8.4.1 or later with Composer, DOM, SimpleXML, mbstring,
XMLWriter, ZipArchive, Phar, zlib, and normal Laravel extensions; Node.js 22 or later; `svn`, `svnadmin`, and GNU `diff` on PATH.
No database or queue worker is required.

For a fresh checkout:

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan svn:fixture
npm run build
php artisan dev
```

Open <http://127.0.0.1:8000> to browse the sample repository.
The dev command starts Laravel and watches CSS/JavaScript for rebuilds. Reload
the browser after changes; Ctrl+C stops both processes. `composer run dev` is an
alias. Use `--port=8001` if needed. For a server without watching, use
`php artisan serve` and build assets with `npm run build`. Development serving is
restricted to `APP_ENV=local`; deployment uses PHP-FPM.

See [testing and fixtures](docs/TESTING.md) for sample content and review URLs.

## Deployment

This release supports whole, anonymously public repository roots. **Do not publish
repositories that require per-user or per-path access filtering.**

Deployment requires an explicit JSON repository file or `SVN_PARENT_PATH`, plus
`SVN_PUBLIC_READ=true`. There is no production fixture fallback. Read the
[deployment guide](DEPLOYMENT.md) before configuring PHP-FPM, repository access,
or a subdirectory mount.

## Prism configuration

Edit [prism.config.json](prism.config.json) to select the light/dark code themes,
file-extension mappings, and enabled plugins, then run `npm run build`. Defaults
use Prism's standard
theme in light mode, Tomorrow in dark mode, and all nine MantisMarkdown options.
Use the same theme for both settings if you prefer a fixed code palette.
Pascal/Delphi and common source formats are mapped to their Prism grammars, which
are bundled automatically with their dependencies. Unknown extensions use plain
text. See [Prism configuration](docs/PRISM.md) for supported choices and source-preservation rules.

## Verification

```bash
composer run verify
```

This builds assets, runs PHP and JavaScript checks, checks formatting, and verifies
local fixture prerequisites. See [testing and fixtures](docs/TESTING.md) for
individual checks and browser testing.

## Documentation

- [Supported behaviour and compatibility](docs/COMPATIBILITY.md)
- [Testing and fixtures](docs/TESTING.md)
- [Deployment and rollback](DEPLOYMENT.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Design guidance](docs/DESIGN.md) and [UX contracts](docs/UX-CONTRACT.md)
- [Acceptance and verification records](docs/UAT.md)
- [Production configuration audit](docs/PRODUCTION-AUDIT.md)
- [All supporting documentation and implementation history](docs/README.md)
