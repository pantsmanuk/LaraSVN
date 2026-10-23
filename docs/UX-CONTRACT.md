# LaraSVN interaction contract

The product and compatibility source is [ARCHITECTURE.md](ARCHITECTURE.md).
The appearance and visual source is [DESIGN.md](DESIGN.md). This file records
implementation ownership for local browsing, history, source, diff, and blame.

| Capability | Owner | Behaviour | Verification |
| --- | --- | --- | --- |
| File navigation | Named Laravel routes and shared Blade tabs | Real links preserve repository, path, revision, and peg | Real-SVN feature tests |
| Revision selection | Shared `partials/context.blade.php` GET form | Explicit Go submits; invalid values produce a readable HTTP 400 page | Feature tests |
| Appearance select | Native labelled select and `theme.js` | Platform popup is accepted; three explicit preferences share one owner | JS state checks; local browser checks passed |
| Code rows | Blade source and comparison views | Server-readable text; source gutters excluded from selection; enhancement preserves line text | PHP and token tests; browser selection review pending |
| Scrollbars | `resources/css/app.css` | Global semantic colours; document vertical flow; internal code horizontal overflow | Browser review pending |
| Directory/search lists | Shared entries table and pagination | Semantic tables, directory-first ordering, 50 entries per page | Real-SVN feature tests |
| History | BrowseController and shared pagination | Revision-pinned links, preserved filters, clamped pages, 20 changes per page | Real-SVN feature tests |
| Search | GET forms and shared clear handler | Explicit submit; clear refreshes results and restores focus | Feature tests; local browser clear/focus checks passed |
| Historical links | SVN metadata and shared changed-path renderer | Deletions link before deletion; copies include source revisions | Real-SVN link-following tests |
| Properties | Shared property renderer | Escaped names and values | Real-SVN feature tests |
| Arbitrary comparison | CompareController, `compare.blade.php`, native SVN diff, Prism | Explicit GET submission, revision-pinned sides, reversible order, readable patch without JS | Real-SVN feature tests; local browser checks passed |
| Snapshot downloads | DownloadController, Download, FileResponse, shared navigation | Legacy dlmode, bounded stored bytes, private temporary archives, cleanup before response | Real-SVN archive content/permission/limit tests |
| Raw files | CodeController, MimeType, FileResponse | Exact bytes, validated MIME headers, sandboxed inline content | Real-SVN feature tests |
| Revision feeds | FeedController and shared navigation | RSS 2.0, live self links, escaped content, quiet mode | Parsed XML and link-following tests |
| Legacy browse aliases | AliasController and named routes | One decoding pass, canonical redirects, URL-owned selectors | Real-SVN dispatch/base-path tests |
| Repository visibility | RepositoryAccess and layout view composer | Production acknowledgement/configuration gate content and navigation together | Production-denial feature tests |
| Error pages | Laravel exception rendering and shared error view | Honest HTTP status and a route back to the fixture | Feature tests |
| Copy feedback | Code-view clipboard handler and local status text | Copy exact display source, report clipboard failure inline | Browser review pending |

Local automated browser results dated 23 October 2026 are recorded in
[UAT.md](UAT.md). Manual selection, clipboard feedback, scrollbar appearance, and
full accessibility review remain pending.

The locale for this proof is English. The fixture is the only configured repository.
Lists have no selection or mutation operations. Diff displays a bounded fixture,
with explicit compact/full navigation. No dialogs, account workflows, or toast
system are required.

Comparison forms reuse native labelled inputs, checkboxes, fieldsets, and the
existing GET/error-page convention. There is no asynchronous comparison state.
Path and revision values persist in successful result URLs. Invalid input returns
a readable 400 page; browser Back restores the form for correction. Comparison
has two snapshots, so it does not use the single-revision context selector.

Phase 4 keeps the existing visual design. Browsing is stateless and issues no
session cookies. Failed deployment configuration returns a generic 503 while
CLI checks explain the settings problem. Denied publication also hides repository
names from the shell. Source byte/line limits produce explicit errors; raw and
download links offer the alternative where policy allows.

Parent-directory discovery and explicit entries share SvnClient's request-scoped
repository registry. Controllers, request defaults, aliases, and navigation read
that same registry. A failed discovery still renders a usable error shell with
no repository names. Discovered production repositories inherit downloads off
and RSS on; fixture-specific overrides remain local.
