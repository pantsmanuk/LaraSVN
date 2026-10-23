# Supported behaviour and compatibility

LaraSVN implements a subset of WebSVN behaviour for read-only browsing.
Only whole, anonymously public repository roots are supported. Repositories
that require per-user or per-path access filtering must not be published.
See [deployment instructions](../DEPLOYMENT.md) for configuration and resource limits.

LaraSVN supports UTF-8 source, numeric operative/peg revisions,
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
responses use a restrictive CSP sandbox. See [comparison and download implementation notes](PHASE3.md) for the
compatibility details.

History `max` defaults to 40 entries,
or 0 when searching. A finite max shows a limit notice when more entries exist.
`max=0` requests all entries within a 1000-change local cap; exceeding that cap
returns an explicit error. Environment-backed limits, a shared 30-second SVN request budget, and a
documented PHP-FPM concurrency/memory policy are available for deployment review.

Only repository-root URLs are supported. Configured exposed subpaths are rejected
until history filtering can preserve that boundary. Configuration entries have `url`, optional `group`, `downloads` (default false), and `rss` (default true)
keys. Use `SVN_PARENT_PATH` for per-request local directory discovery, or set
`SVN_REPOSITORIES_FILE` to a JSON mapping using these fields; string
entries from Phase 1 must be migrated. Configuration errors fail closed. Production
publication requires explicit acknowledgement that all configured history is public.
`php artisan svn:check --production --repository=your-repository` checks the deployment
settings and read-only root access when run on the target host.

## Known limitations

Known compatibility limits include full language/encoding support, remaining
legacy parameter and fragment aliases, word-level change marks, legacy export
transformations, and extension-triggered gzip.

See the [endpoint implementation notes](PHASE3.md) and
[architecture](ARCHITECTURE.md) for the compatibility contract.
