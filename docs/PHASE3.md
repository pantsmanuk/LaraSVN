# Phase 3 handoff

Historical snapshot dated 19 October 2026. The browser blockers below were
superseded by the successful local Playwright run on 23 October 2026. See
[UAT.md](UAT.md) for current evidence and remaining manual and staging checks.

Updated 19 October 2026. The remaining endpoint families are implemented for the
local compatibility subset. Ready for functional review. Browser verification,
production configuration, and final UAT remain outstanding. This handoff supersedes
the current-state notes in [PHASE2.md](PHASE2.md).

## Delivered

- `comp.php` compares arbitrary file or directory snapshots, including additions,
  removals, property changes, and binary notices. It accepts `compare[0/1]`,
  `compare_rev[0/1]`, embedded `path@revision`, `manualorder`, and `ignorews`.
  Embedded revisions win. Nonpositive revisions select HEAD. Legacy requests sort
  revisions ascending unless manual ordering is requested. The form preserves
  explicit order and offers reversal. Native SVN unified patches render without
  JavaScript; Prism adds diff highlighting.
- `rss.php` returns RSS 2.0 XML with author, date, escaped descriptions, changed
  paths, copy origins, property notices, and working revision links. `max` defaults
  to 40, including `max=0`; the local maximum is 1000. The presence of `quiet`,
  including `quiet=0`, omits changed paths. A live feed's self link remains live.
- `dl.php` accepts the legacy `dlmode=plain|zip|gzip` parameter. Files default to
  plain; directories default to gzip. Invalid or incompatible modes fall back to
  that default. Gzip means tar.gz for files and directories. Names follow the
  repository/path/revision convention. With no operative revision, downloads use
  the latest change affecting the path. Archives preserve empty directories,
  binary bytes, and executable file permissions.
- `filedetails.php` checks MIME properties before source decoding. Supported source
  MIME types retain the code view; `usemime=1` requests raw bytes. MIME properties
  take precedence over the extension map, except octet-stream falls through to a
  known extension. Untyped UTF-8 files, including extensionless hooks, remain
  readable as source. Unknown binary data uses octet-stream. Raw responses preserve
  CRLF, invalid UTF-8, NUL bytes, and missing final newlines.
- `/browse`, `/browse.php`, and `/multiviews.php`, each with an optional repository
  and path suffix, redirect to the existing named workflows. Supported `op` values
  are `dir`, `file`, `revision`, `log`, `diff`, `blame`, `rss`, `dl`, `comp`, `search`,
  and repository switching through `rep`. Missing or unrecognized operations use
  file/directory detection. URL paths override conflicting query selectors.
  Repository names use the longest configured match, including slash-containing
  names. Encoded paths are decoded once.
- Existing navigation now exposes comparison, feeds, raw files, and enabled
  downloads. New controls reuse the existing shell, appearance modes, semantic
  colours, GET forms, and reading surfaces. No new package dependency was added.

## Safety and compatibility subset

Downloads are disabled unless a repository entry sets `downloads => true`.
RSS is enabled unless `rss => false`. The fixture explicitly enables both.
`source_mime_types` in `config/svn.php` owns source display overrides.

The SVN transport bounds stdout and stderr while reading and stops the process
on overflow or failure. Display/raw results retain the 2 MB cap and each SVN
command has a 15-second timeout. Downloads allow 20 MB of file data and 1000
entries, with a separate 22 MB total archive cap. These are local limits;
production request deadlines, concurrency, and capacity policy remain phase 4 work.

Downloads inventory repository nodes and read file bytes directly. They never
follow `svn:externals` and reject `svn:special`, including any archive containing a
symlink, with HTTP 415. Select a narrower directory to download other files.
These archives preserve stored content; they do not apply `svn export` keyword or
EOL transformations, nor promise WebSVN's timestamps or identical archive bytes.
This difference must be accepted or revised when production settings are audited.

