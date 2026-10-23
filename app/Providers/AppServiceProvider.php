<?php

namespace App\Providers;

use App\Http\Middleware\RepositoryAccess;
use App\Svn\SvnClient;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SvnClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('layout', function (View $view) {
            $repositories = [];
            if (RepositoryAccess::enabled()) {
                try {
                    $repositories = app(SvnClient::class)->repositories();
                } catch (HttpExceptionInterface) {
                    // Keep the shell usable when rendering a failed-discovery error.
                }
            }
            $view->with('navigationRepositories', $repositories);
        });
    }
}
