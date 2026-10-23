<?php

namespace App\Http\Controllers;

use App\Http\RepositoryRequest;
use App\Svn\SvnClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CompareController extends Controller
{
    public function __invoke(Request $request, SvnClient $svn): View
    {
        $input = RepositoryRequest::parse($request, [
            'compare' => ['sometimes', 'array:0,1', 'size:2'],
            'compare.*' => ['required', 'string', 'max:2020'],
            'compare_rev' => ['sometimes', 'array:0,1', 'size:2'],
            'compare_rev.*' => ['nullable', 'integer', 'min:-2147483647', 'max:2147483647'],
            'manualorder' => ['sometimes', 'in:0,1'],
            'ignorews' => ['sometimes', 'in:0,1'],
        ]);
        $repository = $input['repname'];
        $svn->base($repository);
        $sides = [];
        $patch = null;
        $ignoreWhitespace = ($input['ignorews'] ?? '0') === '1';
        foreach (isset($input['compare']) ? [0, 1] : [] as $index) {
            $value = $input['compare'][$index];
            $revision = (int) ($input['compare_rev'][$index] ?? 0);
            if (preg_match('/@(-?[0-9]+)$/D', $value, $match)) {
                abort_if(strlen(ltrim($match[1], '-')) > 10 || abs((float) $match[1]) > 2147483647, 400, 'Invalid comparison revision.');
                $revision = (int) $match[1];
                $value = substr($value, 0, -strlen($match[0]));
            }
            $sides[] = ['path' => RepositoryRequest::path($value), 'revision' => $revision > 0 ? $revision : $svn->head($repository)];
        }
        if (count($sides) === 2) {
            if (($input['manualorder'] ?? '0') !== '1' && $sides[0]['revision'] > $sides[1]['revision']) {
                $sides = array_reverse($sides);
            }
            foreach ($sides as $side) {
                $svn->info($repository, $side['path'], $side['revision'], $side['revision']);
            }
            $patch = $svn->run($repository, ['diff', ...($ignoreWhitespace ? ['-x', '-w --ignore-eol-style'] : []), '--',
                ...array_map(fn ($side) => $svn->target($repository, $side['path'], $side['revision']), $sides),
            ]);
            // SVN patches can include arbitrary property bytes. Preserve readable output safely.
            $patch = mb_convert_encoding($patch, 'UTF-8', 'UTF-8');
        }

        return view('compare', compact('repository', 'sides', 'patch', 'ignoreWhitespace'));
    }
}
