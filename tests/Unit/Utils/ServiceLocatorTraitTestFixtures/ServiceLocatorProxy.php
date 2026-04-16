<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\ServiceLocatorTraitTestFixtures;

use App\Utils\ServiceLocatorTrait;

class ServiceLocatorProxy
{
    use ServiceLocatorTrait;

    public function resolveService(string $id): mixed
    {
        return $this->getServiceInstance($id);
    }
}
