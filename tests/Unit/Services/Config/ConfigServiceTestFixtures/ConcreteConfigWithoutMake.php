<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Config\ConfigServiceTestFixtures;

use App\Services\Config\AbstractConfig;

/**
 * AbstractConfig subclass that intentionally omits make() — used to test the missing-make guard.
 */
class ConcreteConfigWithoutMake extends AbstractConfig
{
    public string $value = '';
}
