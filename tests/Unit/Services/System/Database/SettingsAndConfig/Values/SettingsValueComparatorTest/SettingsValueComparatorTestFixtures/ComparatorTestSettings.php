<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\SettingsAndConfig\Values\SettingsValueComparatorTest\SettingsValueComparatorTestFixtures;

use App\Services\Users\Settings\Values\Theme;
use App\Utils\Casts\AbstractCastableObject;
use App\Utils\Casts\CastedValue;
use Carbon\Carbon;

/**
 * Settings-like castable covering every comparison category: scalars, arrays, enums,
 * dates, encrypted values and nested castable objects.
 */
class ComparatorTestSettings extends AbstractCastableObject
{
    public int $max_tokens = 4096;
    public string $name = '';

    /**
     * @var list<string>
     */
    public array $allowed = [];
    public Theme $theme = Theme::Light;
    public ?Carbon $created_at = null;

    #[CastedValue('encrypted:string')]
    public string $secret = '';
    public NestedComparatorSettings $nested;
}
