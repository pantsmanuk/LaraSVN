# LaraSVN

A Laravel 13 and PrismJS Subversion browser. The local implementation supports
repository/directory browsing, properties, history, revision details, filename
search, source, previous-change and arbitrary comparisons, blame, RSS, downloads,
raw MIME responses, and legacy browse aliases. Production configuration
and full WebSVN compatibility are not ready.

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

On the current workspace, dependencies, `.env`, fixture, and built assets already
exist. Start with `php artisan dev` and open <http://127.0.0.1:8000>.
The dev command starts Laravel and watches CSS/JavaScript for rebuilds. Reload
the browser after changes; Ctrl+C stops both processes. `composer run dev` is an
alias. Use `--port=8001` if needed. For a server without watching, use
`php artisan serve` and build assets with `npm run build`. Development serving is
restricted to `APP_ENV=local`; deployment uses PHP-FPM.

The Phase 3 fixture has 27 revisions in `storage/app/fixture-v3`. Earlier
fixtures remain in `storage/app/fixture` and `storage/app/fixture-v2`.
`example.php` changes at r10 and r20, with unrelated commits between them. It contains a long multiline PHP comment and a heredoc.
Other sample files cover empty content, Unicode, an `@` in a filename, tabs, CRLF,
and no final newline. Revisions 21–26 add nested directories, properties, a copy,
a rename, a deletion, and a property-only change. The fixture command refuses to
overwrite an existing fixture. Revision 27 adds binary/active content, hooks,
whitespace samples, an external reference, and an isolated symlink. Root downloads
are rejected because they contain that symlink; download `/docs` or `/formats`.
To rebuild this disposable fixture, remove only `storage/app/fixture-v3` and run
the command again.

## Review URLs

- `/listing.php?repname=fixture&path=%2F`
- `/listing.php?repname=fixture&path=%2Ftrunk%2Fsrc&rev=22`
- `/log.php?repname=fixture&path=%2F&showchanges=1`
- `/log.php?repname=fixture&path=%2F&search=cafe`
- `/revision.php?repname=fixture&rev=25`
- `/search.php?repname=fixture&path=%2F&search=*.php`
- `/filedetails.php?repname=fixture&path=/example.php`
- `/diff.php?repname=fixture&path=/example.php&rev=20`
- `/diff.php?repname=fixture&path=/example.php&rev=20&all=1`
- `/blame.php?repname=fixture&path=/example.php&rev=20`
- `/comp.php?repname=fixture&compare[0]=/example.php@10&compare[1]=/example.php@20`
- `/comp.php?repname=fixture&compare[0]=/docs@25&compare[1]=/docs@26`
- `/rss.php?repname=fixture&path=/docs&max=5`
- `/dl.php?repname=fixture&path=/docs&dlmode=zip`
- `/dl.php?repname=fixture&path=/docs&dlmode=gzip`
- `/filedetails.php?repname=fixture&path=/formats/binary.bin&usemime=1`
- `/browse.php/fixture/docs`

Follow Browse → History → Revision → Source, then inspect Changes and Blame.
Deletion links open source before deletion; historical listings open the selected
snapshot. Check Light, Dark, and System appearance across these views. Source
and attribution render on the server and remain readable with JavaScript disabled.
Prism tokenizes complete documents before distributing tokens into visible lines.

## Verification

```bash
composer run verify
```

This builds assets, runs PHP and JS checks, checks formatting, and verifies the
local fixture prerequisites. Individual checks remain available through
`php artisan test`, `npm test`, and `npm run build`.

Browser checks require a Playwright Chromium installation:

```bash
npx playwright install chromium
npm run test:browser
```

For an existing local server or staged Apache mount, set
`LARASVN_BASE_URL=http://localhost/websvn`. The harness selects the known
`fixture`; do not point it at production.

