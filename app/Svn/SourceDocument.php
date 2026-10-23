<?php

namespace App\Svn;

use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class SourceDocument
{
    public string $text;

    public array $lines;

    public bool $finalNewline;

    public function __construct(public string $bytes, int $maxLines = 20_000)
    {
        if (str_contains($bytes, "\0")) {
            throw new HttpException(415, 'Binary content cannot be displayed as source.');
        }
        if (! mb_check_encoding($bytes, 'UTF-8')) {
            throw new HttpException(415, 'Only UTF-8 source can be displayed. Use a raw response or download for other encodings.');
        }
        $this->text = str_replace(["\r\n", "\r"], "\n", $bytes);
        $this->finalNewline = str_ends_with($this->text, "\n");
        $lineCount = $this->text === '' ? 0 : substr_count($this->text, "\n") + ($this->finalNewline ? 0 : 1);
        if ($lineCount > $maxLines) {
            throw new HttpException(413, 'Source exceeds the configured line limit. Use a raw response or download.');
        }
        $lines = $this->text === '' ? [] : explode("\n", $this->text);
        if ($this->finalNewline) {
            array_pop($lines);
        }
        $this->lines = $lines;
    }
}
