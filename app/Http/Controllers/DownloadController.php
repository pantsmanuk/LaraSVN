<?php

namespace App\Http\Controllers;

use App\Http\FileResponse;
use App\Http\RepositoryRequest;
use App\Svn\Download;
use App\Svn\SvnClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class DownloadController extends Controller
{
    public function __invoke(Request $request, SvnClient $svn, Download $download): Response
    {
        $input = RepositoryRequest::parse($request, ['dlmode' => ['sometimes', 'string', 'max:20']]);
        $repository = $input['repname'];
        $svn->base($repository);
        abort_unless($svn->repositories()[$repository]['downloads'] ?? false, 403, 'Downloads are disabled for this repository.');
        $info = $svn->info($repository, $input['path'], $input['rev'], $input['peg']);
        if ($input['rev'] === 0 && $info['revision'] !== 0) {
            $latest = $svn->history($repository, $input['path'], $info['revision'], $info['peg'], 1)[0]['revision'] ?? $info['revision'];
            $info = $svn->info($repository, $input['path'], $latest, $info['peg']);
        }
        $default = $info['kind'] === 'file' ? 'plain' : 'gzip';
        $allowed = $info['kind'] === 'file' ? ['plain', 'zip', 'gzip'] : ['zip', 'gzip'];
        $mode = in_array($input['dlmode'] ?? '', $allowed, true) ? $input['dlmode'] : $default;
        [$bytes, $filename, $mime] = $download->bytes($repository, $info, $mode);

        return FileResponse::make($bytes, $filename, $mime);
    }
}
