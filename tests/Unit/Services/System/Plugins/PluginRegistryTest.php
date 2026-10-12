<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Plugins;

use App\Services\System\Plugins\AbstractHawkiPlugin;
use App\Services\System\Plugins\Exceptions\PluginNotFoundException;
use App\Services\System\Plugins\HawkiCorePlugin;
use App\Services\System\Plugins\PluginRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\System\Plugins\PluginRegistryTest\PluginRegistryTestFixtures\DemoPlugin;

#[CoversClass(PluginRegistry::class)]
#[CoversClass(HawkiCorePlugin::class)]
#[CoversClass(AbstractHawkiPlugin::class)]
class PluginRegistryTest extends TestCase
{
    // =========================================================================
    // all / get
    // =========================================================================

    public function testItContainsTheCorePluginWithoutAnyCache(): void
    {
        $sut = new PluginRegistry();

        $plugins = $sut->all();

        self::assertNotEmpty($plugins);
        self::assertContainsOnlyInstancesOf(HawkiCorePlugin::class, $plugins);
    }

    public function testItGetReturnsCorePluginByName(): void
    {
        $sut = new PluginRegistry();

        self::assertInstanceOf(HawkiCorePlugin::class, $sut->get('hawki-core'));
    }

    public function testItGetReturnsPluginByClassName(): void
    {
        $sut = new PluginRegistry();

        self::assertInstanceOf(HawkiCorePlugin::class, $sut->get(HawkiCorePlugin::class));
    }

    public function testItGetThrowsForUnknownIdentifier(): void
    {
        $sut = new PluginRegistry();

        $this->expectException(PluginNotFoundException::class);
        $this->expectExceptionMessage(
            'No installed plugin found for identifier "hawk/unknown-plugin". Either the plugin is not installed,'
            . ' or the plugin cache at "bootstrap/cache/plugins.php" is outdated and needs to be rebuilt.',
        );

        $sut->get('hawk/unknown-plugin');
    }

    // =========================================================================
    // guess
    // =========================================================================

    public function testItGuessesTheCorePluginForAppClasses(): void
    {
        $sut = new PluginRegistry();

        self::assertInstanceOf(
            HawkiCorePlugin::class,
            $sut->guess(\App\Services\Users\Settings\CoreUserSettings::class),
        );
    }

    public function testItGuessesPluginClassesByLongestNamespacePrefix(): void
    {
        $sut = new PluginRegistry([
            'hawk/demo-plugin' => [
                'class' => DemoPlugin::class,
                'namespace' => 'hawk-demo-plugin',
                'namespaces' => ['Hawk\\DemoPlugin\\'],
                'version' => '1.0.0',
                'path' => '/plugins/hawk/demo-plugin',
            ],
        ]);

        self::assertInstanceOf(
            DemoPlugin::class,
            $sut->guess('Hawk\\DemoPlugin\\Config\\DemoConfig'),
        );
        // Core still resolves for non-plugin classes.
        self::assertInstanceOf(
            HawkiCorePlugin::class,
            $sut->guess(\App\Services\Config\AbstractConfig::class),
        );
        // Unknown namespaces resolve to nothing.
        self::assertNull($sut->guess('Some\\Other\\Vendor\\SomeClass'));
    }

    // =========================================================================
    // noop extension surfaces
    // =========================================================================

    public function testItReturnsEmptyExtensionListsByDefault(): void
    {
        $sut = new PluginRegistry();

        self::assertSame([], $sut->getServiceProviders());
        self::assertSame([], $sut->getTranslationLoaders());
        self::assertSame([], $sut->getEventListenerPaths());
    }

    // =========================================================================
    // namespace derivation
    // =========================================================================

    public function testItDerivesTheNamespaceFromThePackageName(): void
    {
        $plugin = new DemoPlugin('hawk/demo-plugin', '1.2.3');

        self::assertSame('hawk-demo-plugin', $plugin->getNamespace());
    }

    public function testItCorePluginNamespaceIsHawkiCore(): void
    {
        self::assertSame('hawki-core', (new HawkiCorePlugin())->getNamespace());
    }
}
