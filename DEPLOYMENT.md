# Deployment and rollback

All seven staging/production acceptance gates passed in the user's review on
23 October 2026; see [UAT.md](docs/UAT.md). LaraSVN and original WebSVN remain
operational in parallel. LaraSVN is not yet publicly available; public cutover is
a separate deployment step.

Supplied production WebSVN/Apache settings have been audited in
[PRODUCTION-AUDIT.md](docs/PRODUCTION-AUDIT.md). The staging URL is inaccessible
from the development environment, so host acceptance is user-reported. The only
production repository authorised for agent inspection is `svn-hooks`.

## Supported access model

This release publishes whole repository roots anonymously. It has no user login,
SVN authz filtering, or path-based download rules. Anonymous HTTP access to WebSVN
does not prove the underlying repositories are wholly public. Check the actual
WebSVN configuration, Apache access directives, SVN transport, and authz files.
If the installation filters paths or users, do not deploy this subset.

The application process needs read access to repositories and write access to
Laravel runtime directories and its dedicated temporary directory. For local
`file://` repositories, give the application account read permission only. Do not
run PHP-FPM as the repository owner or grant repository writes. For a remote
repository, use a genuinely read-only account/anonymous transport. SVN credentials
in URLs and SSH transport are rejected by the configuration loader. Backend
credential integration is not implemented; do not infer it from public web access.

## Configuration

For the supplied installation, start with
[production.env.example](deploy/production.env.example):
`SVN_PARENT_PATH=/home/svn/repositories` and
`SVN_SOURCE_MIME_TYPES=text/plain`. The former matches active WebSVN discovery;
the latter matches its explicit source MIME override. New and removed repository
directories are resolved per request even when config:cache is enabled. Discovery
publishes visible/readable directories with readable db markers, including linked
roots. Keep parent-directory ownership restricted to the administrator. Discovered
downloads are disabled and RSS is enabled, matching the supplied configuration.

Alternatively, or for explicit per-repository overrides, copy [repositories.example.json](deploy/repositories.example.json) to a protected
location outside `public`, for example `/etc/larasvn/repositories.json`. It is JSON
data, not executable PHP. Replace the example URL with the confirmed repository
root. Do not infer the filesystem path from the public WebSVN URL. The example
keeps downloads disabled until production download policy is confirmed.

Only `url`, `group`, `downloads`, and `rss` are accepted per repository. JSON
booleans must be `true` or `false`. Repository names, including their case, are the
public `repname` values and must match existing links exactly. A configured exposed
subdirectory is rejected because history can reveal paths outside it.

Set these values in a protected production `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://svn.ggpsystems.co.uk/websvn
APP_KEY=YOUR_GENERATED_APPLICATION_KEY
SVN_REPOSITORIES_FILE=/etc/larasvn/repositories.json
SVN_PUBLIC_READ=true
LOG_CHANNEL=daily
LOG_LEVEL=warning
```

`SVN_PUBLIC_READ=true` is explicit acknowledgement that every configured root and
its entire history are public. It provides no authentication. Without it,
staging/production browsing returns 503. Without an explicit repositories file or parent path, those environments have
no fixture fallback. Invalid configuration fails closed.
The Laravel liveness endpoint `/up` is not repository readiness evidence.

Use `php artisan key:generate` when first creating the production environment,
then retain the key across releases. Never copy development `.env` or cached
configuration to the server. No application database, queue worker, or session
storage is needed for these stateless GET endpoints. HTTP responses do not issue
session cookies. Appearance preferences stay in browser-local storage.

Configuration caching captures repository JSON and the parent path setting, but
not the directory inventory. Run `config:cache` again after changing JSON or
environment settings; adding/removing children below the same parent needs no
cache rebuild. Treat cached configuration and
logs as private. Keep the previous release's own configuration/cache during
rollback; do not share a cache compiled for a different release/environment.

## Capacity defaults

| Setting | Default | Boundary |
| --- | --- | --- |
| `SVN_PROCESS_SECONDS` | 15 | Each SVN command |
| `SVN_REQUEST_SECONDS` | 30 | Shared elapsed budget across a request's SVN client and archive work |
| `SVN_DISPLAY_BYTES` | 2000000 | Each SVN metadata/source/raw/patch result, including stderr |
| `SVN_SOURCE_LINES` | 20000 | Display source before line arrays/HTML are built; raw bypasses this line cap |
| `SVN_DOWNLOAD_BYTES` | 20000000 | Total stored file bytes in one download |
| `SVN_ARCHIVE_BYTES` | 22000000 | Uncompressed tar or completed ZIP before reading it into memory |
| `SVN_DOWNLOAD_ENTRIES` | 1000 | Descendant archive entries |

