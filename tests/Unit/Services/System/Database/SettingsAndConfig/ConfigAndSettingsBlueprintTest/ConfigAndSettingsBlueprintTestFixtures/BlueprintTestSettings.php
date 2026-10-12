<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\SettingsAndConfig\ConfigAndSettingsBlueprintTest\ConfigAndSettingsBlueprintTestFixtures;

use App\Utils\Casts\AbstractCastableObject;

/**
 * Minimal castable for blueprint tests — two scalar properties with defaults.
 */
class BlueprintTestSettings extends AbstractCastableObject
{
    public int $max_tokens = 4096;
    public string $name = 'default';
}
