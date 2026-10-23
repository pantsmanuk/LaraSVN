<?php

namespace App\Http\Controllers;

use App\Http\RepositoryRequest;
use App\Svn\SvnClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class BrowseController extends Controller
{
    public function index(SvnClient $svn): View|RedirectResponse
    {
        $configured = $svn->repositories();
        if (count($configured) === 1) {
            return redirect()->route('listing', ['repname' => array_key_first($configured), 'path' => '/']);
        }
        $repositories = [];
        foreach ($configured as $name => $config) {
            try {
                $info = $svn->info($name, '/');
                $error = null;
            } catch (HttpExceptionInterface $exception) {
                $info = null;
                $error = 'Repository is currently unavailable.';
            }
            $repositories[] = ['name' => $name, 'group' => $config['group'] ?? '', 'info' => $info, 'error' => $error];
        }

        return view('repositories', compact('repositories'));
    }

    public function listing(Request $request, SvnClient $svn): View|RedirectResponse
    {
        $input = RepositoryRequest::parse($request, ['page' => ['sometimes', 'integer', 'min:1', 'max:1000000']]);
        $repository = $input['repname'];
        $info = $svn->info($repository, $input['path'], $input['rev'], $input['peg']);
        if ($info['kind'] === 'file') {
            return redirect()->route('source', ['repname' => $repository, 'path' => $input['path'], 'rev' => $info['revision'], 'peg' => $info['peg']]);
        }
        $path = $info['historicalPath'] ?: '/';
        $revision = $peg = $info['revision'];
        $params = ['repname' => $repository, 'path' => $path, 'rev' => $revision, 'peg' => $peg];
        $entries = $this->paginate($svn->listing($repository, $path, $revision, $peg), $request, $params, 50);
        $properties = $svn->properties($repository, $path, $revision, $peg);
        $mode = 'listing';
        $isDirectory = true;

        return view('listing', compact('mode', 'repository', 'path', 'revision', 'peg', 'params', 'entries', 'properties', 'info', 'isDirectory'));
    }

    public function search(Request $request, SvnClient $svn): View
    {
        $input = RepositoryRequest::parse($request, [
            'search' => ['sometimes', 'string', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
        ]);
        $repository = $input['repname'];
        $info = $svn->info($repository, $input['path'], $input['rev'], $input['peg']);
        abort_unless($info['kind'] === 'dir', 400, 'Filename search requires a directory.');
        $path = $info['historicalPath'] ?: '/';
        $revision = $peg = $info['revision'];
        $search = $input['search'] ?? '';
        $params = ['repname' => $repository, 'path' => $path, 'rev' => $revision, 'peg' => $peg];
        // SVN owns filename glob matching. An empty query never recursively scans a repository.
        $results = $search === '' ? [] : $svn->listing($repository, $path, $revision, $peg, $search);
        $entries = $this->paginate($results, $request, $params + ['search' => $search], 50);
        $mode = 'search';
        $isDirectory = true;

        return view('search', compact('mode', 'repository', 'path', 'revision', 'peg', 'params', 'search', 'entries', 'isDirectory'));
    }

    public function log(Request $request, SvnClient $svn): View
    {
        $input = RepositoryRequest::parse($request, [
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'search' => ['sometimes', 'string', 'max:200'],
            'sr' => ['sometimes', 'string', 'max:10', 'regex:/^(?:HEAD|[0-9]+)?$/i'],
            'er' => ['sometimes', 'string', 'max:10', 'regex:/^(?:HEAD|[0-9]+)?$/i'],
            'fr' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'max' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'all' => ['sometimes', 'in:0,1'],
            'isdir' => ['sometimes', 'in:0,1'],
            'showchanges' => ['sometimes', 'in:0,1'],
        ]);
        $repository = $input['repname'];
        $path = $input['path'];
        $info = $svn->info($repository, $path, $input['rev'], $input['peg']);
        $revision = $info['revision'];
        $peg = $info['peg'];
        $navigationPath = $info['historicalPath'];
        $isDirectory = $info['kind'] === 'dir';
        $search = trim($input['search'] ?? '');
        $max = (int) ($input['max'] ?? ($search === '' ? 40 : 0));
        $range = function (string $value, int $default) use ($svn, $repository): int {
            return strtoupper($value) === 'HEAD' ? $svn->head($repository) : ((int) $value ?: $default);
        };
        $start = $range($input['sr'] ?? '', $revision);
        $end = $range($input['er'] ?? '', 1);
        $limit = $max ?: 1000;
        $history = $revision === 0 ? [] : $svn->history($repository, $path, $start, $peg, $limit + 1, $end, true);
        $limited = count($history) > $limit;
        abort_if($limited && $max === 0, 413, 'History exceeds the 1000-change local limit. Narrow the revision range.');
        $history = array_slice($history, 0, $limit);
        $fold = fn (string $value) => mb_strtolower(Str::ascii($value));
        $words = preg_split('/\s+/u', $fold($search), flags: PREG_SPLIT_NO_EMPTY);
        $history = array_values(array_filter($history, function ($entry) use ($words, $fold, $input) {
            if (! empty($input['fr']) && $entry['revision'] >= (int) $input['fr']) {
                return false;
            }
            foreach ($words as $word) {
                if (! str_contains($fold($entry['message']), $word) && ! str_contains($fold($entry['author']), $word)) {
                    return false;
                }
            }

            return true;
        }));
        $showChanges = ($input['showchanges'] ?? '0') === '1';
        $all = ($input['all'] ?? '0') === '1';
        $params = ['repname' => $repository, 'path' => $path, 'rev' => $revision, 'peg' => $peg];
        $filters = array_intersect_key($input, array_flip(['search', 'sr', 'er', 'fr', 'max', 'showchanges', 'all', 'isdir']));
        $query = $params + $filters;
        $entries = $this->paginate($history, $request, $query, $all ? max(1, count($history)) : 20);
        $mode = 'log';

        return view('log', compact('mode', 'repository', 'path', 'navigationPath', 'revision', 'peg', 'params', 'entries', 'search', 'showChanges', 'all', 'query', 'limited', 'max', 'isDirectory'));
    }

    public function revision(Request $request, SvnClient $svn): View
    {
        $input = RepositoryRequest::parse($request, ['isdir' => ['sometimes', 'in:0,1']]);
        $repository = $input['repname'];
        $path = $input['path'];
        $revision = $input['rev'];
        if ($revision === 0) {
            $info = $svn->info($repository, $path, 0, $input['peg']);
            $revision = $info['lastChangedRevision'];
        }
        $head = $svn->head($repository);
        // A commit belongs to the repository even when its changed path was deleted.
        $entry = $svn->history($repository, '/', $revision, $revision, 1, $revision, true)[0] ?? null;
        abort_if($entry === null, 404, 'Revision not found.');
        $peg = $revision;
        $path = '/';
        $params = ['repname' => $repository, 'path' => '/', 'rev' => $revision, 'peg' => $peg];
        $mode = 'revision';
        $isDirectory = true;

        return view('revision', compact('mode', 'repository', 'path', 'revision', 'peg', 'params', 'entry', 'head', 'isDirectory'));
    }

    private function paginate(array $items, Request $request, array $query, int $perPage): LengthAwarePaginator
    {
        $page = min(max(1, (int) $request->query('page', 1)), max(1, (int) ceil(count($items) / $perPage)));

        return new LengthAwarePaginator(array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $perPage, $page, [
            'path' => $request->url(), 'query' => $query,
        ]);
    }
}
