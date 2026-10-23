<?php

use App\Svn\Fixture;
use Illuminate\Support\Facades\Artisan;

Artisan::command('svn:fixture', function () {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Fixture creation is restricted to local/testing environments.');

        return 1;
    }
    $path = storage_path('app/fixture-v3');
    if (is_dir($path)) {
        $this->error('Fixture already exists. Keep it, or remove storage/app/fixture-v3 before rebuilding.');

        return 1;
    }
    Fixture::create($path, true);
    $this->info('Created fixture repository at '.$path.'/repository');
})->purpose('Create the disposable, local Phase 3 SVN repository');
