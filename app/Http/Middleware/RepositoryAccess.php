<?php

namespace App\Http\Middleware;

use App\Svn\SvnClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RepositoryAccess
{
    public function __construct(private SvnClient $svn) {}

    public static function enabled(): bool
    {
        return config('svn.error') === null && (app()->environment(['local', 'testing']) || config('svn.public_read') === true);
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_if(config('svn.error') !== null, 503, 'Repository browsing is temporarily unavailable. Contact the server administrator.');
        abort_unless(self::enabled(), 503, 'Public repository access has not been enabled for this environment.');

        $this->svn->beginRequest();

        return $next($request);
    }
}
