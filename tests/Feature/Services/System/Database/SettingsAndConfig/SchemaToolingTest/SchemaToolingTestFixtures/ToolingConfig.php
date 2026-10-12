<?php

declare(strict_types=1);

namespace Tests\Feature\Services\System\Database\SettingsAndConfig\SchemaToolingTest\SchemaToolingTestFixtures;

use App\Services\Config\AbstractConfig;
use App\Services\System\Plugins\Attributes\PluginName;

/**
 * Config fixture for the schema tooling tests — assigned to the core plugin because
 * test fixtures live outside the `App\` namespace.
 */
#[PluginName('hawki-core')]
class ToolingConfig extends AbstractConfig
{
    public int $max_tokens = 4096;
    public string $name = 'tooling-default';
}
