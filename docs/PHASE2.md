# Phase 2 handoff

Historical snapshot dated 19 October 2026. The browser blockers below were
superseded by the successful local Playwright run on 23 October 2026. See
[UAT.md](UAT.md) for current evidence and remaining manual and staging checks.

Updated 19 October 2026. Functional implementation is ready for local workflow
review. Browser verification and UAT acceptance remain outstanding. This handoff
supersedes the current-state notes in [PHASE1.md](PHASE1.md).

## Delivered

- `/` and `index.php` show grouped configured repositories, or redirect to the
  root when one repository is configured. Empty and unavailable states are explicit.
- `listing.php` browses directories, links files into Source, and shows properties.
  Shared breadcrumbs and parent links preserve the selected snapshot.
- `log.php` supports pagination, revision ranges, message/author search, result
  limits, changed paths, and all-results display within the local cap.
- `revision.php` shows commit metadata, changed paths, copy origins, property
  changes, previous/next repository revisions, and snapshot browsing.
- `search.php` delegates recursive filename glob matching to SVN and paginates
  results. File contents are not searched.
- Source, diff, and blame link to History and Revision. File properties render as
  escaped text. The sidebar reads configuration instead of hard-coded files.
- HEAD is pinned independently from operative revisions. Renamed-file history
  remains accessible through its peg. Deleted-file links use the preceding snapshot.
- Common request validation and path handling have one owner. Invalid parameters
  produce HTTP 400; missing paths/revisions produce 404.
- Existing source/Prism/appearance behaviour is retained. New screens use the same
  semantic tokens and shared controls. No new application dependency was added.

## Fixture and configuration

`php artisan svn:fixture` now creates `storage/app/fixture-v2`, with 26 revisions.
The original `storage/app/fixture` directory is preserved. The command still
refuses an existing destination. The current workspace's Phase 2 fixture exists.

Revisions 21–26 add nested directories, properties, an HTML-like filename, a copy,
a rename, a deletion, and a property-only change. The r10/r20 source proof remains.
Tests create and remove independent temporary repositories.

Repository configuration entries contain `url` and optional `group` values.
Only repository-root URLs are supported; subpath configurations return an explicit
503 until history filtering can enforce that boundary. Production remains
unconfigured. Any production repository inspection is restricted to `svn-hooks`.

## Ownership

| Path | Responsibility |
| --- | --- |
| `app/Http/RepositoryRequest.php` | Common legacy inputs and path validation |
| `app/Http/Controllers/BrowseController.php` | Repository, directory, search, log, and revision workflows |
| `app/Svn/SvnClient.php` | Pinned metadata, listings, properties, verbose history, and existing source/blame transport |
| `resources/views/partials/` | Context, view navigation, properties, tables, changed paths, and pagination |
| `tests/Feature/BrowseViewsTest.php` | Real-SVN browsing and historical-link acceptance cases |
| `scripts/browser-check.mjs` | Browser checks plus browsing/history/search/deletion journeys |

## Verification

- 26 PHP tests pass, with 187 assertions. They cover empty repositories and
  directories, grouping, dotted repository names, historical children, escaped
  properties/names, pagination, search, copies, renames, deletions, and subpath rejection.
- All six existing JavaScript checks pass, and the asset build passes.
- Formatting passes after the implementation changes.
- The frontend skill's static audit reports no findings; this is not browser proof.
- The updated browser harness was attempted with cached Chromium. It cannot
  launch because the sandbox denies `setsockopt`. No visual or browser pass is claimed.
- Reference folders and generated fixtures remain ignored. Work remains uncommitted.

## Local review

Keep your local server running, or run `php artisan serve`. Start at
`/listing.php?repname=fixture&path=%2F`. Follow directories into source, open History,
visit a revision, and follow its changed paths. Specific cases are listed
in [README.md](../README.md).

Focus this review on broken workflows or incorrect content. Detailed UI notes can
wait until UAT. Run `npm run test:browser` on a host that permits Chromium to
complete the outstanding automated browser checks.

The next planned scope is the remaining endpoint families: arbitrary comparison,
RSS, downloads/raw responses, and deployed URL aliases. Production configuration,
access restrictions, resource limits, browser checks, and the agreed compatibility
subset must be settled before declaring the product UAT-ready.
