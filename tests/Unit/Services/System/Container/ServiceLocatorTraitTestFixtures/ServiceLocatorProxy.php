<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Container\ServiceLocatorTraitTestFixtures;

use App\Services\System\Container\ServiceLocatorTrait;

class ServiceLocatorProxy
{
    use ServiceLocatorTrait;

    public function resolveService(string $id): mixed
    {
        return $this->getService($id);
    }
}
