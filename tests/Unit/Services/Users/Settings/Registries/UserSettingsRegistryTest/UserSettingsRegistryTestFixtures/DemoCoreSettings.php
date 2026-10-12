<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users\Settings\Registries\UserSettingsRegistryTest\UserSettingsRegistryTestFixtures;

use App\Services\System\Plugins\Attributes\PluginName;
use App\Services\Users\Settings\AbstractUserSettings;

/**
 * Settings fixture belonging to the core namespace via the explicit plugin attribute.
 */
#[PluginName('hawki-core')]
class DemoCoreSettings extends AbstractUserSettings
{
    public string $value = 'core-default';

    public static function publicKey(): string
    {
        return 'demo-core';
    }
}
