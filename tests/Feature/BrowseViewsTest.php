<?php

namespace Tests\Feature;

use App\Svn\Fixture;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class BrowseViewsTest extends TestCase
{
    private static string $fixture;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$fixture = sys_get_temp_dir().'/larasvn-browse-test-'.bin2hex(random_bytes(8));
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

    public function test_single_repository_index_redirects_to_root_and_empty_configuration_is_readable(): void
    {
        $this->get('/')->assertRedirect(route('listing', ['repname' => 'fixture', 'path' => '/']));
        $this->get('/index.php')->assertRedirect(route('listing', ['repname' => 'fixture', 'path' => '/']));
        config(['svn.repositories' => []]);
        $this->get('/')->assertOk()->assertSee('No repositories are configured.');
    }

    public function test_multiple_grouped_repositories_and_dotted_names(): void
    {
        config(['svn.repositories' => [
            'fixture' => ['url' => 'file://'.self::$fixture.'/repository', 'group' => 'Examples'],
            'team.tools' => ['url' => 'file://'.self::$fixture.'/repository', 'group' => 'Tools'],
        ]]);
        $this->get('/index.php')->assertOk()->assertSee('Examples')->assertSee('Tools')->assertSee('team.tools');
        $this->get('/listing.php?repname=team.tools')->assertOk()->assertSee('example.php');
    }

    public function test_directories_are_sorted_first_and_empty_directories_work(): void
    {
        $this->get('/listing.php')->assertOk()->assertViewHas('revision', 26)
            ->assertViewHas('entries', fn ($entries) => array_column(array_slice($entries->items(), 0, 3), 'name') === ['docs', 'empty-dir', 'trunk']);
        $this->get('/listing.php?path=/empty-dir')->assertOk()->assertSee('This directory is empty.')->assertSee('Parent directory');
    }

    public function test_historical_directory_children_are_linked_to_the_selected_snapshot(): void
    {
        $response = $this->get('/listing.php?path=/trunk/src&rev=22');
        $response->assertOk()->assertSee('greeting.php')->assertDontSee('renamed.php');
        $html = $response->getContent();
        $this->assertStringContainsString('path=%2Ftrunk&amp;rev=22&amp;peg=22', $html);
        foreach ($this->links($html, '/filedetails.php') as $link) {
            $this->get($link)->assertOk()->assertSee('Bonjour café');
        }
        $this->get('/listing.php?path=/example.php&rev=10')->assertRedirect(route('source', ['repname' => 'fixture', 'path' => '/example.php', 'rev' => 10, 'peg' => 26]));
    }

    public function test_properties_are_rendered_as_text_on_files_and_directories(): void
    {
        $this->get('/filedetails.php?path=/trunk/src/renamed.php')->assertOk()
            ->assertSee('custom:note')->assertSee('&lt;script&gt;untrusted property&lt;/script&gt;', false)
            ->assertDontSee('<script>untrusted property</script>', false)->assertSee('text/x-php');
        $this->get('/listing.php?path=/docs')->assertOk()->assertSee('custom:status')->assertSee('Reviewed');
        $this->get('/listing.php?path=/docs')->assertSee('&lt;notes&gt;.txt', false)->assertDontSee('<notes>.txt', false);
    }

    public function test_history_paginates_and_clamps_out_of_range_pages(): void
    {
        $this->get('/log.php')->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 26 && count($entries) === 20 && $entries->currentPage() === 1)
            ->assertSee('Page 1 of 2')->assertSee('page=2');
        $this->get('/log.php?page=2')->assertOk()->assertViewHas('entries', fn ($entries) => count($entries) === 6 && $entries->currentPage() === 2);
        $this->get('/log.php?page=999')->assertOk()->assertViewHas('entries', fn ($entries) => $entries->currentPage() === 2);
    }

    public function test_history_search_folds_accents_and_matches_words_across_author_and_message(): void
    {
        $this->get('/log.php?search=BOB%20CAFE')->assertOk()->assertViewHas('entries', fn ($entries) => array_column($entries->items(), 'revision') === [22])
            ->assertSee('Improve greeting café')->assertSee('Clear history search');
        $this->get('/log.php?search=not-a-real-message')->assertOk()->assertSee('No changes match');
    }

    public function test_history_ranges_limits_and_changed_paths(): void
    {
        $this->get('/log.php?sr=24&er=22&showchanges=1')->assertOk()
            ->assertViewHas('entries', fn ($entries) => array_column($entries->items(), 'revision') === [24, 23, 22])
            ->assertSee('/trunk/src/renamed.php')->assertSee('Copied from');
        $this->get('/log.php?sr=22&er=24')->assertOk()->assertViewHas('entries', fn ($entries) => array_column($entries->items(), 'revision') === [22, 23, 24]);
        $this->get('/log.php?max=3')->assertOk()->assertSee('Showing the first 3 changes')->assertViewHas('entries', fn ($entries) => $entries->total() === 3);
        $this->get('/log.php?all=1&max=0')->assertOk()->assertViewHas('entries', fn ($entries) => count($entries) === 26);
        $this->get('/log.php?sr=HEAD&er=25')->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 2);
        $this->get('/log.php?fr=23')->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 22);
    }

    public function test_renamed_files_keep_their_peg_when_reading_and_following_history(): void
    {
        $this->get('/filedetails.php?path=/trunk/src/renamed.php&rev=21')->assertOk()->assertSee("echo 'Hello';")->assertViewHas('peg', 26);
        $this->get('/blame.php?path=/trunk/src/renamed.php&rev=22')->assertOk()->assertSee('bob');
        $this->get('/log.php?path=/trunk/src/renamed.php')->assertOk()
            ->assertViewHas('entries', fn ($entries) => array_column($entries->items(), 'revision') === [24, 22, 21]);
        $this->get('/diff.php?path=/trunk/src/renamed.php&rev=22')->assertOk()->assertViewHas('previous', 21)->assertSee('Bonjour café');
    }

    public function test_revision_copy_and_deletion_links_resolve_to_real_source(): void
    {
        foreach ([23, 24, 25] as $revision) {
            $response = $this->get('/revision.php?rev='.$revision)->assertOk();
            foreach ($this->links($response->getContent(), '/filedetails.php') as $link) {
                $this->get($link)->assertOk();
            }
        }
        $this->get('/revision.php?rev=23')->assertSee('Copied from')->assertSee('/trunk/src/greeting.php@22');
        $this->get('/revision.php?rev=25&path=/trunk/src/copied.php')->assertOk()->assertSee('before deletion');
        $this->get('/filedetails.php?path=/trunk/src/copied.php&rev=25&peg=25')->assertNotFound();
        $this->get('/revision.php?rev=26')->assertOk()->assertSee('Properties changed')->assertSee('Review documentation properties');
        $this->get('/revision.php?path=/example.php')->assertOk()->assertViewHas('revision', 20);
    }

    public function test_filename_search_is_recursive_revision_aware_and_handles_no_results(): void
    {
        $this->get('/search.php?search=*.php')->assertOk()->assertViewHas('entries', fn ($entries) => array_column($entries->items(), 'path') === ['/example.php', '/trunk/src/renamed.php']);
        $response = $this->get('/search.php?search=greet*&rev=22')->assertOk()->assertSee('trunk/src/greeting.php');
        foreach ($this->links($response->getContent(), '/filedetails.php') as $link) {
            $this->get($link)->assertOk()->assertSee('Bonjour café');
        }
        $this->get('/search.php?search=does-not-exist*')->assertOk()->assertSee('No filenames match');
        $this->get('/search.php')->assertOk()->assertSee('Enter a filename or pattern');
        $response = $this->get('/search.php?search=*sample*')->assertOk();
        foreach ($this->links($response->getContent(), '/filedetails.php') as $link) {
            $this->get($link)->assertOk()->assertSee('naïve café 😀');
        }
    }

    public function test_browsing_validation_and_missing_paths_are_honest(): void
    {
        foreach (['/listing.php?page=0', '/log.php?max=1001', '/log.php?sr=BASE', '/search.php?search[]=x', '/listing.php?path=/trunk/../docs', '/log.php?rev[]=20'] as $url) {
            $this->get($url)->assertStatus(400);
        }
        $this->get('/search.php?path=/example.php')->assertStatus(400);
        $this->get('/listing.php?path=/missing')->assertNotFound();
        $this->get('/log.php?sr=9999')->assertNotFound();
        $this->get('/revision.php?rev=9999')->assertNotFound();
    }

    public function test_subpath_configuration_is_rejected_before_repository_history_is_exposed(): void
    {
        config(['svn.repositories.fixture.url' => 'file://'.self::$fixture.'/repository/trunk']);
        $this->get('/listing.php')->assertStatus(503)->assertDontSee('Improve greeting café');
        $this->get('/revision.php?rev=22')->assertStatus(503)->assertDontSee('Improve greeting café');
    }

    public function test_directory_and_search_pagination_follow_real_svn_entries(): void
    {
        $directory = sys_get_temp_dir().'/larasvn-pages-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $run = fn (array $command) => (new Process($command, $directory))->setTimeout(15)->mustRun();
        try {
            $run(['svnadmin', 'create', $directory.'/repository']);
            $run(['svn', 'checkout', 'file://'.$directory.'/repository', $directory.'/working']);
            config(['svn.repositories.fixture.url' => 'file://'.$directory.'/repository']);
            $this->get('/listing.php')->assertOk()->assertSee('This directory is empty.');
            $this->get('/log.php')->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 0);
            for ($number = 1; $number <= 55; $number++) {
                file_put_contents($directory.'/working/file-'.$number.'.txt', 'content');
            }
            $run(['svn', 'add', '--force', $directory.'/working']);
            $run(['svn', 'commit', '-m', 'Add paginated files', $directory.'/working']);
            $this->get('/listing.php?page=2')->assertOk()->assertViewHas('entries', fn ($entries) => count($entries) === 5 && $entries->total() === 55);
            $response = $this->get('/search.php?search=*.txt&page=2')->assertOk();
            $response->assertViewHas('entries', fn ($entries) => count($entries) === 5 && $entries->currentPage() === 2)
                ->assertSee('search=%2A.txt');
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function links(string $html, string $endpoint): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $links = [];
        foreach ($document->getElementsByTagName('a') as $anchor) {
            $href = $anchor->getAttribute('href');
            if (parse_url($href, PHP_URL_PATH) === $endpoint) {
                $links[] = $href;
            }
        }
        $this->assertNotEmpty($links, 'Expected navigable '.$endpoint.' links.');

        return array_unique($links);
    }
}
