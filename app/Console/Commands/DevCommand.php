<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class DevCommand extends Command
{
    protected $signature = 'dev {--host=127.0.0.1} {--port=8000}';

    protected $description = 'Serve LaraSVN and watch CSS/JavaScript assets for local development';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('The dev command requires APP_ENV=local. Use PHP-FPM for deployment.');

            return self::FAILURE;
        }
        $host = $this->option('host');
        $port = filter_var($this->option('port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if (($host !== 'localhost' && filter_var($host, FILTER_VALIDATE_IP) === false) || $port === false) {
            $this->error('Use a valid IP address or localhost and a port between 1 and 65535.');

            return self::FAILURE;
        }
        $output = fn ($type, $bytes) => $this->output->write($bytes);
        $build = new Process(['node', base_path('scripts/build.mjs')], base_path());
        if ($build->run($output) !== 0) {
            return self::FAILURE;
        }
        $processes = [
            new Process(['node', base_path('scripts/build.mjs'), '--watch'], base_path()),
            new Process([PHP_BINARY, base_path('artisan'), 'serve', '--no-reload', '--tries=1', '--host='.$host, '--port='.$port], base_path()),
        ];
        $stopping = false;
        $this->trap([SIGINT, SIGTERM], function () use (&$stopping) {
            $stopping = true;
        });
        $this->info('Starting local server and asset watcher. Press Ctrl+C to stop both.');
        $status = self::SUCCESS;
        try {
            foreach ($processes as $process) {
                $process->setTimeout(null)->start($output);
            }
            while (! $stopping) {
                foreach ($processes as $process) {
                    if (! $process->isRunning()) {
                        $status = $process->getExitCode() ?: self::FAILURE;
                        break 2;
                    }
                }
                usleep(100_000);
            }
        } finally {
            // Serve owns its PHP server child; the watcher owns its esbuild child.
            foreach (array_reverse($processes) as $process) {
                if ($process->isRunning()) {
                    $process->stop(3, SIGTERM);
                }
            }
            $this->untrap();
        }

        return $status;
    }
}
