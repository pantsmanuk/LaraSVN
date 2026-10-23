<?php

namespace Tests\Feature;

use App\Svn\Fixture;
use App\Svn\MimeType;
use App\Svn\SvnClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use PharData;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

final class CompatibilityEndpointsTest extends TestCase
{
    private static string $fixture;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$fixture = sys_get_temp_dir().'/larasvn-compat-test-'.bin2hex(random_bytes(8));
        Fixture::create(self::$fixture, true);
    }

    public static function tearDownAfterClass(): void
    {
        File::deleteDirectory(self::$fixture);
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['svn.repositories' => ['fixture' => ['url' => 'file://'.self::$fixture.'/repository', 'downloads' => true]]]);
    }

    private function compare(array $query): TestResponse
    {
        return $this->get('/comp.php?'.http_build_query(['repname' => 'fixture', ...$query]));
    }

    public function test_arbitrary_comparison_form_files_embedded_revisions_and_ordering(): void
    {
        $this->get('/comp.php')->assertOk()->assertSee('Compare snapshots');
        $query = ['compare' => ['/example.php@20', '/example.php@10'], 'compare_rev' => [1, 2]];
        $this->compare($query)->assertOk()->assertSee('- * Old message', false)->assertSee('+ * New message', false)
            ->assertViewHas('sides', [['path' => '/example.php', 'revision' => 10], ['path' => '/example.php', 'revision' => 20]]);
        $this->compare($query + ['manualorder' => 1])->assertOk()->assertSee('- * New message', false)->assertSee('+ * Old message', false);
        $this->compare(['compare' => ['/example.php', '/example.php'], 'compare_rev' => [-1, 0]])->assertOk()->assertViewHas('patch', '');
        $this->compare(['compare' => [1 => '/example.php@20', 0 => '/example.php@10'], 'manualorder' => 1])
            ->assertOk()->assertViewHas('sides', [['path' => '/example.php', 'revision' => 10], ['path' => '/example.php', 'revision' => 20]]);
    }

    public function test_directory_property_copy_rename_and_binary_comparisons(): void
    {
        $this->compare(['compare' => ['/docs@25', '/docs@26']])->assertOk()->assertSee('Property changes on:')->assertSee('custom:status')->assertSee('Reviewed');
        $this->compare(['compare' => ['/trunk/src@22', '/trunk/src@25']])->assertOk()->assertSee('greeting.php')->assertSee('renamed.php');
        $this->compare(['compare' => ['/trunk/src/greeting.php@22', '/trunk/src/copied.php@23']])->assertOk()->assertViewHas('patch', '');
        $this->compare(['compare' => ['/empty.txt@27', '/formats/binary.bin@27']])->assertOk()->assertSee('Cannot display: file marked as a binary type.');
    }

    public function test_whitespace_option_and_at_sign_and_space_paths(): void
    {
        $query = ['compare' => ['/formats/left.txt', '/formats/right.txt']];
        $this->compare($query)->assertOk()->assertSee('-one two', false);
        $this->compare($query + ['ignorews' => 1])->assertOk()->assertViewHas('patch', '');
        $this->compare(['compare' => ['/unicode @ sample.txt@10', '/unicode @ sample.txt@27']])->assertOk()->assertViewHas('patch', '');
        $this->compare(['compare' => ['/formats/space before.txt ', '/formats/space before.txt ']])->assertOk()->assertViewHas('patch', '');
    }

    public function test_comparison_validation_and_missing_snapshots(): void
    {
        foreach ([['compare' => ['one']], ['compare' => ['../etc/passwd', '/']], ['compare' => [['/'], '/']], ['compare' => ['/x@9999999999999', '/']], ['compare_rev' => ['HEAD', 0]]] as $query) {
            $this->compare($query)->assertStatus(400);
        }
        $this->compare(['compare' => ['/missing@20', '/example.php@20']])->assertNotFound();
        $this->compare(['compare' => ['/example.php@9999', '/example.php@20']])->assertNotFound();
    }

    public function test_rss_xml_limits_quiet_escaping_and_working_links(): void
    {
        $response = $this->get('/rss.php?repname=fixture&path=/&max=3')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertCount(3, $xml->channel->item);
        $this->assertStringContainsString('<fixtures>', (string) $xml->channel->item[0]->title);
        $this->assertStringContainsString('<ul>', (string) $xml->channel->item[0]->description);
        $this->assertStringNotContainsString('<fixtures>', (string) $xml->channel->item[0]->description);
        $this->assertSame('fixture', (string) $xml->channel->item[0]->children('http://purl.org/dc/elements/1.1/')->creator);
        $this->get((string) $xml->channel->item[0]->link)->assertOk()->assertViewHas('revision', 27);
        $this->get((string) $xml->channel->children('http://www.w3.org/2005/Atom')->link->attributes()['href'])->assertOk();
        $quiet = simplexml_load_string($this->get('/rss.php?max=1&quiet=0')->assertOk()->getContent());
        $this->assertStringNotContainsString('<ul>', (string) $quiet->channel->item[0]->description);
        $default = simplexml_load_string($this->get('/rss.php?max=0')->assertOk()->getContent());
        $this->assertCount(27, $default->channel->item);
        $historical = simplexml_load_string($this->get('/rss.php?path=/trunk/src/renamed.php&rev=22&peg=27')->assertOk()->getContent());
        $this->assertStringStartsWith('r22 ', (string) $historical->channel->item[0]->title);
        $this->get('/rss.php?max[]=2')->assertStatus(400);
        $this->get('/rss.php?path=/missing')->assertNotFound();
        config(['svn.repositories.fixture.rss' => false]);
        $this->get('/rss.php')->assertForbidden();
    }

    public function test_raw_mime_responses_preserve_bytes_and_sandbox_active_content(): void
    {
        $this->get('/filedetails.php?path=/formats/binary.bin')->assertOk()->assertHeader('Content-Type', 'application/octet-stream')
            ->assertContent("binary\0\xff\r\n");
        $this->get('/filedetails.php?'.http_build_query(['path' => '/unicode @ sample.txt', 'usemime' => 1]))
            ->assertOk()->assertContent("naïve café 😀\r\n\r\n\tTabbed\r\nlast line");
        $this->get('/filedetails.php?path=/formats/page.html')->assertOk()->assertViewIs('code');
        $this->get('/filedetails.php?path=/formats/pre-commit')->assertOk()->assertViewIs('code')->assertSee('Extensionless hook');
        $this->get('/filedetails.php?path=/formats/page.html&usemime=1')->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; base-uri 'none'; frame-ancestors 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertContent('<script>document.title="Repository script"</script>');
        $this->get('/filedetails.php?path=/formats/image.svg&usemime=1')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->get('/filedetails.php?path=/trunk/src/renamed.php&rev=22&peg=27&usemime=1')->assertOk()->assertContent("<?php\necho 'Bonjour café';\n");
        $this->get('/filedetails.php?path=/empty.txt&usemime=1')->assertOk()->assertContent('');
        $this->get('/filedetails.php?usemime[]=1')->assertStatus(400);
    }

    public function test_plain_download_defaults_disabled_policy_and_historical_bytes(): void
    {
        $this->get('/dl.php?path=/formats/binary.bin')->assertOk()->assertContent("binary\0\xff\r\n")
            ->assertHeader('Content-Disposition', 'attachment; filename=binary.bin');
        $this->get('/dl.php?path=/example.php&rev=10&dlmode=invalid')->assertOk()->assertSee('Old message', false);
        $this->get('/dl.php?path=/trunk/src/renamed.php&rev=22&peg=27')->assertOk()->assertContent("<?php\necho 'Bonjour café';\n");
        $this->get('/dl.php?'.http_build_query(['path' => '/unicode @ sample.txt']))->assertOk()
            ->assertContent("naïve café 😀\r\n\r\n\tTabbed\r\nlast line");
        $this->get('/dl.php?path=/missing')->assertNotFound();
        $this->get('/dl.php?dlmode[]=zip')->assertStatus(400);
        config(['svn.repositories.fixture.downloads' => false]);
        $this->get('/dl.php?path=/example.php')->assertForbidden();
        config(['svn.repositories.fixture' => ['url' => 'file://'.self::$fixture.'/repository']]);
        $this->get('/dl.php?path=/example.php')->assertForbidden();
    }

    public function test_zip_contains_exact_files_empty_directories_and_no_externals(): void
    {
        $before = glob(sys_get_temp_dir().'/larasvn-download-*');
        $response = $this->get('/dl.php?path=/docs&dlmode=zip&rev=27')->assertOk()->assertHeader('Content-Type', 'application/zip')
            ->assertHeader('Content-Disposition', 'attachment; filename=fixture-docs.r27.zip');
        $archivePath = self::$fixture.'/inspection.zip';
        file_put_contents($archivePath, $response->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivePath));
        $this->assertSame("# Fixture documentation\n", $zip->getFromName('docs.r27/README.md'));
        $this->assertSame("Markup in a filename is text.\n", $zip->getFromName('docs.r27/<notes>.txt'));
        $this->assertFalse($zip->locateName('docs.r27/ignored-external'));
        $zip->close();
        $empty = $this->get('/dl.php?path=/empty-dir&dlmode=zip')->assertOk();
        file_put_contents($archivePath, $empty->getContent());
        $zip->open($archivePath);
        $this->assertSame(0, $zip->locateName('empty-dir.r21/'));
        $zip->close();
        $file = $this->get('/dl.php?path=/formats/binary.bin&dlmode=zip')->assertOk();
        file_put_contents($archivePath, $file->getContent());
        $zip->open($archivePath);
        $this->assertSame("binary\0\xff\r\n", $zip->getFromName('binary.bin.r27'));
        $zip->close();
        $this->assertSame($before, glob(sys_get_temp_dir().'/larasvn-download-*'));
    }

    public function test_gzip_mode_is_a_tar_archive_for_directories_and_single_files(): void
    {
        foreach (['/docs' => ['docs.r27/README.md', "# Fixture documentation\n"], '/formats/binary.bin' => ['binary.bin.r27', "binary\0\xff\r\n"], '/empty-dir' => ['empty-dir.r21', null]] as $path => [$entry, $expected]) {
            $response = $this->get('/dl.php?'.http_build_query(['path' => $path, 'dlmode' => 'gzip']))->assertOk()->assertHeader('Content-Type', 'application/gzip');
            $archivePath = self::$fixture.'/inspection-'.bin2hex(random_bytes(4)).'.tar';
            file_put_contents($archivePath, gzdecode($response->getContent()));
            $tar = new PharData($archivePath);
            $this->assertTrue(isset($tar[$entry]));
            if ($expected !== null) {
                $this->assertSame($expected, $tar[$entry]->getContent());
            }
        }
        $this->get('/dl.php?path=/docs&dlmode=plain')->assertOk()->assertHeader('Content-Type', 'application/gzip');
    }

    public function test_special_nodes_are_rejected_and_no_temporary_files_are_left(): void
    {
        $before = glob(sys_get_temp_dir().'/larasvn-download-*');
        $this->get('/dl.php?path=/special/link')->assertStatus(415);
        $this->get('/dl.php?path=/special&dlmode=zip')->assertStatus(415);
        $this->get('/dl.php?path=/&dlmode=gzip')->assertStatus(415);
        $this->assertSame($before, glob(sys_get_temp_dir().'/larasvn-download-*'));
    }

    public function test_download_size_entry_limits_and_cleanup_after_archive_creation(): void
    {
        $root = self::$fixture.'/limits';
        mkdir($root);
        (new Process(['svnadmin', 'create', $root.'/repository']))->mustRun();
        mkdir($root.'/import');
        file_put_contents($root.'/import/large.bin', str_repeat('x', config('svn.limits.download_bytes') + 1));
        mkdir($root.'/import/many');
        for ($index = 0; $index <= config('svn.limits.download_entries'); $index++) {
            mkdir($root.'/import/many/entry-'.$index);
        }
        (new Process(['svn', 'import', '--non-interactive', '-m', 'Limit tests', $root.'/import', 'file://'.$root.'/repository']))->setTimeout(20)->mustRun();
        config(['svn.repositories.fixture.url' => 'file://'.$root.'/repository']);
        $before = glob(sys_get_temp_dir().'/larasvn-download-*');
        $this->get('/dl.php?path=/large.bin&dlmode=zip')->assertStatus(413);
        $this->assertSame($before, glob(sys_get_temp_dir().'/larasvn-download-*'));
        $this->get('/dl.php?path=/large.bin&dlmode=gzip')->assertStatus(413);
        $this->assertSame($before, glob(sys_get_temp_dir().'/larasvn-download-*'));
        $this->get('/dl.php?path=/large.bin')->assertStatus(413);
        $this->get('/dl.php?path=/many&dlmode=zip')->assertStatus(413);
        $this->assertSame($before, glob(sys_get_temp_dir().'/larasvn-download-*'));
    }

    public function test_transport_enforces_output_limit_while_reading(): void
    {
        $svn = app(SvnClient::class);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('exceeds the local size limit');
        $svn->run('fixture', ['cat', '--', $svn->target('fixture', '/example.php', 27)], 64);
    }

    public function test_multiviews_dispatches_operations_and_pins_auto_detection(): void
    {
        foreach (['browse', 'browse.php', 'multiviews.php'] as $alias) {
            $this->get('/'.$alias)->assertRedirect(route('repositories'));
            $response = $this->get('/'.$alias.'/fixture/docs?path=/wrong&repname=unknown')->assertRedirect();
            $this->get($response->headers->get('Location'))->assertOk()->assertViewIs('listing');
            $response = $this->get('/'.$alias.'/fixture/example.php');
            $this->get($response->headers->get('Location'))->assertOk()->assertViewIs('code');
        }
        $mapping = ['dir' => 'listing.php', 'file' => 'filedetails.php', 'revision' => 'revision.php', 'log' => 'log.php', 'diff' => 'diff.php', 'blame' => 'blame.php', 'rss' => 'rss.php', 'dl' => 'dl.php', 'comp' => 'comp.php', 'search' => 'search.php'];
        foreach ($mapping as $op => $endpoint) {
            $response = $this->get('/browse.php/fixture/docs?op='.$op.'&rev=26')->assertRedirect();
            $this->assertStringContainsString('/'.$endpoint.'?', $response->headers->get('Location'));
            $this->assertStringContainsString('rev=26', $response->headers->get('Location'));
        }
        $this->get('/browse.php?op=rep&repname=fixture')->assertRedirect(route('listing', ['repname' => 'fixture', 'path' => '/']));
        $this->get('/browse.php/unknown/docs')->assertNotFound();
        $this->get('/browse.php/fixture/docs?op[]=dir')->assertStatus(400);
        $response = $this->get('/browse.php/fixture/unicode%20%40%20sample.txt');
        $this->get($response->headers->get('Location'))->assertOk()->assertViewIs('code');
    }

    public function test_alias_repository_names_use_longest_prefix_without_double_decoding(): void
    {
        $url = 'file://'.self::$fixture.'/repository';
        config(['svn.repositories' => ['group' => ['url' => $url], 'group/repo.v1' => ['url' => $url]]]);
        $response = $this->get('/browse.php/group/repo.v1/docs?op=dir')->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()->assertViewHas('repository', 'group/repo.v1');
        $response = $this->get('/browse.php/group/repo.v1/%252e%252e?op=dir')->assertRedirect();
        $this->assertStringContainsString('path=%2F%252e%252e', $response->headers->get('Location'));
        $this->get($response->headers->get('Location'))->assertNotFound();
    }

    public function test_archives_preserve_executable_hooks(): void
    {
        $response = $this->get('/dl.php?path=/formats/hook.sh&dlmode=zip')->assertOk();
        $archivePath = self::$fixture.'/hook.zip';
        file_put_contents($archivePath, $response->getContent());
        $zip = new ZipArchive;
        $zip->open($archivePath);
        $zip->getExternalAttributesName('hook.sh.r27', $system, $attributes);
        $this->assertSame(0755, ($attributes >> 16) & 0777);
        $this->assertSame("#!/bin/sh\necho Hook\n", $zip->getFromName('hook.sh.r27'));
        $zip->close();
        $response = $this->get('/dl.php?path=/formats/hook.sh&dlmode=gzip')->assertOk();
        $archivePath = self::$fixture.'/hook.tar';
        file_put_contents($archivePath, gzdecode($response->getContent()));
        $tar = new PharData($archivePath);
        $this->assertSame(0755, $tar['hook.sh.r27']->getPerms() & 0777);
    }

    public function test_homepage_respects_a_websvn_mount(): void
    {
        $server = ['SCRIPT_NAME' => '/websvn/index.php', 'PHP_SELF' => '/websvn/index.php', 'SCRIPT_FILENAME' => public_path('index.php')];
        foreach (['GET', 'HEAD', 'POST'] as $method) {
            // The test client's call() trims the trailing slash that Apache preserves.
            $request = Request::create('http://localhost/websvn/', $method, [], [], [], $server);
            $response = TestResponse::fromBaseResponse($this->app->handle($request));
            if ($method === 'POST') {
                $response->assertStatus(405);
            } else {
                $response->assertRedirect('http://localhost/websvn/listing.php?repname=fixture&path=%2F');
            }
        }
    }

    public function test_generated_links_and_alias_redirects_respect_a_websvn_mount(): void
    {
        $server = ['SCRIPT_NAME' => '/websvn/index.php', 'PHP_SELF' => '/websvn/index.php', 'SCRIPT_FILENAME' => public_path('index.php')];
        $response = $this->call('GET', 'http://localhost/websvn/listing.php?path=/docs', [], [], [], $server)->assertOk();
        $response->assertSee('http://localhost/websvn/rss.php?', false)
            ->assertSee('http://localhost/websvn/build/app.css', false);
        $response = $this->call('GET', 'http://localhost/websvn/browse.php/fixture/docs?op=log', [], [], [], $server)->assertRedirect();
        $this->assertStringStartsWith('http://localhost/websvn/log.php?', $response->headers->get('Location'));
        $response = $this->call('GET', 'http://localhost/websvn/rss.php?max=1', [], [], [], $server)->assertOk();
        $xml = simplexml_load_string($response->getContent());
        $this->assertStringStartsWith('http://localhost/websvn/revision.php?', (string) $xml->channel->item[0]->link);
    }

    public function test_repository_mime_properties_cannot_inject_response_headers(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Invalid repository MIME type.');
        MimeType::forFile('/test.txt', ['svn:mime-type' => "text/plain\r\nX-Injected: true"]);
    }

    public function test_simultaneous_downloads_are_isolated_and_cleaned_up(): void
    {
        $code = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
config(['svn.repositories' => ['fixture' => ['url' => 'file://'.$argv[1].'/repository', 'downloads' => true]]]);
$request = Illuminate\Http\Request::create('http://localhost/dl.php?path=/docs&dlmode=zip&rev=27');
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode(), 'body' => base64_encode($response->getContent())]);
$kernel->terminate($request, $response);
CODE;
        $before = glob(sys_get_temp_dir().'/larasvn-download-*');
        $processes = [new Process(['php', '-r', $code, self::$fixture], base_path()), new Process(['php', '-r', $code, self::$fixture], base_path())];
        foreach ($processes as $process) {
            $process->start();
        }
        foreach ($processes as $index => $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(200, $result['status']);
            $archivePath = self::$fixture.'/concurrent-'.$index.'.zip';
            file_put_contents($archivePath, base64_decode($result['body']));
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($archivePath));
            $this->assertSame("# Fixture documentation\n", $zip->getFromName('docs.r27/README.md'));
            $zip->close();
        }
        $this->assertSame($before, glob(sys_get_temp_dir().'/larasvn-download-*'));
    }
}
