<?php

namespace App\Http;

use App\Svn\SvnClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class RepositoryRequest
{
    public static function parse(Request $request, array $rules = [], string $defaultPath = '/'): array
    {
        $query = $request->query();
        foreach (['rev', 'peg'] as $key) {
            if (($query[$key] ?? null) === '') {
                unset($query[$key]);
            }
        }
        $validator = Validator::make($query, [
            'repname' => ['sometimes', 'string', 'max:100'],
            'path' => ['sometimes', 'string', 'max:2000'],
            'rev' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'peg' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            ...$rules,
        ]);
        abort_if($validator->fails(), 400, $validator->errors()->first());
        $input = $validator->validated();
        $input['repname'] ??= array_key_first(app(SvnClient::class)->repositories()) ?? '';
        $input['path'] = self::path($input['path'] ?? $defaultPath);
        $input['rev'] = (int) ($input['rev'] ?? 0);
        $input['peg'] = (int) ($input['peg'] ?? 0);

        return $input;
    }

    public static function path(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        abort_if(strlen($path) > 2000 || preg_match('~(?:^|/)\.\.?(/|$)|[\x00-\x1f\\\\]|//~', $path), 400, 'Invalid repository path.');

        return $path;
    }
}
