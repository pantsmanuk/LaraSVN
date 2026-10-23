# LaraSVN: UI and UX guidance

Status: design brief dated 2 October 2026. Initial local proof added 19 October
2026. Local automated browser checks passed 23 October 2026; manual visual and
accessibility acceptance remains pending.

This document records the intended interface and appearance behaviour for the
Laravel replacement described in [ARCHITECTURE.md](ARCHITECTURE.md). It specifies
requirements and design direction. Automated checks do not replace manual visual
acceptance; see [UAT.md](UAT.md) for the current evidence and checklist.

## Design intent

Build a modern repository browser with a light touch. The interface provides
orientation and useful controls, then gives most of the space to repository
content. Modernity comes from typography, spacing, clear interactions, and
consistent states rather than decorative effects.

The audience is people reading source, investigating changes, and navigating SVN
history. This is a working tool. Familiar links, compact tables, and clear
revision context matter more than brand expression.

Do not add taglines, decorative captions, or subtitles that repeat a heading.
Reserve UI text for navigation, repository context, controls, instructions, and
status. Keep the space available for repository content.

"Light touch" applies equally to light and dark appearance. Both modes use quiet
surfaces, restrained accents, limited borders, and clear hierarchy. Low visual
weight must not mean faint text, hidden actions, or weak keyboard focus.

The distinguishing feature is continuity between source, diff, and blame. A user
keeps the same sense of place while moving between code, changes, and attribution.

The user wants a UAT-ready product before reviewing detailed UI notes. Development
phases prioritize complete workflows and compatibility while preserving this
design contract. Detailed cosmetic feedback belongs to UAT; each phase does not
require separate visual approval. Correctness, accessibility, and security defects
are addressed as they arise. The automated browser verification required before
UAT passed on 23 October 2026.

## Reference and decisions still open

The user identified ProjectSend v2 as the reference for the desired experience
and supplied ProjectSend 2.6.0 for direct inspection. That reference copy is no
longer included in this project; filenames below refer to its source. The
observations below come from local source, not a running instance. A reference
URL can later establish rendered appearance, first-paint behaviour, and interaction
quality. Source declarations alone do not prove those outcomes.

The staff account dashboard is the primary reference. The user spends most of
their ProjectSend time there and values its feel. Staff-dashboard familiarity
takes precedence over observations from the public pages or client portal when
choosing LaraSVN's visual language and navigation treatment.

The reference informs LaraSVN's choices without supplying its visual identity.
LaraSVN does not reproduce ProjectSend's screens, branding, or file-sharing flows.

The source review supports these choices:

| ProjectSend evidence | Principle carried into LaraSVN |
| --- | --- |
| `app.css` maps shared colour roles to separate light and dark values | Use neutral surfaces, quiet dividers, and consistent semantic colours across screens and appearances |
| `button.tsx` defines solid, outline, secondary, ghost, and link treatments | Reserve visual emphasis for the main action; use quieter treatments for navigation and secondary controls |
| `table-shell.tsx` provides compact semantic tables, muted headers, overflow, and an inline empty state | Make directory and history lists readable and consistent without dashboard decoration |
| `app-sidebar-header.tsx` separates breadcrumb context from utility controls | Separate repository/path/revision context from global appearance controls |
| `app-sidebar-layout.tsx` supplies the staff shell through `AppLayout` | Keep navigation, context, utility controls, and the content area consistent across repository views |
| `use-appearance.tsx` stores a three-way preference and resolves System using a media query | Keep preference separate from effective appearance and respond to system changes |
| `appearance-switcher.tsx` presents labelled choices with a selected checkmark | Keep the appearance control compact while making all three choices and their selected state explicit |

ProjectSend self-hosts Instrument Sans and uses a purple brand accent. Those are
reference observations, not LaraSVN font or palette decisions. Its
`package.json` and
`composer.json` declare React, Inertia, Tailwind 4,
and Laravel 12. LaraSVN retains the planned Laravel 13 and Blade approach.

The earlier off-white, graphite, teal, and IBM Plex proposal remains an option.
Exact colours, fonts, radii, spacing values, and icon family remain open until
visual comparison. The source review establishes restraint and component
consistency, not a palette to copy.

