# Phase 1 handoff

Historical snapshot dated 19 October 2026. The browser blockers below were
superseded by the successful local Playwright run on 23 October 2026. See
[UAT.md](UAT.md) for current evidence and remaining manual and staging checks.

Historical Phase 1 handoff. Current state is in [PHASE2.md](PHASE2.md).

Updated 19 October 2026. The user reviewed the initial implementation positively
and is holding detailed notes until the product is UAT-ready. Automated browser
verification and formal visual acceptance remain outstanding.

## Delivered

- Laravel 13.34.0 on PHP 8.4.1, installed from local Composer cache.
- Local Git repository initialized by the user. Both reference folders, `.env`,
  dependencies, generated assets, framework state, and SVN fixture are ignored.
  No commit has been made.
- `php artisan svn:fixture` creates reproducible r1–r20 history without touching
  production. It refuses an existing destination.
- `filedetails.php`, `diff.php`, and `blame.php` share validated request parsing,
  resolved revisions, source documents, and escaped Blade rendering.
- SVN uses argument-based processes, noninteractive commands, timeouts, XML
  metadata/blame, and an explicit configured local repository.
- GNU diff produces line changes independently of syntax highlighting. The file's
  previous change is selected through SVN history, so r20 compares against r10.
- PrismJS 1.30.0 tokenizes complete documents. Text and grammar classes are rendered
  using DOM text nodes, with multiline context preserved inside compact hunks.
- Appearance has one controller for Light/Dark/System, namespaced browser storage,
  system-change tracking, cross-tab updates, and page-local fallback when storage
  fails. Initial resolution runs before the stylesheet loads.
- A restrained staff-inspired navigation rail and shared context/header provide
  the initial review surface. This is not final shell approval.

## Ownership map

| Path | Responsibility |
| --- | --- |
| `app/Svn/SvnClient.php` | SVN transport, metadata, revision resolution, source, and blame |
| `app/Svn/SourceDocument.php` | UTF-8 display lines, newline metadata, and original bytes |
| `app/Svn/Comparison.php` | Isolated temporary comparison inputs and aligned line rows |
| `app/Svn/Fixture.php` | Disposable local history generator |
| `app/Http/Controllers/CodeController.php` | Legacy inputs and view orchestration |
| `resources/views/` | Shared shell, plain readable code, comparison, blame, and errors |
| `resources/js/token-lines.js` | Full-document Prism tokens split into source lines |
| `resources/js/theme.js` | Appearance ownership |
| `resources/css/app.css` | Canonical semantic tokens, responsive layout, and syntax colours |
| `tests/` | Real-SVN feature checks, source/comparison cases, token and appearance checks |
| `scripts/browser-check.mjs` | Repeatable browser review against actual kernel responses |

## Verification evidence

- PHP tests: 12 passing tests, 52 assertions, using a separate temporary SVN fixture.
- Six JavaScript tests pass, covering multiline comment/heredoc context, Unicode text round
  trips, unknown grammars, empty lines, and appearance preference/state transitions.
- Frontend asset build passes. Dependencies are pinned by lockfiles.
- Actual Laravel HTTP-kernel rendering returns HTTP 200 for the comparison view
  with the application's Content Security Policy.
- Reference folders and generated fixture/dependencies were checked with
  `git check-ignore`.
- The frontend skill's static audit reported no findings. That does not establish
  browser or accessibility correctness.
- Browser attempts were blocked: Firefox crashed headless; cached Chromium exited
  because `setsockopt` is not permitted. No screenshot or visual-pass claim exists.

## Review and remaining work

Run `php artisan serve` from the current workspace. Open the review URLs in
[README.md](../README.md), especially Changes at r20 and Blame. Review both appearances
and System tracking, narrow layouts, keyboard access, and no-JavaScript reading.
Run `npm run test:browser` on a host that permits Chromium.

The next planned phase expands repository browsing and history. Per-phase shell
approval is not a prerequisite for functional development. Preserve the design
contract while deferring detailed cosmetic feedback to UAT. Correctness,
accessibility, and security defects remain actionable throughout development.
Resolve outstanding browser verification before declaring the product UAT-ready.
Production access remains unneeded, and any future production repository
inspection remains restricted to `svn-hooks`.
