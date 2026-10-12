<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users\Settings\Registries;

use App\Services\System\Plugins\InstalledPlugins;
use App\Services\Users\Exceptions\DuplicateUserSettingsKeyException;
use App\Services\Users\Exceptions\InvalidUserSettingsClassException;
use App\Services\Users\Settings\Registries\UserSettingsRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\Users\Settings\Registries\UserSettingsRegistryTest\UserSettingsRegistryTestFixtures\DemoCoreSettings;
use Tests\Unit\Services\Users\Settings\Registries\UserSettingsRegistryTest\UserSettingsRegistryTestFixtures\DemoPluginSettings;
use Tests\Unit\Services\Users\Settings\Registries\UserSettingsRegistryTest\UserSettingsRegistryTestFixtures\OtherCoreSettings;

#[CoversClass(UserSettingsRegistry::class)]
class UserSettingsRegistryTest extends TestCase
{
    private UserSettingsRegistry $sut;
    private string $originalCacheFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Register the demo plugin so the plugin-namespace fixture can resolve it.
        $this->originalCacheFile = InstalledPlugins::getCacheFile();
        InstalledPlugins::setCacheFile(__DIR__ . '/../../../System/Plugins/InstalledPluginsTest/InstalledPluginsTestFixtures/demo-plugin.php');
        InstalledPlugins::reset();

        $this->sut = new UserSettingsRegistry();
    }

    protected function tearDown(): void
    {
        InstalledPlugins::setCacheFile($this->originalCacheFile);
        InstalledPlugins::reset();

        parent::tearDown();
    }

    // =========================================================================
    // declare
    // =========================================================================

    public function testItDeclaresSettingsClasses(): void
    {
        $this->sut->declare(DemoCoreSettings::class);

        self::assertSame([DemoCoreSettings::class], $this->sut->all());
    }

    public function testItDeclareIsIdempotent(): void
    {
        $this->sut->declare(DemoCoreSettings::class);
        $this->sut->declare(DemoCoreSettings::class);

        self::assertSame([DemoCoreSettings::class], $this->sut->all());
    }

    public function testItDeclareThrowsForNonSettingsClasses(): void
    {
        $this->expectException(InvalidUserSettingsClassException::class);
        $this->expectExceptionMessage(\sprintf(
            'The class "%s" must extend "%s" to be loadable via the user-settings service.',
            \stdClass::class,
            \App\Services\Users\Settings\AbstractUserSettings::class,
        ));

        $this->sut->declare(\stdClass::class);
    }

    public function testItDeclareThrowsForDuplicatePublicKeys(): void
    {
        $this->sut->declare(DemoCoreSettings::class);

        $this->expectException(DuplicateUserSettingsKeyException::class);
        $this->expectExceptionMessage(\sprintf(
            'The user-settings public key "demo-core" is already registered by "%s" and cannot be reused by "%s".'
            . ' Public keys must be globally unique across all settings classes.',
            DemoCoreSettings::class,
            OtherCoreSettings::class,
        ));

        $this->sut->declare(OtherCoreSettings::class);
    }

    // =========================================================================
    // namespace grouping
    // =========================================================================

    public function testItGroupsClassesByNamespace(): void
    {
        $this->sut->declare(DemoCoreSettings::class);
        $this->sut->declare(DemoPluginSettings::class);

        self::assertSame([
            'hawki-core' => [DemoCoreSettings::class],
            'hawk-demo-plugin' => [DemoPluginSettings::class],
        ], $this->sut->classesByNamespace());
    }

    public function testItNamespacesReturnsAllRegisteredNamespaces(): void
    {
        $this->sut->declare(DemoCoreSettings::class);
        $this->sut->declare(DemoPluginSettings::class);

        self::assertSame(['hawki-core', 'hawk-demo-plugin'], $this->sut->namespaces());
    }

    public function testItClassesForNamespaceReturnsEmptyListForUnknownNamespaces(): void
    {
        self::assertSame([], $this->sut->classesForNamespace('unknown-namespace'));
    }

    // =========================================================================
    // public key index
    // =========================================================================

    public function testItClassesByPublicKeyKeysTheClasses(): void
    {
        $this->sut->declare(DemoCoreSettings::class);
        $this->sut->declare(DemoPluginSettings::class);

        self::assertSame([
            'demo-core' => DemoCoreSettings::class,
            'demo-plugin' => DemoPluginSettings::class,
        ], $this->sut->classesByPublicKey());
    }

    public function testItRegistersTheRealCoreSettingsInProduction(): void
    {
        // The UsersServiceProvider declares CoreUserSettings — verify the wired registry.
        $registry = $this->app->make(UserSettingsRegistry::class);

        self::assertContains(\App\Services\Users\Settings\CoreUserSettings::class, $registry->all());
    }
}
