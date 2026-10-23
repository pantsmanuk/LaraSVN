<?php

namespace App\Svn;

final class MimeType
{
    public static function forFile(string $path, array $properties): string
    {
        $mime = $properties['svn:mime-type'] ?? '';
        // Repository properties are untrusted HTTP header inputs.
        abort_if($mime !== '' && ! preg_match('~^[a-zA-Z0-9!#$&^_.+-]+/[a-zA-Z0-9!#$&^_.+-]+(?:;[ a-zA-Z0-9=._"-]+)?$~D', $mime), 502, 'Invalid repository MIME type.');
        if ($mime !== '' && strtolower(explode(';', $mime)[0]) !== 'application/octet-stream') {
            return $mime;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'php' => 'text/x-php',
            'txt', 'md', 'sh', 'py', 'rb', 'ini', 'yml', 'yaml', 'sql', 'c', 'h', 'cpp', 'java', 'go', 'rs', 'ts' => 'text/plain',
            'js' => 'text/javascript', 'json' => 'application/json', 'css' => 'text/css',
            'html', 'htm' => 'text/html', 'xml' => 'application/xml', 'svg' => 'image/svg+xml',
            'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    public static function showSource(string $mime): bool
    {
        return in_array(strtolower(trim(explode(';', $mime)[0])), config('svn.source_mime_types', []), true);
    }
}
