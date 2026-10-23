<?php

namespace Tests\Feature;

use App\Svn\Fixture;
use App\Svn\SourceDocument;
use App\Svn\SvnClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class DeploymentTest extends TestCase
{
    private static string $fixture;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$fixture = sys_get_temp_dir().'/larasvn-deployment-test-'.bin2hex(random_bytes(8));
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
        config(['svn.repositories' => ['fixture' => ['url' => 'file://'.self::$fixture.'/repository', 'downloads' => true]]]);
    }

    public function test_production_requires_explicit_public_access_and_hides_configuration_errors(): void
    {
        $this->app->instance('env', 'production');
        $this->get('/listing.php')->assertStatus(503)->assertSee('Public repository access has not been enabled')->assertDontSee('repname=fixture', false);
        config(['svn.public_read' => true]);
        $this->get('/listing.php')->assertOk();
        config(['svn.error' => 'PRIVATE-CONFIG-PATH']);
        $this->get('/')->assertStatus(503)->assertDontSee('PRIVATE-CONFIG-PATH');
        $this->get('/rss.php')->assertStatus(503);
        $this->get('/dl.php?path=/example.php')->assertStatus(503);
        $this->get('/browse.php/fixture')->assertStatus(503);
        $this->assertSame(1, Artisan::call('svn:check'));
        $this->assertStringContainsString('PRIVATE-CONFIG-PATH', Artisan::output());
    }

    public function test_anonymous_gets_are_stateless_and_errors_have_security_headers(): void
    {
        foreach (['/listing.php', '/filedetails.php', '/rss.php', '/dl.php?path=/example.php', '/filedetails.php?path=/missing'] as $url) {
            $response = $this->get($url)->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
            $this->assertFalse($response->headers->has('Set-Cookie'));
            $this->assertTrue($response->headers->has('Content-Security-Policy'));
        }
    }

    public function test_configured_byte_line_entry_and_archive_limits_are_enforced(): void
    {
        config(['svn.limits.display_bytes' => 100]);
        $this->get('/listing.php')->assertStatus(413);
        config(['svn.limits.display_bytes' => 2_000_000, 'svn.limits.source_lines' => 10]);
        $this->get('/filedetails.php')->assertStatus(413);
        $this->get('/filedetails.php?usemime=1')->assertOk();
        config(['svn.limits.download_entries' => 1]);
        $this->get('/dl.php?path=/docs&dlmode=zip')->assertStatus(413);
        config(['svn.limits.download_entries' => 1000, 'svn.limits.download_bytes' => 10]);
        $this->get('/dl.php?path=/example.php')->assertStatus(413);
        config(['svn.limits.download_bytes' => 20_000_000, 'svn.limits.archive_bytes' => 10]);
        $this->get('/dl.php?path=/docs&dlmode=zip')->assertStatus(413);
        config(['svn.limits.process_seconds' => 0]);
        $this->get('/listing.php')->assertStatus(503);
    }

    public function test_line_limit_is_checked_before_splitting_and_handles_final_newlines(): void
    {
        $this->assertSame(['one', 'two'], (new SourceDocument("one\ntwo\n", 2))->lines);
        $this->expectException(HttpException::class);
        new SourceDocument("one\ntwo\nthree", 2);
    }

    public function test_svn_client_shares_one_request_budget_and_metadata_cache(): void
    {
        config(['svn.limits.request_seconds' => 1]);
        $client = app(SvnClient::class);
        $this->assertSame($client, app(SvnClient::class));
        $client->head('fixture');
        usleep(1_010_000);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('exceeded its time limit');
        $client->info('fixture', '/');
    }

    public function test_preflight_checks_read_only_access_and_deployment_settings(): void
    {
        $this->assertSame(0, Artisan::call('svn:check', ['--repository' => ['fixture']]));
        $this->assertStringContainsString('root readable at r26', Artisan::output());
        $this->assertSame(1, Artisan::call('svn:check', ['--repository' => ['missing']]));
        $this->assertSame(1, Artisan::call('svn:check', ['--production' => true]));
        $this->app->instance('env', 'production');
        $this->assertSame(1, Artisan::call('svn:fixture'));
        config(['svn.public_read' => true, 'app.debug' => false, 'app.url' => 'https://example.test/websvn', 'app.key' => 'test-key']);
        $this->assertSame(0, Artisan::call('svn:check', ['--production' => true, '--repository' => ['fixture']]));
        config(['app.debug' => true]);
        $this->assertSame(1, Artisan::call('svn:check', ['--production' => true]));
    }

    public function test_dev_command_rejects_deployment_use_and_invalid_options_before_starting_children(): void
    {
        $this->assertSame(1, Artisan::call('dev'));
        $this->assertStringContainsString('requires APP_ENV=local', Artisan::output());
        $this->app->instance('env', 'local');
        $this->assertSame(1, Artisan::call('dev', ['--port' => 'invalid']));
        $this->assertSame(1, Artisan::call('dev', ['--host' => 'bad host']));
    }

    public function test_repository_endpoints_reject_mutating_http_methods(): void
    {
        foreach (['/listing.php', '/filedetails.php', '/comp.php', '/dl.php', '/rss.php'] as $url) {
            $this->post($url)->assertStatus(405);
        }
    }

    public function test_parent_discovery_drives_all_workflows_and_refreshes_between_requests(): void
    {
        $parent = self::$fixture.'/discovery';
        mkdir($parent);
        symlink(self::$fixture.'/repository', $parent.'/svn-hooks');
        mkdir($parent.'/not-a-repository');
        symlink(self::$fixture.'/repository', $parent.'/.hidden');
        config(['svn.repositories' => [], 'svn.parent_path' => $parent]);
        $this->get('/')->assertRedirect(route('listing', ['repname' => 'svn-hooks', 'path' => '/']));
        $this->get('/listing.php?repname=svn-hooks')->assertOk()->assertSee('repname=svn-hooks', false)->assertDontSee('Download file');
        $this->get('/filedetails.php?repname=svn-hooks&path=/example.php')->assertOk()->assertViewHas('repository', 'svn-hooks');
        $this->get('/rss.php?repname=svn-hooks&max=1')->assertOk();
        $this->get('/dl.php?repname=svn-hooks&path=/example.php')->assertForbidden();
        $response = $this->get('/browse.php/svn-hooks/example.php')->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk();
        symlink(self::$fixture.'/repository', $parent.'/123');
        $this->get('/listing.php?repname=123')->assertOk();
        $response = $this->get('/browse.php/123')->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk();
        unlink($parent.'/123');
        $this->get('/listing.php?repname=123')->assertNotFound();
        // Explicit per-repository entries override discovered defaults.
        config(['svn.repositories.svn-hooks' => ['url' => 'file://'.self::$fixture.'/repository', 'downloads' => true, 'rss' => false]]);
        $this->get('/rss.php?repname=svn-hooks')->assertForbidden();
        $this->get('/dl.php?repname=svn-hooks&path=/example.php')->assertOk();
        $this->assertSame(0, Artisan::call('svn:check', ['--repository' => ['svn-hooks']]));
    }

    public function test_invalid_parent_directory_returns_readable_503_without_fallback(): void
    {
        config(['svn.parent_path' => '/missing/larasvn-parent']);
        $this->get('/listing.php')->assertStatus(503)->assertSee('SVN_PARENT_PATH')->assertDontSee('repname=fixture', false);
        $this->assertSame(1, Artisan::call('svn:check'));
        $this->assertStringContainsString('SVN_PARENT_PATH', Artisan::output());
    }

    public function test_copied_production_mime_policy_preserves_untyped_source_and_respects_properties(): void
    {
        config(['svn.source_mime_types' => ['text/plain']]);
        $this->get('/filedetails.php?path=/example.php')->assertOk()->assertViewIs('code');
        $this->get('/filedetails.php?path=/trunk/src/renamed.php')->assertOk()
            ->assertHeader('Content-Type', 'text/x-php; charset=utf-8')->assertContent("<?php\necho 'Bonjour café';\n");
        $this->get('/diff.php?path=/example.php')->assertOk()->assertViewIs('code');
    }
}
