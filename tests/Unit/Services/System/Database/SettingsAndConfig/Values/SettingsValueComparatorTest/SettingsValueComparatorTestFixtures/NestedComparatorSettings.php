<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\SettingsAndConfig\Values\SettingsValueComparatorTest\SettingsValueComparatorTestFixtures;

use App\Services\Users\Settings\Values\Theme;
use App\Utils\Casts\AbstractCastableObject;

/**
 * Nested castable object for the comparator tests.
 */
class NestedComparatorSettings extends AbstractCastableObject
{
    public string $street = '';
    public Theme $theme = Theme::Light;
}