ProjectSend's appearance initialization runs from the client entry point. The
inspected hook uses a generic storage key and does not include a storage-event
handler or storage-error fallback. LaraSVN retains its own requirements for a
namespaced preference, cross-tab synchronization, storage failure, and first
paint. The reference's implementation is not a substitute for those checks.

## Staff dashboard reference

The relevant page is
`pages/dashboard.tsx`, reached
through `AppLayout`. The
separate `pages/portal/dashboard.tsx` is not the primary reference for this brief.

The source establishes these characteristics and their intended use in LaraSVN:

| Staff dashboard characteristic | LaraSVN guidance |
| --- | --- |
| `app-sidebar.tsx` and `nav-main.tsx` group navigation and mark the active destination | Provide predictable orientation, readable groups, and a clear current location; adapt destinations to repository browsing |
| `app-shell.tsx` retains the sidebar's open state | Consider a collapsible repository-navigation rail that can yield width to source and diff |
| The shared staff header separates breadcrumbs from appearance and other utilities | Keep path context on the reading side and global appearance controls in a stable utility position |
| `heading.tsx` separates a concise page title from muted supporting text | Establish repository context without oversized headings or decorative banners |
| `widget-box.tsx` uses a light border, restrained radius, and a consistent title/action row | Group distinct information with quiet boundaries; place section actions predictably beside their headings |
| The dashboard uses a spaced responsive grid and shared widget shells | Maintain a consistent rhythm between sections while allowing each view the width its content needs |

These features are evidence for a familiar, composed working environment. Their
effect on comfort remains informed by the user's experience rather than a browser
review performed here.

The dashboard's statistics, charts, news, account administration, draggable
widgets, and saved widget arrangements do not become LaraSVN features. Cards may
group genuinely distinct summary information, but source, diff, and blame remain
continuous reading surfaces rather than grids of widgets.

Before fixing shell geometry, compare a staff-inspired collapsible navigation
rail with the earlier compact-header proposal in source and diff wireframes.
Evaluate orientation, control placement, and available reading width at desktop
and narrow sizes. This is a future design comparison, not authorization to build
either interface now.

## Appearance modes

The application supports three explicit preferences:

| Preference | Effective appearance | Behaviour when the system setting changes |
| --- | --- | --- |
| Light | Light | Remains light |
| Dark | Dark | Remains dark |
| System | Follows `prefers-color-scheme` | Updates without a page reload |

System is the default when no valid preference has been saved. If the browser
cannot determine a system colour preference, System resolves to light.

The saved preference and effective appearance are separate values. A user who
selects System continues to see System selected even when the effective
appearance is dark. System must not be saved as whichever appearance happens
to be active at that moment.

Changing the preference applies immediately. The choice persists across page
navigation and reloads in the same browser. A saved Light or Dark choice takes
precedence over the operating system. Choosing System restores system tracking.

This preference requires no account. Browser-local storage is the intended owner,
with an application-specific key to avoid interfering with other applications on
the same origin. Changes synchronize across open application tabs. Invalid or
removed stored values resolve to System. If storage is unavailable, selection
still works on the current page without breaking navigation or reading.

The appearance control has the label **Appearance** and the choices **Light**,
**Dark**, and **System**. It stays in one consistent location in the application
shell. Use a compact labelled select or radio group with standard keyboard
behaviour and an unambiguous selected state. An icon alone is insufficient to
describe a three-way preference.

ProjectSend's icon-triggered menu demonstrates compact placement, but LaraSVN's
initial design uses a visible Appearance label. The precise select or radio
treatment remains a visual comparison decision.

Apply the resolved appearance before the first visible paint. A saved dark
preference must not produce a flash of a light page. Initial theme setup follows
the application's Content Security Policy and does not require a server round
trip. Theme changes preserve focus, scroll position, content, and selected
revision. They do not animate the whole page through intermediate colours.

Without JavaScript, system-aware CSS still provides a readable light or dark
page. Saved browser preferences and the interactive selector require JavaScript.
The page's native controls and scrollbars use the effective `color-scheme`.

## Colour and depth

Light mode uses light neutral surfaces and dark text. Dark mode uses dark neutral
surfaces and light text. Dark mode is designed independently rather than produced
by inverting the light palette.

Both modes use the same semantic roles for page background, content surface,
primary text, secondary text, borders, links, focus, selection, additions,
deletions, warnings, and errors. Components consume those roles rather than
embedding separate colour values for each screen.

