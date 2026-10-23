<?php

namespace App\Svn;

use Symfony\Component\Process\Process;

final class Fixture
{
    public static function create(string $directory, bool $phaseThree = false): void
    {
        if (file_exists($directory)) {
            throw new \RuntimeException('Fixture destination already exists.');
        }
        mkdir($directory, 0700, true);
        $run = static function (array $arguments) use ($directory) {
            (new Process($arguments, $directory, ['LC_ALL' => 'C.UTF-8']))->setTimeout(20)->mustRun();
        };
        $run(['svnadmin', 'create', $directory.'/repository']);
        $url = 'file://'.$directory.'/repository';
        $run(['svn', 'checkout', '--non-interactive', $url, 'working']);
        $working = $directory.'/working';
        file_put_contents($working.'/noise.txt', "Initial fixture\n");
        $run(['svn', 'add', $working.'/noise.txt']);
        $run(['svn', 'commit', '--non-interactive', '--username', 'fixture', '-m', 'Create fixture', $working]);
        for ($revision = 2; $revision <= 20; $revision++) {
            if ($revision === 10) {
                file_put_contents($working.'/example.php', self::source(false));
                file_put_contents($working.'/empty.txt', '');
                file_put_contents($working.'/unicode @ sample.txt', "naïve café 😀\r\n\r\n\tTabbed\r\nlast line");
                $run(['svn', 'add', $working.'/example.php', $working.'/empty.txt', $working.'/unicode @ sample.txt@']);
            } elseif ($revision === 20) {
                file_put_contents($working.'/example.php', self::source(true));
            } else {
                file_put_contents($working.'/noise.txt', 'Unrelated commit '.$revision."\n");
            }
            $run(['svn', 'commit', '--non-interactive', '--username', $revision === 20 ? 'bob' : 'alice', '-m', $revision === 20 ? 'Improve message inside multiline comment' : 'Fixture revision '.$revision, $working]);
        }
        mkdir($working.'/trunk/src', 0700, true);
        mkdir($working.'/docs');
        mkdir($working.'/empty-dir');
        file_put_contents($working.'/trunk/src/greeting.php', "<?php\necho 'Hello';\n");
        file_put_contents($working.'/docs/README.md', "# Fixture documentation\n");
        file_put_contents($working.'/docs/<notes>.txt', "Markup in a filename is text.\n");
        $run(['svn', 'add', $working.'/trunk', $working.'/docs', $working.'/empty-dir']);
        $run(['svn', 'propset', 'custom:note', '<script>untrusted property</script>', $working.'/trunk/src/greeting.php']);
        $run(['svn', 'propset', 'svn:mime-type', 'text/x-php', $working.'/trunk/src/greeting.php']);
        $run(['svn', 'commit', '--username', 'alice', '-m', 'Add nested source and documentation', $working]);
        file_put_contents($working.'/trunk/src/greeting.php', "<?php\necho 'Bonjour café';\n");
        $run(['svn', 'commit', '--username', 'bob', '-m', 'Improve greeting café', $working]);
        $run(['svn', 'copy', $working.'/trunk/src/greeting.php', $working.'/trunk/src/copied.php']);
        $run(['svn', 'commit', '--username', 'alice', '-m', 'Copy greeting', $working]);
        $run(['svn', 'move', $working.'/trunk/src/greeting.php', $working.'/trunk/src/renamed.php']);
        $run(['svn', 'commit', '--username', 'alice', '-m', 'Rename greeting', $working]);
        $run(['svn', 'delete', $working.'/trunk/src/copied.php']);
        $run(['svn', 'commit', '--username', 'bob', '-m', 'Remove copied greeting', $working]);
        $run(['svn', 'propset', 'custom:status', 'Reviewed', $working.'/docs']);
        $run(['svn', 'commit', '--username', 'alice', '-m', 'Review documentation properties', $working]);
        if ($phaseThree) {
            self::extendPhaseThree($directory);
        }

    }

    private static function extendPhaseThree(string $directory): void
    {
        $working = $directory.'/working';
        $run = static fn (array $arguments) => (new Process($arguments, $directory, ['LC_ALL' => 'C.UTF-8']))->setTimeout(20)->mustRun();
        mkdir($working.'/formats');
        mkdir($working.'/special');
        file_put_contents($working.'/formats/binary.bin', "binary\0\xff\r\n");
        file_put_contents($working.'/formats/page.html', '<script>document.title="Repository script"</script>');
        file_put_contents($working.'/formats/image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        file_put_contents($working.'/formats/space before.txt ', "Exact trailing-space filename\r\n");
        file_put_contents($working.'/formats/pre-commit', "#!/bin/sh\necho Extensionless hook\n");
        file_put_contents($working.'/formats/hook.sh', "#!/bin/sh\necho Hook\n");
        file_put_contents($working.'/formats/left.txt', "one two\nlast\n");
        file_put_contents($working.'/formats/right.txt', "one   two\nlast\n");
        symlink('../../../../etc/passwd', $working.'/special/link');
        $run(['svn', 'add', $working.'/formats', $working.'/special']);
        $run(['svn', 'propset', 'svn:mime-type', 'application/octet-stream', $working.'/formats/binary.bin']);
        $run(['svn', 'propset', 'svn:executable', '*', $working.'/formats/hook.sh']);
        $run(['svn', 'propset', 'svn:externals', '^/missing ignored-external', $working.'/docs']);
        $run(['svn', 'commit', '--username', 'fixture', '-m', 'Add binary, active content & download safety <fixtures>', $working]);
    }

    private static function source(bool $changed): string
    {
        $lines = ['<?php', '', '/*', ' * A multiline comment: café 😀'];
        for ($i = 1; $i <= 18; $i++) {
            $lines[] = ' * Context line '.$i;
        }
        $lines[] = $changed ? ' * New message, still inside the comment.' : ' * Old message, still inside the comment.';
        for ($i = 19; $i <= 36; $i++) {
            $lines[] = ' * Context line '.$i;
        }

        return implode("\n", [...$lines, ' */', '', '$message = <<<TEXT', 'Unicode: naïve café 😀', '', 'TEXT;', '', 'echo $message;', '']);
    }
}
