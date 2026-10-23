<?php

namespace App\Svn;

use Symfony\Component\Process\Process;

final class Comparison
{
    public static function rows(SourceDocument $old, SourceDocument $new, bool $all, bool $ignoreWhitespace): array
    {
        $directory = sys_get_temp_dir().'/larasvn-'.bin2hex(random_bytes(12));
        if (! mkdir($directory, 0700)) {
            throw new \RuntimeException('Cannot create comparison directory.');
        }
        try {
            file_put_contents($directory.'/old', $old->text);
            file_put_contents($directory.'/new', $new->text);
            $context = $all ? max(count($old->lines), count($new->lines), 1) : 5;
            $process = new Process(['diff', '-U', (string) $context, ...($ignoreWhitespace ? ['-w'] : []), '--', $directory.'/old', $directory.'/new']);
            $process->setTimeout(10)->run();
            if (! in_array($process->getExitCode(), [0, 1], true)) {
                throw new \RuntimeException('Cannot compute comparison.');
            }
            if ($process->getExitCode() === 0) {
                return $all ? array_map(fn ($line, $index) => self::row($index + 1, $index + 1, $line, $new->lines[$index] ?? ''), $old->lines, array_keys($old->lines)) : [];
            }
            $rows = [];
            $deleted = $added = [];
            $flush = function () use (&$rows, &$deleted, &$added, $old, $new) {
                for ($i = 0; $i < max(count($deleted), count($added)); $i++) {
                    $left = $deleted[$i] ?? null;
                    $right = $added[$i] ?? null;
                    $rows[] = self::row($left, $right, $left ? $old->lines[$left - 1] : null, $right ? $new->lines[$right - 1] : null, 'change');
                }
                $deleted = $added = [];
            };
            $inHunk = false;
            foreach (explode("\n", $process->getOutput()) as $line) {
                if (preg_match('/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/', $line, $match)) {
                    $flush();
                    $left = (int) $match[1];
                    $right = (int) $match[3];
                    $rows[] = ['kind' => 'hunk', 'label' => $line];
                    $inHunk = true;
                } elseif ($inHunk && str_starts_with($line, '-')) {
                    $deleted[] = $left++;
                } elseif ($inHunk && str_starts_with($line, '+')) {
                    $added[] = $right++;
                } elseif ($inHunk && str_starts_with($line, ' ')) {
                    $flush();
                    $rows[] = self::row($left, $right, $old->lines[$left - 1], $new->lines[$right - 1]);
                    $left++;
                    $right++;
                }
            }
            $flush();

            return $rows;
        } finally {
            @unlink($directory.'/old');
            @unlink($directory.'/new');
            rmdir($directory);
        }
    }

    private static function row(?int $oldLine, ?int $newLine, ?string $oldText, ?string $newText, string $kind = 'context'): array
    {
        return compact('oldLine', 'newLine', 'oldText', 'newText', 'kind');
    }
}