Use an accent sparingly for navigation and meaningful actions. Addition and
deletion colours remain distinct from the navigation accent. Broad saturated
backgrounds, gradients, heavy shadows, and glowing dark-mode borders do not fit
this direction. Tonal differences and spacing provide most of the hierarchy.

Reserve elevation for content that actually overlays the page. Ordinary source
panels and tables do not need a shadow or a card container. Borders should
separate content when that separation helps reading, not outline every element.

Text, syntax tokens, line numbers, controls, and diff markers meet the applicable
WCAG 2.2 AA contrast requirements in both modes. Forced-colours mode uses system
colours where necessary. Focus and change meaning survive without the palette.

## Typography and density

Use one legible interface family and one monospace source family, with suitable
fallbacks. Self-host any chosen fonts. Typography must support filenames, author
names, and repository content outside ASCII.

Keep control labels, breadcrumbs, and table metadata compact but readable. Give
source a stable line height and sufficient horizontal space. Revision numbers and
line gutters align consistently. Do not shrink type to fit long paths.

Use whitespace to group related controls. Avoid both crowded toolbars and large
decorative gaps that push useful content below the viewport. Corner rounding is
restrained and consistent, not a different shape on each screen.

## Navigation and layout

The shared shell exposes repository selection, path breadcrumbs, revision
selection, and appearance. Repository, path, and revision remain identifiable
on every repository page.

Separate application identity and utilities from path, revision, and view context.
Keep the geometry provisional until the staff-inspired rail and compact-header
comparison described above. The chosen layout must give source and side-by-side
diff sufficient width, including an accessible way to collapse navigation where
needed. A directory tree serves repository navigation. Upload controls, account
menus, notification bells, and gallery layouts do not belong in the read-only
browsing shell.

Source, history, diff, and blame use consistent names, action placement, and
navigation. Actual navigation uses links so browser history, opening another tab,
and copying a URL work normally. Theme preferences do not alter content URLs.

Lists use semantic tables or lists suited to their content. Long filenames and
paths retain an accessible way to read the full value. Search results and history
preserve the committed search and pagination state in their URLs.

Assign scrolling deliberately. The document provides the main vertical reading
flow. Source and diff can scroll horizontally within their content region.
Sticky context, if used, must not hide focused controls or anchored source lines.
Scrollbars remain visible and operable in both appearances.

At narrow widths, controls can wrap and secondary metadata can move below primary
content. Desktop diff uses aligned columns. Narrow screens use a unified reading
layout that preserves the old/new line references and change markers. Changing
layout must not hide actions or require desktop hover.

## Source, diff, and blame

Prism syntax colours belong to the same theme as the surrounding application.
There is no independent code-theme selector in the first release. Switching
appearance updates source, diff, blame, and any open overlay together.

Syntax highlighting, changed-line backgrounds, word-change marks, selection,
and focus must coexist. A syntax token that is readable on an ordinary source
surface must also be readable on an added or deleted line.

Use textual addition/deletion markers and line numbers alongside colour. Blame
groups use subtle separation rather than a rainbow of author colours. Revision
and author information is readable without opening a popup.

Line links support copying and existing fragments. Selecting or copying source
must not include gutters, change markers, or attribution. Empty lines, tabs, and
long lines retain their meaning. Highlighting failure leaves readable plain text.

Commit details can appear on hover as an enhancement, but keyboard and touch
users receive an equivalent explicit control or link. Any popup uses the current
appearance and preserves focus when dismissed.

## Interaction and feedback

Controls have deliberate default, hover, focus, selected, and unavailable states.
Visible keyboard focus takes precedence over visual subtlety. Use text labels
for unfamiliar actions and accessible names for icon-only controls.

Search has an explicit submit action and a labelled clear control when non-empty.
Clearing returns focus to the input. Navigation and revision selection remain
predictable rather than depending on hidden gestures or shortcuts.

Empty directories, no search results, binary files, absent revisions, network
failures, and process timeouts have distinct messages with an appropriate next
action. Keep source and context visible when only enhancement fails.

Feedback is local to the operation. Avoid a toast for every successful navigation
or theme change. If an operation needs a loading indicator, reserve its space so
controls do not move. Motion is brief and functional, and respects reduced motion.

