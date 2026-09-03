<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Plugins;

use App\Services\System\Plugins\HawkiCorePlugin;
use App\Services\System\Plugins\InstalledPlugins;
use App\Services\System\Plugins\PluginRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\System\Plugins\PluginRegistryTest\PluginRegistryTestFixtures\DemoPlugin;

#[CoversClass(InstalledPlugins::class)]
class InstalledPluginsTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/InstalledPluginsTest/InstalledPluginsTestFixtures';
    private string $originalCacheFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCacheFile = InstalledPlugins::getCacheFile();
    }

    protected function tearDown(): void
    {
        // Restore the original cache file and drop the cached registry singleton,
        // so other tests never see this test's fixture cache.
        InstalledPlugins::setCacheFile($this->originalCacheFile);

        parent::tearDown();
    }

    // =========================================================================
    // Registry singleton
    // =========================================================================

    public function testItReturnsTheSameRegistryInstance(): void
    {
        InstalledPlugins::reset();

        self::assertSame(InstalledPlugins::getRegistry(), InstalledPlugins::getRegistry());
    }

    public function testItRegistryIsWiredIntoTheContainerAsTheBootstrapSingleton(): void
    {
        // The bootstrap wiring (withSingletons in bootstrap/app.php) must resolve to
        // the same instance the static wrapper holds, so constructor injection and
        // the wrapper never diverge.
        self::assertSame(InstalledPlugins::getRegistry(), $this->app->make(PluginRegistry::class));
    }

    public function testItResetCreatesAFreshRegistry(): void
    {
        $before = InstalledPlugins::getRegistry();

        InstalledPlugins::reset();

        self::assertNotSame($before, InstalledPlugins::getRegistry());
    }

    public function testItReadsPluginsFromTheCacheFile(): void
    {
        InstalledPlugins::setCacheFile(self::FIXTURES_DIR . '/demo-plugin.php');
        InstalledPlugins::reset();

        self::assertInstanceOf(DemoPlugin::class, InstalledPlugins::getPlugin('hawk/demo-plugin'));
        self::assertSame('hawk-demo-plugin', InstalledPlugins::getPlugin('hawk/demo-plugin')->getNamespace());
    }

    public function testItCoreIsPresentWithAnEmptyCacheFile(): void
    {
        InstalledPlugins::setCacheFile(self::FIXTURES_DIR . '/empty-plugins.php');
        InstalledPlugins::reset();

        self::assertInstanceOf(HawkiCorePlugin::class, InstalledPlugins::getPlugin('hawki-core'));
        self::assertNull(InstalledPlugins::guessPlugin('Hawk\\DemoPlugin\\Something'));
    }

    public function testItGuessPluginResolvesTheContainingPlugin(): void
    {
        InstalledPlugins::setCacheFile(self::FIXTURES_DIR . '/demo-plugin.php');
        InstalledPlugins::reset();

        self::assertInstanceOf(
            DemoPlugin::class,
            InstalledPlugins::guessPlugin('Hawk\\DemoPlugin\\Settings\\DemoSettings'),
        );
    }

    // =========================================================================
    // Cache file plumbing
    // =========================================================================

    public function testItSetCacheFileReturnsTheConfiguredPath(): void
    {
        $path = self::FIXTURES_DIR . '/empty-plugins.php';

        InstalledPlugins::setCacheFile($path);

        self::assertSame($path, InstalledPlugins::getCacheFile());
    }
}
