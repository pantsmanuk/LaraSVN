<?php

use App\Svn\RepositoryConfiguration;

$settings = RepositoryConfiguration::load(
    env('SVN_REPOSITORIES_FILE'),
    env('APP_ENV', 'production'),
    'file://'.storage_path('app/fixture-v3/repository'),
    env('SVN_PARENT_PATH'),
);

return [
    ...$settings,
    'parent_path' => env('SVN_PARENT_PATH'),
    // This release has no per-user/path authz. Production publication is explicit.
    'public_read' => env('SVN_PUBLIC_READ', false),
    'limits' => [
        'process_seconds' => env('SVN_PROCESS_SECONDS', 15),
        'request_seconds' => env('SVN_REQUEST_SECONDS', 30),
        'source_lines' => env('SVN_SOURCE_LINES', 20_000),
        'display_bytes' => env('SVN_DISPLAY_BYTES', 2_000_000),
        'download_bytes' => env('SVN_DOWNLOAD_BYTES', 20_000_000),
        'archive_bytes' => env('SVN_ARCHIVE_BYTES', 22_000_000),
        'download_entries' => env('SVN_DOWNLOAD_ENTRIES', 1000),
    ],
    'source_mime_types' => array_values(array_filter(array_map('trim', explode(',', env(
        'SVN_SOURCE_MIME_TYPES',
        'text/plain,text/x-php,application/x-httpd-php,application/json,text/javascript,application/javascript,text/css,text/html,application/xml,text/xml,image/svg+xml',
    ))))),
];
