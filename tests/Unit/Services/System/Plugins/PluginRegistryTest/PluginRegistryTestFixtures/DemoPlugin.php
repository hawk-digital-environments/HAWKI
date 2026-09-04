<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Plugins\PluginRegistryTest\PluginRegistryTestFixtures;

use App\Services\System\Plugins\AbstractHawkiPlugin;

/**
 * Minimal plugin for registry tests — instantiable without a container.
 */
class DemoPlugin extends AbstractHawkiPlugin
{
}
