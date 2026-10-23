# Phase 4 handoff

Updated 19 October 2026; browser results supplemented 23 October 2026. Local
deployment/UAT preparation and automated browser checks are complete. The supplied
production configuration audit is complete; host checks and manual acceptance
remain open, so this
is a review candidate rather than a completed production sign-off. See
[UAT.md](UAT.md) for the checklist and [DEPLOYMENT.md](../DEPLOYMENT.md) for staging
and rollback.

## Delivered

- `php artisan dev` builds assets, starts Laravel, and watches CSS/JavaScript.
  `composer run dev` invokes the same command without Composer's process timeout.
  Both child processes stop on Ctrl+C or when either process fails. No new npm
  package was added. A manual browser reload shows asset changes.
- `SVN_REPOSITORIES_FILE` loads an external JSON mapping. The loader validates
  names, URL schemes, flags, and supported settings, rejects embedded credentials,
  and returns no repositories on invalid input. It never includes supplied PHP.
  SVN_PARENT_PATH supports live local discovery, with explicit JSON entries
  overriding discovered defaults. Fixtures are automatic only in local/testing
  environments without deployment settings. Fixture creation is
  also restricted to those environments.
- Staging/production requires `SVN_PUBLIC_READ=true`. Every exposed root and its
  history must be public. No login/authz support is implied. Denied configuration
  also hides repository names from navigation and returns an honest 503.
- `php artisan svn:check` verifies configuration, extensions, built assets,
  writable runtime locations, GNU diff, and read-only root metadata. Select
  repositories with `--repository`; `--production` also checks environment, debug,
  application key, HTTPS URL, and public-access acknowledgement. It does not print
  backend URLs or claim to validate per-path access rules.
- Byte, entry, archive, line, process-time, and shared request-time limits come
  from configuration. A scoped SVN client shares HEAD metadata and timing across
  controllers/downloads. The HTTP boundary resets it for each request, including
  long-lived/test application processes. Source-line limits run before splitting
  and building HTML; raw responses bypass that line limit.
- All public application endpoints remain GET/HEAD. Unused session/cookie and
  request-forgery middleware is removed for these read-only workflows. Responses
  issue no session cookies and use `private, no-store` alongside existing security
  headers. Adding authentication or mutations will require restoring the relevant
  session/forgery controls.
- Apache `/websvn`, dedicated PHP-FPM, repository JSON, and temporary-cleanup
  examples live in `deploy/`. PHP-FPM's worker count, memory limit, and hard timeout
  provide native concurrency/termination boundaries. Examples require host review.
- The browser harness supports actual local HTTP/Apache mounts through
  `LARASVN_BASE_URL`, explicitly selects `fixture`, and adds skip-link, labelled
  input, and landmark checks. No production URL is used by this harness.
- `composer run verify` builds assets, runs PHP/JS checks and formatting, then
  checks local fixture prerequisites. It requires the local fixture and dev tools.

## Proof and limits

The suite currently has 58 passing PHP tests and 560 assertions, including all
previous endpoint tests. New checks cover malformed configuration, credential and
unsupported access-rule rejection, explicit production publication, hidden
navigation on denial, stateless/security headers, source/byte/archive/entry limits,
shared timing, CLI prerequisites, development-command guards, and HTTP method
restrictions.

Existing JS checks, build, formatting, Composer validation, and frontend static
audit pass. Composer retains its existing missing-license warning. No dependency
versions changed.

A live watcher proof modified and restored CSS, verified both rebuilds, and stopped
the watcher cleanly with SIGTERM. The dev command's initial build and coordinated
failure path ran; its server could not bind a local socket in this sandbox, so a
successful interactive server run must be confirmed on the user's host.

Isolated production-mode `config:cache`, `route:cache`, and `view:cache` succeeded.
The resulting cached application passed `svn:check --production` and rendered the
real fixture listing. A second cached production-profile proof added and removed
local fixture links under the discovered parent and verified 200/404 changes
without rebuilding config cache. These checks used private temporary caches and synthetic
configuration; the workspace's live environment/cache and production were not
changed.

On 23 October 2026, the full Playwright Chromium harness passed against a temporary
local PHP server outside the agent sandbox. The server was stopped after the run.
The default intercepted transport hit a hostname-resolution error after an alias
redirect, so the verified path sets `LARASVN_BASE_URL=http://127.0.0.1:8000`.
See [UAT.md](UAT.md) for the exact command and coverage. Manual visual/accessibility
acceptance and actual Apache/PHP-FPM verification remain open.

## Production evidence

Supplied production configuration files were inspected as text and later removed
from the project. [PRODUCTION-AUDIT.md](PRODUCTION-AUDIT.md) records source
locations, effective settings, resulting changes, and remaining host checks.
No production repository was opened.

The active setup uses parentPath /home/svn/repositories, text/plain source override,
disabled downloads with an inactive minimum depth of 2, RSS enabled, MultiViews off,
and four-column tabs. No application authz or Apache authentication appears in the
supplied settings. Inherited Apache settings, runtime overlays, filesystem
permissions, and actual file encodings still require host/UAT verification.

Live parent discovery now covers routes, aliases, feeds, download policy, and
navigation; inventory refreshes between requests rather than being captured by
config:cache. Production MIME override is environment-configurable. Local fixture
settings remain unchanged. The matching template is deploy/production.env.example.
Production has not been activated or configured in the local .env.

## Review now

1. Run `php artisan dev`. Confirm assets rebuild after a frontend edit and Ctrl+C
   stops both server and watcher. The existing Phase 3 fixture remains ready.
2. Follow [UAT.md](UAT.md), including detailed cosmetic feedback at this stage.
   The local automated browser run has passed; record manual acceptance separately.
3. Review [DEPLOYMENT.md](../DEPLOYMENT.md) and the example server configuration.
   Do not activate a production replacement yet.
4. Confirm inherited access/runtime rules and real filesystem permissions on the
   host. Record browser and host checks in the acceptance table.

All work remains uncommitted. Reference copies, deployment-specific repository
JSON, dependencies, built assets, and generated fixture repositories remain ignored.
