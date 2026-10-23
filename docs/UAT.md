# UAT checklist

The code is a local UAT candidate. Production parity is conditional on the actual
installation settings, and browser checks have not passed in the agent sandbox.
Mark results here or return notes with the URL, selected revision, appearance,
expected result, and observed result. Cosmetic feedback is welcome at this stage.

## Prepare

Run `php artisan dev` and open <http://127.0.0.1:8000>. The command starts the server
and rebuilds CSS/JavaScript after edits. Reload pages to see changes; this is not
browser hot reload. Ctrl+C stops both processes. Use `--port=8001` if 8000 is busy.
`composer run dev` starts the same command. `php artisan serve` still works with
`npm run build` for a manual asset build.

The fixture is still revision 27 in `storage/app/fixture-v3`. No rebuild or new
repository is needed in the current workspace. Only local/testing environments without explicit JSON/parent settings
fall back to the fixture; a broken explicit repository configuration fails closed.
Run `php artisan svn:check --repository=fixture` before starting review.

Automated browser checks can use either transport:

```bash
# Actual Laravel HTTP kernel, transported without opening an HTTP server.
npm run test:browser

# Existing local server, or a locally staged Apache mount with the fixture.
LARASVN_BASE_URL=http://127.0.0.1:8000 npm run test:browser
LARASVN_BASE_URL=http://localhost/websvn npm run test:browser
```

Install Chromium through Playwright if needed, or set `CHROMIUM_EXECUTABLE` to a
compatible existing binary. The browser script explicitly selects `fixture` and
requires its known data. Do not point it at the production WebSVN service.
Automated checks include syntax tokens, theme state, mobile overflow, aliases,
ZIP downloads, RSS, keyboard skip-link focus, landmarks, labelled controls, and
reading with JavaScript disabled. They do not replace visual/accessibility review.

## Local acceptance

| Check | Expected result | Result |
| --- | --- | --- |
| Browse `/docs` and `/trunk/src` | Directory-first lists; files open source; breadcrumbs retain snapshot | Pending |
| Source `/example.php` | PHP highlighting; multiline comment/heredoc stay correct; copy includes source only | Pending |
| Changes at r20 | Previous file change is r10; compact/full and whitespace controls work | Pending |
| Blame at r20 | Every source line has correct author/revision; links retain history context | Pending |
| Historical `/trunk/src` at r22 | Opens the earlier file names/content rather than HEAD | Pending |
| History and filename search | Pages, limits, author/message search, glob search, and clear/reset work | Pending |
| Revision r23/r24/r25 | Copy, rename, and deletion links reach the appropriate snapshots | Pending |
| Compare `/example.php@10` with `@20` | Readable patch, correct direction, reversal, and Prism diff tokens | Pending |
| Compare `/docs@25` with `@26` | Property-only change is visible | Pending |
| Compare `/formats/left.txt` and `/formats/right.txt` | Whitespace difference disappears when ignored | Pending |
| RSS `/docs`, max=2 | Valid XML/feed, correct revisions, working item links | Pending |
| RSS `quiet=0` | Changed-path list omitted because quiet is presence-based | Pending |
| ZIP and tar.gz of `/docs` | Valid archives with exact files; no external checkout | Pending |
| Plain and archived `/formats/hook.sh` | Correct bytes; executable permissions in archives on Unix | Pending |
| `/formats/pre-commit` | Extensionless UTF-8 hook displays as source | Pending |
| Raw `/formats/binary.bin` | Exact binary bytes; no source-decoding error | Pending |
| Raw HTML/SVG | Content remains sandboxed; repository scripts cannot run | Pending |
| Download `/special` or root | Explicit 415 because this fixture contains a symlink | Pending |
| `/browse.php/fixture/docs` and `?op=log` | Correct canonical workflow and query context | Pending |
| Invalid path/revision, missing resource | Honest 400/404; readable error and return link | Pending |
| Light/Dark/System | No theme flash; persistence, system changes, and cross-tab sync | Pending |
| 390 px width and 200% browser zoom | Controls remain usable; only reading/table regions scroll horizontally | Pending |
| Keyboard only | Skip link reaches main, labels focus inputs, links/buttons/select work, focus stays visible | Pending |
| JavaScript disabled | Source/diff/blame/patch and GET navigation remain readable and usable | Pending |
| Colour and reduced motion | Text and syntax tokens readable in both themes; no unnecessary motion | Pending |

## Installation acceptance gates

These gates remain open until evidence is available. They must not be marked
passed merely because fixture tests pass.

| Gate | Evidence needed | Status |
| --- | --- | --- |
| Actual repository transport and root URL | Local parentPath /home/svn/repositories in supplied config | Static audit complete; filesystem permissions need host check |
| Whole-root public access | No WebSVN access file or authentication in supplied snippet | Check inherited Apache/runtime overlays on host |
| MIME and download parity | text/plain override; downloads disabled, depth 2 inactive | Profile prepared; actual file types/encoding need UAT |
| `/websvn` Apache routing and assets | Adapted configuration, syntax check, live staging URL tests | Awaiting host check |
| Browser and visual acceptance | Successful harness output and local checklist | Awaiting browser check |
| Resource policy | Approved limits, FPM worker/memory budget, temporary cleanup | Candidate defaults documented |
| Rollback | Previous WebSVN release/mount preserved and restore path checked | Awaiting deployment review |

See [DEPLOYMENT.md](../DEPLOYMENT.md) for configuration, capacity defaults, server
examples, staging, and rollback. This phase does not activate a production mount.

Production audit findings and source references are recorded in
[PRODUCTION-AUDIT.md](PRODUCTION-AUDIT.md).
