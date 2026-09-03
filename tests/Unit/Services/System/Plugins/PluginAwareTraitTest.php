<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Plugins;

use App\Services\Ai\Config\AiConfig;
use App\Services\System\Plugins\Attributes\PluginName;
use App\Services\System\Plugins\Exceptions\PluginNotFoundException;
use App\Services\System\Plugins\HawkiCorePlugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Services\System\Plugins\PluginAwareTraitTest\PluginAwareTraitTestFixtures\ClassWithExplicitPluginName;
use Tests\Unit\Services\System\Plugins\PluginAwareTraitTest\PluginAwareTraitTestFixtures\ClassWithoutPluginResolution;

#[CoversClass(PluginName::class)]
#[CoversTrait(\App\Services\System\Plugins\PluginAwareTrait::class)]
class PluginAwareTraitTest extends TestCase
{
    protected function tearDown(): void
    {
        ClassWithExplicitPluginName::setContainingPlugin(null);
        ClassWithoutPluginResolution::setContainingPlugin(null);

        parent::tearDown();
    }

    // =========================================================================
    // Implicit resolution
    // =========================================================================

    public function testItResolvesAppClassesToTheCorePlugin(): void
    {
        // AiConfig lives in App\ — the implicit prefix resolution must find the core.
        self::assertSame('hawki-core', AiConfig::namespace());
    }

    // =========================================================================
    // Explicit resolution
    // =========================================================================

    public function testItResolvesThePluginFromTheAttribute(): void
    {
        self::assertInstanceOf(
            HawkiCorePlugin::class,
            ClassWithExplicitPluginName::getContainingPlugin(),
        );
    }

    public function testItCachesTheResolutionPerClass(): void
    {
        $first = ClassWithExplicitPluginName::getContainingPlugin();
        $second = ClassWithExplicitPluginName::getContainingPlugin();

        self::assertSame($first, $second);
    }

    // =========================================================================
    // Test helper
    // =========================================================================

    public function testItSetContainingPluginOverridesTheResolution(): void
    {
        $plugin = new HawkiCorePlugin();
        ClassWithExplicitPluginName::setContainingPlugin($plugin);

        self::assertSame($plugin, ClassWithExplicitPluginName::getContainingPlugin());

        ClassWithExplicitPluginName::setContainingPlugin(null);

        self::assertNotSame($plugin, ClassWithExplicitPluginName::getContainingPlugin());
    }

    // =========================================================================
    // Failure
    // =========================================================================

    public function testItThrowsWhenNoPluginMatchesAndNoAttributeIsSet(): void
    {
        $this->expectException(PluginNotFoundException::class);
        $this->expectExceptionMessage(\sprintf(
            'Could not resolve the plugin owning the class "%s": no installed plugin namespace'
            . ' matches the class name, and the class does not declare a "%s" attribute.'
            . ' Add the attribute to the class, or move the class into a plugin namespace.',
            ClassWithoutPluginResolution::class,
            PluginName::class,
        ));

        ClassWithoutPluginResolution::getContainingPlugin();
    }
}
