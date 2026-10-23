<?php

namespace App\Svn;

use App\Http\RepositoryRequest;
use Illuminate\Support\Facades\File;
use PharData;
use ZipArchive;

final class Download
{
    public function __construct(private SvnClient $svn) {}

    public function bytes(string $repository, array $info, string $mode): array
    {
        $maxBytes = Limits::get('download_bytes');
        $maxEntries = Limits::get('download_entries');
        // Resolve the historical name once so descendants share a concrete snapshot.
        $path = $info['historicalPath'] ?: '/';
        $revision = $info['revision'];
        $target = $this->svn->target($repository, $path, $revision);
        $properties = $this->svn->xml($repository, ['proplist', '--xml', '--recursive', '-r', (string) $revision, '--', $target]);
        $executables = [];
        foreach ($properties->target as $node) {
            foreach ($node->property as $property) {
                abort_if((string) $property['name'] === 'svn:special', 415, 'Downloads containing symbolic links or special files are not supported. Select a narrower path.');
                if ((string) $property['name'] === 'svn:executable') {
                    $executables[rawurldecode((string) $node['path'])] = true;
                }
            }
        }
        $name = basename(rtrim($path, '/')) ?: str_replace(['/', '\\'], '_', $repository);
        if ($info['kind'] === 'file' && $mode === 'plain') {
            return [$this->cat($repository, $path, $revision, $maxBytes), $name, 'application/octet-stream'];
        }
        $entries = [];
        if ($info['kind'] === 'dir') {
            $xml = $this->svn->xml($repository, ['list', '--xml', '--recursive', '-r', (string) $revision, '--', $target]);
            $total = 0;
            foreach ($xml->list->entry as $entry) {
                $relative = (string) $entry->name;
                abort_if($relative === '' || str_starts_with($relative, '/'), 502, 'Invalid repository archive entry.');
                RepositoryRequest::path($relative);
                $total += (int) $entry->size;
                $entries[] = ['name' => $relative, 'kind' => (string) $entry['kind']];
                abort_if(count($entries) > $maxEntries || $total > $maxBytes, 413, 'Download exceeds the configured entry or byte limit. Select a narrower path.');
            }
        } else {
            $entries[] = ['name' => '', 'kind' => 'file'];
        }
        $root = $name.'.r'.$revision;
        $temporary = sys_get_temp_dir().'/larasvn-download-'.bin2hex(random_bytes(16));
        abort_unless(mkdir($temporary, 0700), 500, 'Could not prepare the download.');
        $archive = null;
        try {
            $archivePath = $temporary.($mode === 'zip' ? '/archive.zip' : '/archive.tar');
            if ($mode === 'zip') {
                $archive = new ZipArchive;
                abort_unless($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL) === true, 500, 'Could not create the archive.');
            } else {
                $archive = new PharData($archivePath);
            }
            if ($info['kind'] === 'dir') {
                $archive->addEmptyDir($root);
            }
            $total = 0;
            foreach ($entries as $entry) {
                $entryName = $root.($entry['name'] === '' ? '' : '/'.$entry['name']);
                if ($entry['kind'] === 'dir') {
                    $archive->addEmptyDir($entryName);
                } else {
                    $filePath = $entry['name'] === '' ? $path : rtrim($path, '/').'/'.$entry['name'];
                    $bytes = $this->cat($repository, $filePath, $revision, max(1, $maxBytes - $total));
                    $total += strlen($bytes);
                    abort_if($total > $maxBytes, 413, 'Download exceeds the configured byte limit.');
                    $added = $archive->addFromString($entryName, $bytes);
                    abort_if($added === false, 500, 'Could not add a file to the archive.');
                    $executable = isset($executables[rawurldecode($this->svn->base($repository)).$filePath]);
                    if ($archive instanceof ZipArchive) {
                        $archive->setExternalAttributesName($entryName, ZipArchive::OPSYS_UNIX, ($executable ? 0100755 : 0100644) << 16);
                    } else {
                        $archive[$entryName]->chmod($executable ? 0755 : 0644);
                    }
                }
            }
            if ($archive instanceof ZipArchive) {
                abort_unless($archive->close(), 500, 'Could not finish the archive.');
            }
            $archive = null;
            // Bound archive overhead too. All temporary content is removed before sending a response.
            abort_if(filesize($archivePath) > Limits::get('archive_bytes'), 413, 'Archive exceeds the local size limit.');
            $bytes = file_get_contents($archivePath);
            abort_if($bytes === false, 500, 'Could not read the archive.');
            if ($mode === 'gzip') {
                $bytes = gzencode($bytes);
                abort_if($bytes === false, 500, 'Could not compress the archive.');
            }
            $filename = str_replace(['/', '\\'], '_', $repository).'-'.$root.($mode === 'zip' ? '.zip' : '.tar.gz');

            $this->svn->remainingSeconds();

            return [$bytes, $filename, $mode === 'zip' ? 'application/zip' : 'application/gzip'];
        } finally {
            // Also covers partial archives and failed SVN reads. No shutdown/stream lifecycle dependency.
            unset($archive);
            File::deleteDirectory($temporary);
        }
    }

    private function cat(string $repository, string $path, int $revision, int $limit): string
    {
        return $this->svn->run($repository, ['cat', '-r', (string) $revision, '--', $this->svn->target($repository, $path, $revision)], $limit);
    }
}