Alternatively, set `CHROMIUM_EXECUTABLE` to a compatible local Chromium binary.
The browser harness transports actual Laravel HTTP-kernel responses through
Playwright routing rather than opening a TCP port. It checks DOM enhancement,
appearance behaviour, browsing, history pagination, search clearing, deletion
links, arbitrary comparisons, archive downloads, feeds, aliases, narrow-screen
overflow, error rendering, and no-JavaScript reading. It writes review screenshots to `/tmp/larasvn-*.png`.
Browser checks have not passed in the restricted development session: Chromium
cannot start because a required socket operation is prohibited.

## Scope and next work

The current local environment uses `fixture`. Staging/production requires an
explicit JSON repository file or `SVN_PARENT_PATH`, plus `SVN_PUBLIC_READ=true`; there is no fixture
fallback there. This release supports whole, anonymously public repository roots.
Do not publish roots that rely on per-user or per-path access filtering. See
[DEPLOYMENT.md](DEPLOYMENT.md) before configuring a deployment.

The local implementation supports UTF-8 source, numeric operative/peg revisions,
compact/full previous-change comparisons, whitespace toggling, XML properties,
repository groups, directory/file links, paginated history, and SVN filename glob
search. History supports `page`, `all`, `showchanges`, `search`, `fr`, `max`, and
numeric or `HEAD` values for `sr` and `er`. Every search word must match the message
or author, without case or accent differences. Empty filename queries do not scan
the repository. Directory/search pages contain up to 50 entries; history pages
contain up to 20 changes unless `all=1`.

Default SVN display/raw limit is 2 MB, enforced during reads. Source displays
also have a 20000-line cap, checked before splitting or rendering. Downloads
allow 20 MB of stored file data and 1000 entries; archives have a 22 MB total archive
limit. Downloads use isolated temporary directories and clean up before responding.
They exclude externals, reject symlinks, and preserve executable file permissions.
They preserve stored bytes without export keyword/EOL transformations. Raw
responses use a restrictive CSP sandbox. See [PHASE3.md](docs/PHASE3.md) for the
compatibility details.

History `max` defaults to 40 entries,
or 0 when searching. A finite max shows a limit notice when more entries exist.
`max=0` requests all entries within a 1000-change local cap; exceeding that cap
returns an explicit error. Environment-backed limits, a shared 30-second SVN request budget, and a
documented PHP-FPM concurrency/memory policy are available for deployment review.

Only repository-root URLs are supported. Configured exposed subpaths are rejected
until history filtering can preserve that boundary. Configuration entries now
have `url`, optional `group`, `downloads` (default false), and `rss` (default true)
keys. Use `SVN_PARENT_PATH` for per-request local directory discovery, or set
`SVN_REPOSITORIES_FILE` to a JSON mapping using these fields; string
entries from Phase 1 must be migrated. Configuration errors fail closed. Production
publication requires explicit acknowledgement that all configured history is public.
`php artisan svn:check --production --repository=svn-hooks` checks the deployment
settings and read-only root access when run on the target host.

Supplied production configuration has been audited in
[PRODUCTION-AUDIT.md](docs/PRODUCTION-AUDIT.md). Outstanding: inherited access/runtime
checks, deployed Apache checks, full
language/encoding support, remaining legacy parameter and fragment aliases,
word-level change marks, legacy export transformations, extension-triggered gzip,
and final deployment/browser/UAT checks.
See [PHASE4.md](docs/PHASE4.md) for current status, [UAT.md](docs/UAT.md) for acceptance,
[DEPLOYMENT.md](DEPLOYMENT.md) for staging/rollback, and [PHASE3.md](docs/PHASE3.md) for
endpoint compatibility details, [PHASE2.md](docs/PHASE2.md) for
browsing/history implementation, [PHASE1.md](docs/PHASE1.md) for the
initial proof, [ARCHITECTURE.md](docs/ARCHITECTURE.md) for the product plan, and
[DESIGN.md](docs/DESIGN.md) for the design contract.
