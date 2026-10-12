<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\SettingsAndConfig;

use App\Services\System\Database\SettingsAndConfig\ConfigAndSettingsBlueprint;
use App\Services\System\Database\SettingsAndConfig\Exceptions\ConfigAndSettingsSchemaException;
use App\Services\System\Database\SettingsAndConfig\UserSettingsBlueprint;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\System\Database\SettingsAndConfig\ConfigAndSettingsBlueprintTest\ConfigAndSettingsBlueprintTestFixtures\BlueprintTestSettings;

#[CoversClass(ConfigAndSettingsBlueprint::class)]
#[CoversClass(UserSettingsBlueprint::class)]
class ConfigAndSettingsBlueprintTest extends TestCase
{
    // =========================================================================
    // Typed reads
    // =========================================================================

    public function testItGetReturnsTheTypedDbValue(): void
    {
        $sut = new ConfigAndSettingsBlueprint(BlueprintTestSettings::class, ['max_tokens' => '8192']);

        self::assertSame(8192, $sut->max_tokens);
    }

    public function testItGetReturnsTheClassDefaultForMissingRows(): void
    {
        $sut = new ConfigAndSettingsBlueprint(BlueprintTestSettings::class, []);

        self::assertSame(4096, $sut->max_tokens);
        self::assertSame('default', $sut->name);
    }

    public function testItGetThrowsForPropertiesTheClassDoesNotDeclare(): void
    {
        $sut = new ConfigAndSettingsBlueprint(BlueprintTestSettings::class, ['removed_key' => 'legacy']);

        $this->expectException(ConfigAndSettingsSchemaException::class);
        $this->expectExceptionMessage(\sprintf(
            'The class "%s" does not declare a property "removed_key". Use getRaw() for properties that were'
            . ' removed from the class, or check the property name.',
            BlueprintTestSettings::class,
        ));

        $sut->removed_key;
    }

    // =========================================================================
    // Raw reads
    // =========================================================================

    public function testItGetRawReturnsTheStoredStringAndNullForMissingRows(): void
    {
        $sut = new ConfigAndSettingsBlueprint(BlueprintTestSettings::class, ['max_tokens' => '8192']);

        self::assertSame('8192', $sut->getRaw('max_tokens'));
        self::assertNull($sut->getRaw('name'));
    }

    public function testItGetRawReadsRowsOfRemovedProperties(): void
    {
        $sut = new ConfigAndSettingsBlueprint(BlueprintTestSettings::class, ['removed_key' => 'legacy']);

        self::assertSame('legacy', $sut->getRaw('removed_key'));
    }

    public function testItHasRowDetectsExistingRows(): void
    {
        $sut = new ConfigAndSettingsBlueprint(BlueprintTestSettings::class, ['name' => 'custom']);

        self::assertTrue($sut->hasRow('name'));
        self::assertFalse($sut->hasRow('max_tokens'));
    }

    // =========================================================================
    // Pending writes
    // =========================================================================

    public function testItSetQueuesTypedValuesForUpsert(): void
    {
        $sut = new ConfigAndSettingsBlueprint(BlueprintTestSettings::class, []);
        $sut->max_tokens = 1024;

        self::assertSame(['max_tokens' => 1024], $sut->getPending());
        self::assertTrue(isset($sut->max_tokens));
    }

    public function testItSetThrowsForUndeclaredProperties(): void
    {
        $sut = new ConfigAndSettingsBlueprint(BlueprintTestSettings::class, []);

        $this->expectException(ConfigAndSettingsSchemaException::class);
        $this->expectExceptionMessage(\sprintf(
            'The class "%s" does not declare a property "unknown". Use getRaw() for properties that were'
            . ' removed from the class, or check the property name.',
            BlueprintTestSettings::class,
        ));

        $sut->unknown = 'x';
    }

    // =========================================================================
    // User settings flavour
    // =========================================================================

    public function testItUserSettingsBlueprintExposesTheUserId(): void
    {
        $sut = new UserSettingsBlueprint(BlueprintTestSettings::class, ['name' => 'custom'], 42);

        self::assertSame(42, $sut->getUserId());
        // Blueprint behaviour is inherited untouched.
        self::assertSame('custom', $sut->name);
        self::assertSame(4096, $sut->max_tokens);
    }
}
