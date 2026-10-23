<?php

namespace Tests\Unit;

use App\Svn\RepositoryConfiguration;
use PHPUnit\Framework\TestCase;

final class RepositoryConfigurationTest extends TestCase
{
    private function load(string $json): array
    {
        $file = tempnam(sys_get_temp_dir(), 'larasvn-config-');
        try {
            file_put_contents($file, $json);

            return RepositoryConfiguration::load($file, 'production', 'file:///fixture');
        } finally {
            unlink($file);
        }
    }

    public function test_fixture_is_available_only_in_local_and_testing_environments(): void
    {
        self::assertArrayHasKey('fixture', RepositoryConfiguration::load(null, 'local', 'file:///fixture')['repositories']);
        self::assertSame(['repositories' => [], 'error' => null], RepositoryConfiguration::load(null, 'production', 'file:///fixture', '/configured/parent'));
        foreach (['production', 'staging', 'development'] as $environment) {
            $result = RepositoryConfiguration::load(null, $environment, 'file:///fixture');
            self::assertSame([], $result['repositories']);
            self::assertNotNull($result['error']);
        }
    }

    public function test_valid_data_preserves_names_and_defaults_downloads_off(): void
    {
        $result = $this->load('{"group/hooks.v1":{"url":"file:///srv/svn/my%20hooks","group":"Tools"}}');
        self::assertNull($result['error']);
        self::assertFalse($result['repositories']['group/hooks.v1']['downloads']);
        self::assertTrue($result['repositories']['group/hooks.v1']['rss']);
    }

    public function test_unsupported_access_rules_and_malformed_data_fail_closed(): void
    {
        foreach (['[1]', 'invalid', '{"repo":"url"}', '{"../repo":{"url":"file:///repo"}}', '{"repo":{"url":"file:///repo","downloads":"false"}}', '{"repo":{"url":"file:///repo","authz":"private"}}', '{"repo":{"url":"https://user:secret@example.test/repo"}}', '{"repo":{"url":"https://example.test/repo?password=secret"}}', '{"repo":{"url":"svn+ssh://example.test/repo"}}', '{"repo":{"url":"https://example.test/repo%0d%0a"}}'] as $json) {
            $result = $this->load($json);
            self::assertSame([], $result['repositories'], $json);
            self::assertNotNull($result['error']);
            self::assertStringNotContainsString('secret', $result['error']);
        }
        self::assertNotNull(RepositoryConfiguration::load('/missing/config.json', 'production', '')['error']);
    }
}
