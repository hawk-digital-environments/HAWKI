<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Config\ConfigServiceTestFixtures;

use App\Services\Config\AbstractConfig;
use Illuminate\Config\Repository;

/**
 * Minimal AbstractConfig subclass for unit tests. Has a make() method and one property.
 */
class ConcreteConfig extends AbstractConfig
{
    public string $value = 'default';

    public static function make(Repository $repo): static
    {
        return self::fromArray([
            'value' => $repo->get('test.value', 'from-make'),
        ]);
    }
}
