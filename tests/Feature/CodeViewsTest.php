<?php

namespace Tests\Feature;

use App\Svn\Fixture;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class CodeViewsTest extends TestCase
{
    private static string $fixture;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$fixture = sys_get_temp_dir().'/larasvn-test-'.bin2hex(random_bytes(8));
        Fixture::create(self::$fixture);
    }

    public static function tearDownAfterClass(): void
    {
        File::deleteDirectory(self::$fixture);
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['svn.repositories.fixture.url' => 'file://'.self::$fixture.'/repository']);
    }

    public function test_source_is_readable_before_javascript_and_escaped(): void
    {
        $this->get('/filedetails.php?repname=fixture&path=/example.php')
            ->assertOk()->assertSee('&lt;?php', false)->assertSee('café 😀')
            ->assertViewHas('revision', 20)->assertViewHas('peg', 26)
            ->assertViewHas('document', fn ($document) => count($document->lines) === 49);
    }

    public function test_diff_selects_previous_file_change_and_full_document_for_prism(): void
    {
        $this->get('/diff.php?repname=fixture&path=/example.php&rev=20')
            ->assertOk()->assertViewHas('previous', 10)
            ->assertViewHas('old', fn ($old) => str_contains($old->text, 'Old message'))
            ->assertViewHas('rows', fn ($rows) => count($rows) < 25)
            ->assertSee('New message, still inside the comment.');
        $this->get('/diff.php?path=/example.php&rev=20&all=1')->assertOk()
            ->assertViewHas('rows', fn ($rows) => count($rows) === 50);
    }

    public function test_blame_matches_all_source_lines_and_links_to_prior_revisions(): void
    {
        $this->get('/blame.php?path=/example.php&rev=20')->assertOk()
            ->assertViewHas('attribution', function ($lines) {
                return count($lines) === 49 && $lines[23]['revision'] === 20 && $lines[1]['revision'] === 10;
            })->assertSee('bob')->assertSee('alice')->assertSee('id="l23"', false);
    }

    public function test_revision_ten_is_available_and_has_no_previous_file_change(): void
    {
        $this->get('/diff.php?path=/example.php&rev=10&peg=20')->assertOk()
            ->assertSee('No previous change exists')->assertViewHas('revision', 10);
        $this->get('/filedetails.php?path=/example.php&rev=10&peg=20')->assertOk()
            ->assertSee('Old message, still inside the comment.');
    }

    public function test_unicode_at_sign_crlf_and_missing_final_newline(): void
    {
        $query = http_build_query(['path' => '/unicode @ sample.txt']);
        $this->get('/filedetails.php?'.$query)->assertOk()
            ->assertViewHas('revision', 10)
            ->assertViewHas('document', fn ($document) => $document->bytes === "naïve café 😀\r\n\r\n\tTabbed\r\nlast line" && count($document->lines) === 4 && ! $document->finalNewline);
        $this->get('/blame.php?'.$query)->assertOk()->assertViewHas('attribution', fn ($lines) => count($lines) === 4);
    }

    public function test_empty_source_and_blame(): void
    {
        $this->get('/filedetails.php?path=/empty.txt')->assertOk()->assertSee('This file is empty.');
        $this->get('/blame.php?path=/empty.txt')->assertOk()->assertViewHas('attribution', []);
    }

    public function test_invalid_inputs_and_missing_resources_have_honest_errors(): void
    {
        $this->get('/filedetails.php?rev[]=20')->assertStatus(400);
        $this->get('/filedetails.php?path=/../etc/passwd')->assertStatus(400);
        $this->get('/filedetails.php?repname=unknown')->assertNotFound();
        $this->get('/filedetails.php?path=/missing.php')->assertNotFound();
        $this->get('/filedetails.php?rev=9999')->assertNotFound();
    }
}
