<?php

namespace App\Http;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class FileResponse
{
    public static function make(string $bytes, string $filename, string $mime, bool $inline = false): Response
    {
        $filename = str_replace(['/', '\\', "\r", "\n"], '_', $filename);
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', Str::ascii($filename)) ?: 'download';

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $filename, $fallback),
            'Content-Security-Policy' => "sandbox; default-src 'none'; base-uri 'none'; frame-ancestors 'none'",
        ]);
    }
}
