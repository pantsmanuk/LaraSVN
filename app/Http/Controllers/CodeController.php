<?php

namespace App\Http\Controllers;

use App\Http\FileResponse;
use App\Http\RepositoryRequest;
use App\Svn\Comparison;
use App\Svn\Limits;
use App\Svn\MimeType;
use App\Svn\SourceDocument;
use App\Svn\SvnClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class CodeController extends Controller
{
    public function source(Request $request, SvnClient $svn): View|Response
    {
        return $this->show($request, $svn, 'source');
    }

    public function diff(Request $request, SvnClient $svn): View
    {
        return $this->show($request, $svn, 'diff');
    }

    public function blame(Request $request, SvnClient $svn): View
    {
        return $this->show($request, $svn, 'blame');
    }

    private function show(Request $request, SvnClient $svn, string $mode): View|Response
    {
        $input = RepositoryRequest::parse($request, [
            'usemime' => ['sometimes', 'in:0,1'],
            'all' => ['sometimes', 'in:0,1'],
            'ignorews' => ['sometimes', 'in:0,1'],
        ], '/example.php');
        $repository = $input['repname'];
        $path = $input['path'];
        $info = $svn->info($repository, $path, $input['rev'], $input['peg']);
        abort_unless($info['kind'] === 'file', 404, 'Select a file to view source.');
        $revision = $input['rev'] ?: $info['lastChangedRevision'];
        $peg = $info['peg'];
        $navigationPath = $info['historicalPath'];
        $properties = $svn->properties($repository, $path, $revision, $peg);
        $sourceBytes = null;
        if ($mode === 'source') {
            $mime = MimeType::forFile($path, $properties);
            $sourceBytes = $svn->run($repository, ['cat', '-r', (string) $revision, '--', $svn->target($repository, $path, $peg)]);
            // Extensionless hooks and other untyped UTF-8 files remain readable as source.
            $untypedText = ! isset($properties['svn:mime-type'])
                && ($mime === 'application/octet-stream' || str_starts_with($mime, 'text/') || in_array($mime, ['application/json', 'application/xml', 'application/javascript'], true))
                && ! str_contains($sourceBytes, "\0") && mb_check_encoding($sourceBytes, 'UTF-8');
            if (($input['usemime'] ?? '0') === '1' || (! MimeType::showSource($mime) && ! $untypedText)) {
                return FileResponse::make($sourceBytes, basename($path), $mime, true);
            }
        }
        $history = $svn->history($repository, $path, $revision, $peg);
        abort_if($history === [], 404, 'No history for this file.');
        if ($mode === 'diff') {
            $revision = $history[0]['revision'];
        }
        $document = $sourceBytes === null ? $svn->document($repository, $path, $revision, $peg) : new SourceDocument($sourceBytes, Limits::get('source_lines'));
        $previous = $history[1]['revision'] ?? null;
        $old = null;
        $rows = $attribution = [];
        $all = ($input['all'] ?? '0') === '1';
        $ignoreWhitespace = ($input['ignorews'] ?? '0') === '1';
        if ($mode === 'diff' && $previous !== null) {
            $old = $svn->document($repository, $path, $previous, $peg);
            $rows = Comparison::rows($old, $document, $all, $ignoreWhitespace);
        } elseif ($mode === 'blame') {
            $attribution = $svn->blame($repository, $path, $revision, $peg);
            abort_unless(count($attribution) === count($document->lines), 502, 'Source and attribution do not agree.');
        }
        $params = ['repname' => $repository, 'path' => $path, 'rev' => $revision, 'peg' => $peg];

        return view('code', compact('mode', 'repository', 'path', 'revision', 'peg', 'document', 'old', 'previous', 'rows', 'attribution', 'history', 'all', 'ignoreWhitespace', 'params', 'properties', 'navigationPath'));
    }
}