Each archive uses a private, randomly named temporary directory. Normal completion
and exceptions remove it before a response is returned. Client disconnects during
response delivery therefore leave no archive behind. Cleanup does not depend on
stream completion or the working directory. Abrupt process/host termination can
still leave temporary data; operational cleanup belongs to deployment hardening.

Raw responses retain the requested MIME type and filename but carry a restrictive
CSP sandbox. Repository HTML/SVG is not allowed to execute scripts or acquire the
application's origin privileges. MIME properties cannot inject response headers.
This intentionally limits active raw documents compared with legacy WebSVN.

Aliases redirect to canonical endpoints instead of retaining the alias URL while
rendering. A `/websvn` deployment must mount Laravel's `public` directory and send
missing paths, including `.php` paths, through `public/index.php`. Disable Apache's
native MultiViews negotiation for that mount. Do not expose the project root.
HTTP-kernel tests verify base-path links and redirects; the actual Apache/proxy
configuration still requires deployment testing.

Repository-root configuration remains required. Authentication, authz filtering,
production credentials, path-specific download policies, configurable legacy MIME
regexes/maps, extension-triggered gzip responses, and full WebSVN configuration
parity are outside this phase. Production remains unconfigured and uninspected.

## Fixture

`php artisan svn:fixture` now creates `storage/app/fixture-v3` with 27 revisions.
The existing Phase 1 and Phase 2 fixtures remain untouched. The command refuses
an existing destination. The current workspace has the new fixture ready.

Revision 27 adds binary and active-content samples, executable and extensionless
hooks, whitespace comparison samples, a trailing-space filename, an external
reference, and an isolated symlink. Root downloads are deliberately rejected due
to that symlink. Use `/docs` or `/formats` when reviewing successful archives.

## Verification

- 44 PHP tests pass with 418 assertions. Real-SVN feature tests cover
  comparisons and ordering, properties, binary notices,
  whitespace, odd filenames, XML escaping, feed limits and links, raw bytes,
  archive contents and permissions, external exclusion, disabled features,
  symlink rejection, incremental size limits, cleanup after partial archive
  creation, simultaneous downloads, alias dispatch, decoding, MIME-header
  validation, and `/websvn` base-path handling.
- Existing source, diff, blame, browsing, history, and search checks remain in the
  full suite. Run `php artisan test` for the current counts.
- All six existing JavaScript checks, asset build, formatting, and the frontend
  static audit pass. Composer validation and native extension requirements pass.
  Composer retains its existing missing-license warning.
- The browser harness now transports binary responses with base64 and includes
  comparison/reversal, alias navigation, downloads, RSS, narrow-screen comparison,
  and comparison without JavaScript. Chromium still fails to launch because the
  sandbox denies `setsockopt`; no browser or visual pass is claimed.
- Work remains uncommitted. Reference folders and generated repositories remain
  ignored.

## Local review

Run `php artisan serve` if your server is not already running. The assets and
fixture are prepared. Begin at `/listing.php?repname=fixture&path=/docs`.

1. Open **Compare snapshots**. Compare `/example.php` at r10 and r20, then reverse.
   Compare `/docs` at r25 and r26 for the property-only patch. Compare
   `/formats/left.txt` and `/formats/right.txt`, then select **Ignore whitespace**.
2. Open the **RSS feed** from directory or file navigation. A feed reader may be
   more convenient than the browser's XML view. Follow an item to its revision.
3. Download `/docs` as ZIP and tar.gz. Inspect extracted files. Download an
   individual `/formats` file; its plain bytes should match the raw response.
   An archive of `hook.sh` should preserve its executable bit on Unix.
4. Visit `/browse.php/fixture/docs` and follow links into source/history. It should
   reach the same workflows as the normal endpoints.
5. Check Light, Dark, and System across the comparison and existing views.

Report broken workflows or incorrect content. Detailed cosmetic notes can wait
until UAT. On a host that permits Chromium, run `npm run test:browser` to complete
the outstanding automated browser checks.

The next phase should audit the production installation's actual settings and
URL usage, settle the documented differences, verify deployment and access
boundaries, set production limits, and complete browser/accessibility/UAT checks.
