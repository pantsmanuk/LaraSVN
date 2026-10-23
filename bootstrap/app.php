<?php

use App\Http\Middleware\RepositoryAccess;
use App\Http\Middleware\ResponseHeaders;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ResponseHeaders::class);
        $middleware->web(append: [RepositoryAccess::class], remove: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
        ]);
        $middleware->trimStrings(except: ['path', 'compare.*']);
        $middleware->convertEmptyStringsToNull(except: [fn () => true]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpExceptionInterface $error, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }
            $status = $error->getStatusCode();

            return response()->view('errors.http', [
                'status' => $status,
                'message' => $error->getMessage() ?: 'This resource is unavailable.',
            ], $status, $error->getHeaders());
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
