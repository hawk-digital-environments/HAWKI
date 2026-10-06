<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users\Settings\Registries\UserSettingsRegistryTest\UserSettingsRegistryTestFixtures;

use App\Services\System\Plugins\Attributes\PluginName;
use App\Services\Users\Settings\AbstractUserSettings;

/**
 * Settings fixture whose public key collides with {@see DemoCoreSettings} — used to
 * prove the registry rejects duplicate keys.
 */
#[PluginName('hawki-core')]
class OtherCoreSettings extends AbstractUserSettings
{
    public string $value = 'other-default';

    public static function publicKey(): string
    {
        // Same key as DemoCoreSettings — must be rejected on declare.
        return 'demo-core';
    }
}
