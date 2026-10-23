# Production configuration audit

Historical configuration audit. On 23 October 2026 the user reported all seven
staging/production acceptance gates passed; see [UAT.md](UAT.md). Those results
describe the reviewed installation, including working downloads. The settings
and open checks below describe the originally supplied configuration. LaraSVN
is still non-public, with original WebSVN operating in parallel.

19 October 2026. This audit reads the supplied Apache snippet and WebSVN include
files as text. Reference PHP was not included or executed. No production
repository was opened. Local proof uses disposable fixtures only.

## Supplied configuration

| Evidence | Finding | LaraSVN consequence |
| --- | --- | --- |
| `include/config.php:117` | `parentPath("/home/svn/repositories")` | Discover readable local repository directories under that parent; preserve directory names as repname |
| `include/config.php:293` | `addInlineMimeType('text/plain')` | Production source MIME override is text/plain; other explicit SVN MIME properties may select raw responses |
| `include/config.php:329` | `setMinDownloadLevel(2)` | Depth rule exists but is inactive because downloads are disabled |
| `include/configclass.php:672` and active configuration | allowDownload defaults false and no active allowDownload call overrides it | Discovered production repositories must return 403 from dl.php and hide archive controls |
| `include/configclass.php:678–680` | RSS enabled, no caching, default 40 items | Existing RSS behaviour matches these settings |
| `include/configclass.php:665` and active configuration | MultiViews disabled, no active enabling call | Existing .php endpoints are primary; browse aliases are additional compatibility |
| `include/config.php:384,488` | GeSHi enabled; tab expansion 4 | Prism replaces highlighting; existing four-column tab display matches |
| `include/config.php:480` | Unlimited PHP execution time | Bounded request/worker policy is an intentional operational change |
| Active config statements | No access-file, credential, remote-repository, exclusion, subpath, or additional include calls | Whole-root local/public model matches the supplied application settings |
| Apache snippet:4–5 | svn.ggpsystems.co.uk with svn-1.ggpsystems.co.uk alias | Keep both names and existing HTTPS configuration |
| Apache snippet:16 | Alias /websvn to /var/www/websvn | Replace only this mapping with Laravel public plus its routing/handler block |
| Apache snippet | No authentication, reverse-proxy, or WEBSVN_PATH_CONF directive | Direct HTTPS deployment fits supplied snippet; inherited/global configuration remains a host check |

The supplied configuration files and archive were removed during release cleanup.
This document retains the audit findings.

## Implemented alignment

`SVN_PARENT_PATH` adds request-scoped discovery using the same basic visible,
readable directory and readable `db` marker as WebSVN. It does not run SVN against
all candidates merely to enumerate them. Metadata is read only when a workflow
needs it. Linked repository directories remain eligible, as in WebSVN; the public
acknowledgement covers their targets too. Parent-directory ownership must therefore
be controlled by the administrator.

Discovery remains live with Laravel config caching. Added/removed directories
appear/disappear on the next request. Explicit JSON entries override discovered
entries; discovered defaults are downloads false and RSS true. Invalid/unreadable
parents return 503 without fixture fallback. Existing URL validation limits names
to 100 bytes and excludes control characters, backslashes, and dot segments.

`SVN_SOURCE_MIME_TYPES=text/plain` supplies the observed source override for
explicit MIME properties. Untyped UTF-8 text remains readable as source, including
PHP and extensionless hooks. Raw responses preserve bytes and retain the existing
sandbox policy. The general extension map is a supported subset, not every legacy
CAD/media mapping; actual production file types and encodings need UAT coverage.

The [production environment template](../deploy/production.env.example) matches these
settings without changing local `.env`. The [Apache block](../deploy/apache-websvn.conf.example)
replaces the existing WebSVN Alias inside the supplied VirtualHost. Preserve its
ServerName, ServerAlias, certificate directives, logs, and unrelated DocumentRoot.
Only Laravel public is served. Reference source and configuration directories are
never document roots.

## Remaining host and acceptance checks

- Confirm inherited Apache rules and runtime environment do not add authentication,
  an authz/config overlay through WEBSVN_PATH_CONF, or path-specific restrictions.
  The supplied snippet cannot prove the absence of settings elsewhere.
- Confirm the PHP-FPM account has read-only access to the real parent and repository
  data, including any linked roots. Run the production preflight on the server
  with `--repository=svn-hooks`; do not inspect other repositories through the agent.
- Check the actual /websvn mount, FPM handler, HTTPS-generated links, and static
  assets using staged server configuration before replacing WebSVN.
- Keep downloads disabled. Enabling them later must revisit the existing depth-2
  rule, export keyword/EOL transformations, and symlink/externals policy. These are
  currently unreachable through the disabled production download endpoint.
- Review UTF-8 assumptions and real SVN MIME properties using svn-hooks only.
  Broader character encodings, the complete legacy extension map, and additional
  WebSVN configuration modes remain outside the documented subset.
- Complete manual accessibility/visual UAT and staged Apache browser checks on the
  user's host. Local Playwright checks passed on 23 October 2026 outside the agent
  sandbox; see [UAT.md](UAT.md). That pass does not verify the production host.

The static configuration audit is complete. Deployment activation and host/UAT
acceptance remain open. See [UAT.md](UAT.md) and [DEPLOYMENT.md](../DEPLOYMENT.md).
