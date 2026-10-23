<?php

namespace App\Http\Controllers;

use App\Http\RepositoryRequest;
use App\Svn\SvnClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AliasController extends Controller
{
    public function __invoke(Request $request, SvnClient $svn, ?string $location = null): RedirectResponse
    {
        $op = $request->query('op', '');
        abort_unless(is_string($op), 400, 'Invalid operation.');
        if ($op === 'rep') {
            $input = RepositoryRequest::parse($request);
            $svn->base($input['repname']);

            return redirect()->route('listing', ['repname' => $input['repname'], 'path' => '/']);
        }
        if ($location === null || $location === '') {
            return redirect()->route('repositories');
        }
        $names = array_map('strval', array_keys($svn->repositories()));
        usort($names, fn ($a, $b) => strlen($b) <=> strlen($a));
        $repository = null;
        foreach ($names as $name) {
            if ($location === $name || str_starts_with($location, $name.'/')) {
                $repository = $name;
                break;
            }
        }
        abort_if($repository === null, 404, 'Repository not found.');
        $path = RepositoryRequest::path(substr($location, strlen($repository)));
        $query = $request->query();
        unset($query['op']);
        $query['repname'] = $repository;
        $query['path'] = $path;
        $routes = ['dir' => 'listing', 'file' => 'source', 'revision' => 'revision', 'log' => 'log', 'diff' => 'diff', 'blame' => 'blame', 'rss' => 'rss', 'dl' => 'download', 'comp' => 'compare', 'search' => 'search'];
        $route = $routes[$op] ?? null;
        if ($route === null) {
            $input = RepositoryRequest::parse(new Request($query));
            $info = $svn->info($repository, $path, $input['rev'], $input['peg']);
            $route = $info['kind'] === 'file' ? 'source' : 'listing';
            $query['rev'] = $info['revision'];
            $query['peg'] = $info['peg'];
        }

        return redirect()->route($route, $query);
    }
}