All settings must be positive integers. History/feed requests retain the existing
1000-change cap. Limits return 413, exhausted request/command time returns 504,
and invalid policy returns 503. Each request uses one scoped SVN client so
repeated HEAD reads and nested download services share the same snapshot/budget.

The [dedicated PHP-FPM pool example](deploy/php-fpm.conf.example) uses four workers,
256 MB per worker, and a 40-second hard request timeout. This caps concurrent
requests, including archive creation, without application-wide locking. Reserve
at least that memory allocation plus PHP/web-server overhead; lower worker count
for a smaller host. Measure before increasing byte/entry limits. The PHP-FPM
hard timeout also covers HTML rendering and stalled PHP code beyond SVN work.

Normal completion and exceptions clean temporary archives before responses are
returned. A killed process cannot run `finally`; the [tmpfiles example](deploy/tmpfiles.conf.example)
cleans stale content after a day in a directory used exclusively by this app.
Ensure the application account owns it with mode 0700. The CLI account used for
checks should have equivalent temporary-directory and repository permissions.

## Stage a release

1. Create a versioned release directory. Deploy application files and lockfiles.
   Exclude reference source trees, `.env`, caches, fixtures, and development logs.
   Serve only the release's `public` directory.
2. Run `composer install --no-dev --prefer-dist --optimize-autoloader` and build
   assets with `npm ci && npm run build` in the build environment. Production PHP
   needs the Composer-declared extensions plus mbstring and normal Laravel
   extensions. Install SVN 1.14+ and GNU diff. `svnadmin` is required only for fixtures/tests.
3. Set up the protected environment and parent path or repository JSON, application account,
   runtime/log permissions, and dedicated temporary directory. Do not create a
   fixture on production.
4. Run `php artisan route:clear`. Build `php artisan config:cache` and
   `php artisan view:cache` as the production account in the production environment.
   Leave routes uncached for subdirectory mounts. The current Laravel cached
   matcher trims `/websvn/` to `/websvn` before resolving the base URL, causing
   the homepage to return 405 with "Supported methods: HEAD". Avoid `route:cache`;
   if using `php artisan optimize`, pass `--except=routes` and clear any existing
   route cache. `svn:check` checks repository access and settings, not HTTP routing.
   Run `php -d sys_temp_dir=/var/tmp/larasvn artisan svn:check --production --repository=svn-hooks` with the same
   permissions and temporary-directory policy as PHP-FPM. The checker reads root
   metadata only; it does not change repositories or print backend URLs.
5. Adapt the [Apache example](deploy/apache-websvn.conf.example) within the existing
   HTTPS VirtualHost. Keep the current WebSVN service available while testing a
   staging hostname/mount. Required modules include alias, dir, rewrite, headers,
   proxy, and proxy_fcgi. Check Apache and PHP-FPM configuration syntax using the
   host's installed binaries before reload. Examples have not been exercised
   against this production host.
6. Validate `/websvn/`, `/websvn/listing.php?repname=svn-hooks&path=%2F`, assets, a permitted
   source page, historical links, feeds, aliases, and the production download policy.
   Only inspect `svn-hooks`. Follow [UAT.md](docs/UAT.md) for the full fixture checklist.
7. Switch the mount/release pointer only after acceptance. Reload PHP-FPM to drop
   OPcache references to the previous release; reload Apache if its mapping changes.
   Recheck status, content, asset URLs, and private logs.

The examples target direct HTTPS Apache with a dedicated PHP-FPM pool. Existing
reverse proxies, external authentication, URL rewriting before the mount, or an
Alias outside `/websvn` require adapting and testing the actual server setup.
Do not enable broad forwarded-header trust to make generated URLs look correct.

## Roll back

Keep the old WebSVN code, configuration, and mount configuration intact until the
replacement is accepted. LaraSVN performs no repository migration or database
writes, so rollback restores the previous mount/release mapping and reloads the
relevant services. Restore the previous release's environment/configuration cache
as well. Recheck the original bookmarked URL, source, history, and RSS. Repository
contents do not need restoration because this application never modifies them.
