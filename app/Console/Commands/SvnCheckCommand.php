<?php

namespace App\Console\Commands;

use App\Svn\Limits;
use App\Svn\SvnClient;
use Illuminate\Console\Command;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

final class SvnCheckCommand extends Command
{
    protected $signature = 'svn:check {--repository=* : Check only these repository names} {--production : Enforce deployment settings}';

    protected $description = 'Check configuration, local prerequisites, and read-only SVN root access';

    public function handle(SvnClient $svn): int
    {
        if (config('svn.error') !== null) {
            $this->error(config('svn.error'));

            return self::FAILURE;
        }
        if ($this->option('production') && (app()->environment(['local', 'testing']) || config('app.debug') || ! config('app.key') || parse_url(config('app.url'), PHP_URL_SCHEME) !== 'https' || config('svn.public_read') !== true)) {
            $this->error('Production requires APP_ENV outside local/testing, APP_DEBUG=false, APP_KEY, an HTTPS APP_URL, and SVN_PUBLIC_READ=true. Only wholly public repository roots are supported.');

            return self::FAILURE;
        }
        $svn->beginRequest();
        try {
            $repositories = $svn->repositories();
        } catch (HttpExceptionInterface $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        $names = $this->option('repository') ?: array_keys($repositories);
        if ($names === [] || array_diff($names, array_keys($repositories))) {
            $this->error('Configure at least one repository and select only configured names.');

            return self::FAILURE;
        }
        foreach (['simplexml', 'xmlwriter', 'zip', 'Phar', 'zlib', 'mbstring'] as $extension) {
            if (! extension_loaded($extension)) {
                $this->error('Missing PHP extension: '.$extension);

                return self::FAILURE;
            }
        }
        foreach (['theme.js', 'code.js', 'app.css'] as $asset) {
            if (! is_file(public_path('build/'.$asset))) {
                $this->error('Built assets missing. Run npm ci and npm run build.');

                return self::FAILURE;
            }
        }
        foreach ([storage_path('framework/views'), storage_path('logs'), sys_get_temp_dir()] as $directory) {
            if (! is_dir($directory) || ! is_writable($directory)) {
                $this->error('Runtime directories must be writable by the application account.');

                return self::FAILURE;
            }
        }
        try {
            foreach (array_keys(config('svn.limits')) as $name) {
                Limits::get($name);
            }
            $diff = new Process(['diff', '--version']);
            if ($diff->setTimeout(5)->run() !== 0 || ! str_contains($diff->getOutput(), 'GNU diffutils')) {
                $this->error('GNU diff is required.');

                return self::FAILURE;
            }
            foreach ($names as $name) {
                $info = $svn->info($name, '/');
                $this->info($name.': repository root readable at r'.$info['revision']);
            }
        } catch (HttpExceptionInterface $error) {
            $this->error('SVN check failed (HTTP '.$error->getStatusCode().'): '.$error->getMessage());

            return self::FAILURE;
        } catch (\Throwable) {
            $this->error('Prerequisite check failed. Check SVN/diff binaries, permissions, and the private application log.');

            return self::FAILURE;
        }
        $this->info('Checks passed. This verifies whole-root read access, not Apache routing or per-path authz.');

        return self::SUCCESS;
    }
}