## Ownership during implementation

This document owns visual intent and the appearance contract. The architecture
owns repository behaviour and compatibility. A future `UX-CONTRACT.md` can hold
the detailed interaction contract without duplicating palette values.

When implementation begins, one shared stylesheet owns runtime semantic tokens
for both appearances. This document records the accepted values and their mapping
to that stylesheet. Components use those tokens consistently. Prism colours,
diff states, overlays, native controls, and scrollbar styling are included in
the mapping. Do not create a token-generation tool merely to implement two themes.

One theme controller owns preference resolution, persistence, system-change
tracking, and cross-tab updates. Screens do not implement their own theme state.
No account database or theme framework is required for this behaviour.

## Design acceptance checks

Before implementation is considered complete, verify these cases in a browser:

- A fresh visit follows both light and dark system settings.
- Light and Dark override either system setting and survive navigation and reload.
- System remains the selected preference and follows system changes without reload.
- Appearance changes synchronize across tabs without changing content state.
- Missing, invalid, or unavailable browser storage leaves a usable application.
- The initial page uses the resolved appearance without a contrasting theme flash.
- Source, compact/full diff, blame, tables, errors, and overlays work in both modes.
- Syntax, changed lines, word changes, focus, and text selection remain readable
  together, including long and non-ASCII source.
- Keyboard, touch, a narrow viewport, 200% zoom, forced colours, reduced motion,
  and JavaScript-disabled reading remain usable.
- Shared components preserve navigation, density, and feedback across sibling views.
- Compare the proposed shell with ProjectSend's staff dashboard for orientation,
  spacing, subdued surfaces, and action placement, without reproducing its layout
  or adding its product features.

These acceptance checks remain binding. Local automated browser checks passed on
23 October 2026; [UAT.md](UAT.md) distinguishes covered behaviour from the remaining
manual review. [PHASE1.md](PHASE1.md) retains the historical verification limits.
The ProjectSend source review informs the brief, but does not count as browser
verification of either application.

## Initial implementation mapping

The provisional local shell uses a narrow repository rail and a shared content
header. It is available for review, not a final choice between the two shell
directions above. `resources/css/app.css` owns exact runtime values. Semantic
roles map directly to `--page`, `--chrome`, `--text`, `--muted`, `--border`,
`--accent`, `--active`, `--focus`, `--added`, and `--deleted`. Syntax uses
`--comment`, `--keyword`, `--string`, `--number`, and `--punctuation`. Scrollbars
use `--scrollbar`. Each role has one `light-dark()` pair; components do not copy
separate theme palettes. This establishes a modern-browser baseline.

`resources/js/theme.js` selects the root `color-scheme`; system-aware CSS also
works without JavaScript. Browser-local persistence and the native labelled
appearance selector share that controller. The native popup's platform-owned
geometry is accepted. Font stacks use local fallbacks in this proof; no remote
font requests or final typography choice are introduced.


## Phase 3 comparison and snapshot tools

Arbitrary comparisons reuse the existing reading surface with a unified native
SVN patch. Two labelled From/To fieldsets establish direction. Reverse comparison
and whitespace controls stay next to the submit action. They use the same semantic
colours, form controls, and appearance controller as source and blame. At narrow
widths the fieldsets stack; only the patch region scrolls horizontally.

Shared snapshot navigation owns comparison, feeds, raw-file links, and download
formats. Downloads appear only when enabled for the repository. Feeds and raw
files use their appropriate document responses rather than a second application
shell. This phase adds no branding, dashboard widgets, or visual redesign.
Local browser checks for comparison, reversal, feeds, aliases, ZIP downloads, and
no-JavaScript patches passed on 23 October 2026. Full manual acceptance remains open.


## Phase 4 acceptance

The current shell and reading views are ready for detailed UAT notes. Deployment
and resource-policy work adds no new visual direction. Error pages use generic
repository navigation rather than fixture-specific wording. When publication is
disabled, the shell also hides configured repository names. The browser harness
now checks skip-link focus, labelled native controls, and landmarks across the
existing screens; these checks passed locally on 23 October 2026. The
manual matrix in [UAT.md](UAT.md) covers keyboard use, 200% zoom, appearance, readable
syntax colours, and no-JavaScript behaviour.
