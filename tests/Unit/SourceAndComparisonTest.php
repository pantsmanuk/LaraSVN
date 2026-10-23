<?php

namespace Tests\Unit;

use App\Svn\Comparison;
use App\Svn\SourceDocument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SourceAndComparisonTest extends TestCase
{
    public function test_line_boundaries_preserve_blank_lines_and_final_newline(): void
    {
        $document = new SourceDocument("first\r\n\r\n\tlast\r\n");
        self::assertSame(['first', '', "\tlast"], $document->lines);
        self::assertSame("first\n\n\tlast\n", $document->text);
        self::assertTrue($document->finalNewline);
        self::assertSame([], (new SourceDocument(''))->lines);
        self::assertSame([''], (new SourceDocument("\n"))->lines);
    }

    public function test_binary_input_is_explicitly_rejected(): void
    {
        $this->expectException(HttpException::class);
        new SourceDocument("\0binary");
    }

    public function test_invalid_utf8_is_explicitly_rejected(): void
    {
        $this->expectException(HttpException::class);
        new SourceDocument("\xff");
    }

    public function test_diff_handles_an_empty_side_and_insertions_before_first_line(): void
    {
        $rows = Comparison::rows(new SourceDocument(''), new SourceDocument("new\n"), false, false);
        self::assertSame(1, $rows[1]['newLine']);
        self::assertNull($rows[1]['oldLine']);
        $rows = Comparison::rows(new SourceDocument("old\n"), new SourceDocument("new\nold\n"), true, false);
        self::assertSame('new', $rows[1]['newText']);
        self::assertSame('old', $rows[2]['oldText']);
    }

    public function test_whitespace_toggle_changes_equality_without_rewriting_source(): void
    {
        $old = new SourceDocument("  text\n");
        $new = new SourceDocument("\ttext\n");
        self::assertSame([], Comparison::rows($old, $new, false, true));
        $rows = Comparison::rows($old, $new, true, true);
        self::assertSame('  text', $rows[0]['oldText']);
        self::assertSame("\ttext", $rows[0]['newText']);
        self::assertSame('change', Comparison::rows($old, $new, false, false)[1]['kind']);
    }
}
