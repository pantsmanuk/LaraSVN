# UAT checklist

All seven installation acceptance gates passed in the user's staging/production
review on 23 October 2026. Browser and visual acceptance was explicitly granted.
The reviewed URL is `https://larasvn.ggpsystems.co.uk/websvn/`. It is not publicly
accessible or reachable from the development environment; the installation
results below are user-reported, not independently checked by the agent.

The user also confirmed that staging/production was installed from a `git clone`
of the GitHub repository and worked without unexpected errors or extra steps.

As a final integration check, the user changed one project's WebSVN URL in the
bug tracking software to LaraSVN and confirmed that the integration continued to
work as expected. Result: PASSED. This confirms the tested project's integration;
it does not imply a switch for other projects.

LaraSVN and the original WebSVN are running in parallel. LaraSVN is not yet
available to the public. Public cutover remains a separate deployment step.

## Installation acceptance results

| Gate | User-reported evidence | Result |
| --- | --- | --- |
| Actual repository transport and root URL | Staging/production root URL is reachable without issue | PASSED |
| Whole-root public access | All repositories can be browsed from the root without additional authentication within the accessible network | PASSED |
| MIME and download parity | Files browse as plain text where appropriate; downloads work when requested | PASSED |
| `/websvn` Apache routing and assets | Project works without apparent issue in staging/production | PASSED |
| Browser and visual acceptance | User explicitly passed and accepted the interface | PASSED and ACCEPTED |
| Resource policy | Deployment has not meaningfully affected server workload | PASSED |
| Rollback | LaraSVN and original WebSVN both work in parallel at the time of review | PASSED at time of review |

The workload result records observed deployment behaviour, not a load-test result.
The rollback result records availability of the original installation; a cutover
and restore drill was not reported. Individual fixture and accessibility cases
were not itemized in the acceptance report.

## Local fixture preparation

These instructions support repeatable development checks. The user's acceptance
review was performed against staging/production, not the local fixture.

Run `php artisan dev` and open <http://127.0.0.1:8000>. The command starts the server
and rebuilds CSS/JavaScript after edits. Reload pages to see changes; this is not
browser hot reload. Ctrl+C stops both processes. Use `--port=8001` if 8000 is busy.
`composer run dev` starts the same command. `php artisan serve` still works with
`npm run build` for a manual asset build.

The fixture is still revision 27 in `storage/app/fixture-v3`. No rebuild or new
repository is needed in the current workspace. Only local/testing environments without explicit JSON/parent settings
fall back to the fixture; a broken explicit repository configuration fails closed.
Run `php artisan svn:check --repository=fixture` before starting review.

Run automated browser checks against a running local server:

```bash
# Existing local server, or a locally staged Apache mount with the fixture.
LARASVN_BASE_URL=http://127.0.0.1:8000 npm run test:browser
LARASVN_BASE_URL=http://localhost/websvn npm run test:browser
```

Playwright and its Chromium browser are installed in the development workspace.
The browser script explicitly selects `fixture` and
requires its known data. Do not point it at the production WebSVN service.
Automated checks include syntax tokens, theme state, mobile overflow, aliases,
ZIP downloads, RSS, keyboard skip-link focus, landmarks, labelled controls, and
reading with JavaScript disabled. They do not replace visual/accessibility review.

## Automated browser verification

Passed on 23 October 2026 against application revision `b117eb0`, using Playwright
Chromium and a temporary server started with
`php artisan serve --host=127.0.0.1 --port=8000 --no-reload`:

```bash
LARASVN_BASE_URL=http://127.0.0.1:8000 node scripts/browser-check.mjs
```

The command exited 0. Coverage included source highlighting, multiline diff,
blame, appearance persistence, system changes, cross-tab updates, narrow-screen
overflow, missing revisions, no-JavaScript reading, directory navigation, history
pagination, filename search clearing, deletion links, comparison and reversal,
aliases, ZIP downloads, RSS, skip-link focus, landmarks, and control labels.
Review screenshots were saved as `/tmp/larasvn-*.png`. The server was stopped
after verification.

Chromium requires execution outside the agent sandbox. The default transport
without `LARASVN_BASE_URL` failed hostname resolution after an alias redirect;
the successful run used the local HTTP server. This result does not establish
Apache/PHP-FPM mount behaviour, full WCAG conformance, or manual visual acceptance.
The separate staging/production acceptance results are recorded above.

## Fixture checklist

This checklist retains fixture-specific cases for future regression reviews.
Results were not individually reported in the staging/production acceptance;
the installation acceptance results above are the user's sign-off.

| Check | Expected result | Result |
| --- | --- | --- |
| Browse `/docs` and `/trunk/src` | Directory-first lists; files open source; breadcrumbs retain snapshot | Not itemized |
| Source `/example.php` | PHP highlighting; multiline comment/heredoc stay correct; copy includes source only | Not itemized |
| Changes at r20 | Previous file change is r10; compact/full and whitespace controls work | Not itemized |
| Blame at r20 | Every source line has correct author/revision; links retain history context | Not itemized |
| Historical `/trunk/src` at r22 | Opens the earlier file names/content rather than HEAD | Not itemized |
| History and filename search | Pages, limits, author/message search, glob search, and clear/reset work | Not itemized |
| Revision r23/r24/r25 | Copy, rename, and deletion links reach the appropriate snapshots | Not itemized |
| Compare `/example.php@10` with `@20` | Readable patch, correct direction, reversal, and Prism diff tokens | Not itemized |
| Compare `/docs@25` with `@26` | Property-only change is visible | Not itemized |
| Compare `/formats/left.txt` and `/formats/right.txt` | Whitespace difference disappears when ignored | Not itemized |
| RSS `/docs`, max=2 | Valid XML/feed, correct revisions, working item links | Not itemized |
| RSS `quiet=0` | Changed-path list omitted because quiet is presence-based | Not itemized |
| ZIP and tar.gz of `/docs` | Valid archives with exact files; no external checkout | Not itemized |
| Plain and archived `/formats/hook.sh` | Correct bytes; executable permissions in archives on Unix | Not itemized |
| `/formats/pre-commit` | Extensionless UTF-8 hook displays as source | Not itemized |
| Raw `/formats/binary.bin` | Exact binary bytes; no source-decoding error | Not itemized |
| Raw HTML/SVG | Content remains sandboxed; repository scripts cannot run | Not itemized |
| Download `/special` or root | Explicit 415 because this fixture contains a symlink | Not itemized |
| `/browse.php/fixture/docs` and `?op=log` | Correct canonical workflow and query context | Not itemized |
| Invalid path/revision, missing resource | Honest 400/404; readable error and return link | Not itemized |
| Light/Dark/System | No theme flash; persistence, system changes, and cross-tab sync | Not itemized |
| 390 px width and 200% browser zoom | Controls remain usable; only reading/table regions scroll horizontally | Not itemized |
| Keyboard only | Skip link reaches main, labels focus inputs, links/buttons/select work, focus stays visible | Not itemized |
| JavaScript disabled | Source/diff/blame/patch and GET navigation remain readable and usable | Not itemized |
| Colour and reduced motion | Text and syntax tokens readable in both themes; no unnecessary motion | Not itemized |

See [DEPLOYMENT.md](../DEPLOYMENT.md) for configuration, capacity defaults, server
examples, staging, and rollback. Recording acceptance does not activate public
access or replace the original WebSVN installation.

Production audit findings and source references are recorded in
[PRODUCTION-AUDIT.md](PRODUCTION-AUDIT.md).
