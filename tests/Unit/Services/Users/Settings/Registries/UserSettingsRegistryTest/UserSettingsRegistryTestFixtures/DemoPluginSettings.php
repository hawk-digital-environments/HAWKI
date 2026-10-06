<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users\Settings\Registries\UserSettingsRegistryTest\UserSettingsRegistryTestFixtures;

use App\Services\System\Plugins\Attributes\PluginName;
use App\Services\Users\Settings\AbstractUserSettings;

/**
 * Settings fixture belonging to the demo plugin namespace — requires the demo plugin
 * to be registered in the plugin registry (done via a fixture cache file). The
 * attribute takes the plugin's Composer package name; the namespace
 * (`hawk-demo-plugin`) is derived from it.
 */
#[PluginName('hawk/demo-plugin')]
class DemoPluginSettings extends AbstractUserSettings
{
    public string $value = 'plugin-default';

    public static function publicKey(): string
    {
        return 'demo-plugin';
    }
}
