<?php

namespace App\Svn;

use SimpleXMLElement;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class SvnClient
{
    private array $heads = [];

    private ?array $repositories = null;

    private float $startedAt;

    public function __construct()
    {
        $this->beginRequest();
    }

    public function beginRequest(): void
    {
        $this->heads = [];
        $this->repositories = null;
        $this->startedAt = hrtime(true) / 1e9;
    }

    public function remainingSeconds(): float
    {
        $remaining = Limits::get('request_seconds') - (hrtime(true) / 1e9 - $this->startedAt);
        abort_if($remaining <= 0, 504, 'This repository request exceeded its time limit. Select a narrower path or range.');

        return $remaining;
    }

    public function repositories(): array
    {
        if ($this->repositories === null) {
            $discovered = ['repositories' => [], 'error' => null];
            if (is_string(config('svn.parent_path')) && config('svn.parent_path') !== '') {
                $discovered = RepositoryConfiguration::discover(config('svn.parent_path'));
            }
            abort_if($discovered['error'] !== null, 503, $discovered['error'] ?? 'Repository discovery failed.');
            // Explicit entries win, matching WebSVN's default skipAlreadyAdded behaviour.
            $this->repositories = array_replace($discovered['repositories'], config('svn.repositories', []));
        }

        return $this->repositories;
    }

    public function base(string $repository): string
    {
        $configured = $this->repositories();
        $base = $configured[$repository]['url'] ?? null;
        abort_unless(is_string($base), 404, 'Repository not found.');

        return rtrim($base, '/');
    }

    public function run(string $repository, array $arguments, ?int $maxBytes = null): string
    {
        $this->base($repository);
        $maxBytes ??= Limits::get('display_bytes');
        $process = new Process(['svn', '--non-interactive', '--no-auth-cache', ...$arguments], env: ['LC_ALL' => 'C.UTF-8']);
        $process->setTimeout(min(Limits::get('process_seconds'), $this->remainingSeconds()));
        $output = $error = '';
        try {
            $process->start();
            foreach ($process as $type => $bytes) {
                if ($type === Process::OUT) {
                    $output .= $bytes;
                } else {
                    $error .= $bytes;
                }
                abort_if(strlen($output) + strlen($error) > $maxBytes, 413, 'This Subversion result exceeds the local size limit. Narrow the path or revision range.');
            }
        } catch (ProcessTimedOutException) {
            throw new HttpException(504, 'Subversion did not respond in time.');
        } finally {
            if ($process->isRunning()) {
                $process->stop(0);
            }
        }
        if (! $process->isSuccessful()) {
            if (preg_match('/E(160006|160013|200009|195012)/', $error)) {
                abort(404, 'Path or revision not found.');
            }
            report(new \RuntimeException('Subversion request failed with exit '.$process->getExitCode()));
            abort(502, 'Subversion could not read this resource. Check repository availability on the server.');
        }

        return $output;
    }

    public function target(string $repository, string $path, string|int $peg): string
    {
        $base = $this->base($repository);
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return rtrim($base, '/').$encoded.'@'.$peg;
    }

    public function xml(string $repository, array $arguments): SimpleXMLElement
    {
        $output = $this->run($repository, $arguments);
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($output, options: LIBXML_NONET);
            abort_if($xml === false, 502, 'Subversion returned invalid metadata.');

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function head(string $repository): int
    {
        if (! isset($this->heads[$repository])) {
            $xml = $this->xml($repository, ['info', '--xml', '-r', 'HEAD', '--', $this->base($repository).'@HEAD']);
            $this->requireRepositoryRoot($repository, $xml);
            $this->heads[$repository] = (int) $xml->entry['revision'];
        }

        return $this->heads[$repository];
    }

    private function requireRepositoryRoot(string $repository, SimpleXMLElement $xml): void
    {
        // Exposed subpaths need history filtering before they can be supported safely.
        abort_unless(rawurldecode($this->base($repository)) === rawurldecode(rtrim((string) $xml->entry->repository->root, '/')), 503, 'Only whole repository roots are supported; subpath history cannot be published safely.');
    }

    public function info(string $repository, string $path, int $revision = 0, int $peg = 0): array
    {
        $peg = $peg ?: $this->head($repository);
        $revision = $revision ?: $peg;
        $xml = $this->xml($repository, ['info', '--xml', '-r', (string) $revision, '--', $this->target($repository, $path, $peg)]);
        $this->requireRepositoryRoot($repository, $xml);

        return [
            'revision' => (int) $xml->entry['revision'], 'peg' => $peg,
            'kind' => (string) $xml->entry['kind'],
            'lastChangedRevision' => (int) $xml->entry->commit['revision'],
            'author' => (string) $xml->entry->commit->author,
            'date' => (string) $xml->entry->commit->date,
            'historicalPath' => rawurldecode(substr((string) $xml->entry->{'relative-url'}, 1)),
        ];
    }

    public function listing(string $repository, string $path, int $revision, int $peg, ?string $search = null): array
    {
        $flags = $search === null ? [] : ['--recursive', '--search', $search];
        $xml = $this->xml($repository, ['list', '--xml', ...$flags, '-r', (string) $revision, '--', $this->target($repository, $path, $peg)]);
        $entries = [];
        foreach ($xml->list->entry as $entry) {
            $name = (string) $entry->name;
            $entries[] = [
                'name' => $name, 'path' => rtrim($path, '/').'/'.$name,
                'kind' => (string) $entry['kind'], 'size' => (int) $entry->size,
                'revision' => (int) $entry->commit['revision'],
                'author' => (string) $entry->commit->author, 'date' => (string) $entry->commit->date,
            ];
        }
        usort($entries, fn ($a, $b) => ($a['kind'] !== $b['kind'] ? ($a['kind'] === 'dir' ? -1 : 1) : strnatcasecmp($a['name'], $b['name'])));

        return $entries;
    }

    public function properties(string $repository, string $path, int $revision, int $peg): array
    {
        $xml = $this->xml($repository, ['proplist', '--xml', '--verbose', '-r', (string) $revision, '--', $this->target($repository, $path, $peg)]);
        $properties = [];
        foreach ($xml->xpath('/properties/target/property') as $property) {
            $value = (string) $property;
            if ((string) $property['encoding'] === 'base64') {
                $bytes = base64_decode($value, true);
                $value = $bytes === false || ! mb_check_encoding($bytes, 'UTF-8') || str_contains($bytes, "\0") ? 'Binary property value' : $bytes;
            }
            $properties[(string) $property['name']] = $value;
        }
        ksort($properties);

        return $properties;
    }

    public function document(string $repository, string $path, int $revision, int $peg): SourceDocument
    {
        return new SourceDocument($this->run($repository, ['cat', '-r', (string) $revision, '--', $this->target($repository, $path, $peg)]), Limits::get('source_lines'));
    }

    public function history(string $repository, string $path, int $revision, int $peg, int $limit = 2, int $end = 1, bool $verbose = false): array
    {
        $xml = $this->xml($repository, ['log', '--xml', ...($verbose ? ['--verbose'] : []), '--limit', (string) $limit, '-r', $revision.':'.$end, '--', $this->target($repository, $path, $peg)]);
        $entries = [];
        foreach ($xml->logentry as $entry) {
            $changes = [];
            foreach ($entry->paths?->path ?? [] as $change) {
                $changes[] = [
                    'path' => (string) $change, 'action' => (string) $change['action'],
                    'kind' => (string) $change['kind'],
                    'copyFrom' => (string) $change['copyfrom-path'],
                    'copyRevision' => (int) $change['copyfrom-rev'],
                    'propertiesModified' => (string) $change['prop-mods'] === 'true',
                ];
            }
            $entries[] = [
                'revision' => (int) $entry['revision'], 'author' => (string) $entry->author,
                'date' => (string) $entry->date, 'message' => (string) $entry->msg, 'changes' => $changes,
            ];
        }

        return $entries;
    }

    public function blame(string $repository, string $path, int $revision, int $peg): array
    {
        $xml = $this->xml($repository, ['blame', '--xml', '-r', '1:'.$revision, '--', $this->target($repository, $path, $peg)]);
        $lines = [];
        foreach ($xml->target->entry as $entry) {
            $lines[(int) $entry['line-number']] = [
                'revision' => isset($entry->commit) ? (int) $entry->commit['revision'] : null,
                'author' => (string) $entry->commit?->author,
            ];
        }

        return $lines;
    }
}
