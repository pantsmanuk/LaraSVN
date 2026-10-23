<?php

namespace App\Svn;

use JsonException;
use stdClass;

final class RepositoryConfiguration
{
    /** Read data only. Never include a deployment-supplied PHP configuration file. */
    public static function load(?string $file, string $environment, string $fixtureUrl, ?string $parentPath = null): array
    {
        if ($file === null || $file === '') {
            if ($parentPath !== null && $parentPath !== '') {
                return ['repositories' => [], 'error' => null];
            }

            return in_array($environment, ['local', 'testing'], true)
                ? ['repositories' => ['fixture' => ['url' => $fixtureUrl, 'group' => 'Local repositories', 'downloads' => true, 'rss' => true]], 'error' => null]
                : self::error('Set SVN_REPOSITORIES_FILE for this environment.');
        }
        if (! is_file($file) || ! is_readable($file)) {
            return self::error('SVN_REPOSITORIES_FILE must name a readable JSON file.');
        }
        $bytes = @file_get_contents($file);
        if ($bytes === false) {
            return self::error('Repository configuration could not be read.');
        }
        try {
            $records = json_decode($bytes, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::error('Repository configuration must be valid JSON.');
        }
        if (! $records instanceof stdClass) {
            return self::error('Repository configuration must be an object keyed by repository name.');
        }
        $repositories = [];
        foreach (get_object_vars($records) as $name => $record) {
            $name = (string) $name;
            if (! self::validName($name)) {
                return self::error('Repository names must be nonempty paths without control characters or dot segments.');
            }
            if (! $record instanceof stdClass) {
                return self::error('Each repository must contain a settings object.');
            }
            $entry = get_object_vars($record);
            if (array_diff(array_keys($entry), ['url', 'group', 'downloads', 'rss']) !== []) {
                return self::error('Unsupported repository settings. This release supports whole public repository roots, group, downloads, and rss only.');
            }
            $url = $entry['url'] ?? null;
            if (! is_string($url) || ! self::validUrl($url)) {
                return self::error('Repository URLs must use file, http, https, or svn without embedded credentials, query strings, or fragments.');
            }
            if (isset($entry['group']) && ! is_string($entry['group'])) {
                return self::error('Repository group must be a string.');
            }
            foreach (['downloads', 'rss'] as $flag) {
                if (array_key_exists($flag, $entry) && ! is_bool($entry[$flag])) {
                    return self::error('Repository downloads and rss settings must be JSON booleans.');
                }
            }
            $repositories[$name] = $entry + ['downloads' => false, 'rss' => true];
        }

        return ['repositories' => $repositories, 'error' => null];
    }

    /** Discovery is request-scoped, so config:cache never freezes the directory inventory. */
    public static function discover(string $parentPath): array
    {
        if (! str_starts_with($parentPath, '/') || ! is_dir($parentPath) || ! is_readable($parentPath)) {
            return self::error('SVN_PARENT_PATH must be a readable absolute directory.');
        }
        $repositories = [];
        try {
            foreach (new \FilesystemIterator($parentPath) as $entry) {
                $name = $entry->getFilename();
                $path = $entry->getPathname();
                // Match WebSVN parentPath: visible/readable directories containing readable db.
                if (str_starts_with($name, '.') || ! is_dir($path) || ! is_readable($path) || ! is_dir($path.'/db') || ! is_readable($path.'/db')) {
                    continue;
                }
                if (! self::validName($name)) {
                    return self::error('Discovered repository name cannot be represented by the supported URL subset.');
                }
                $url = 'file://'.implode('/', array_map('rawurlencode', explode('/', $path)));
                $repositories[$name] = ['url' => $url, 'downloads' => false, 'rss' => true];
            }
        } catch (\UnexpectedValueException) {
            return self::error('SVN_PARENT_PATH could not be enumerated.');
        }
        uksort($repositories, 'strnatcasecmp');

        return ['repositories' => $repositories, 'error' => null];
    }

    private static function validName(string $name): bool
    {
        return $name !== '' && ! str_starts_with($name, '/') && ! str_ends_with($name, '/') && strlen($name) <= 100
            && ! preg_match('~[\x00-\x1f\\\\]|(?:^|/)\.\.?(/|$)|//~', $name);
    }

    private static function validUrl(string $url): bool
    {
        try {
            $parts = parse_url($url);
        } catch (\ValueError) {
            return false;
        }
        if ($parts === false || (preg_match('/[\x00-\x20\x7f]/', $url) || preg_match('/[\x00-\x1f\x7f]/', rawurldecode($url))) || array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($parts))) {
            return false;
        }
        $scheme = $parts['scheme'] ?? '';
        if ($scheme === 'file') {
            return str_starts_with($url, 'file:///') && str_starts_with($parts['path'] ?? '', '/');
        }

        return in_array($scheme, ['http', 'https', 'svn'], true) && ($parts['host'] ?? '') !== '';
    }

    private static function error(string $message): array
    {
        return ['repositories' => [], 'error' => $message];
    }
}
