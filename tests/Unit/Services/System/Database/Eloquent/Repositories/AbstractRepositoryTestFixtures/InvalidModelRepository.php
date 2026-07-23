<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories\AbstractRepositoryTestFixtures;

use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;

class InvalidModelRepository extends AbstractRepository
{
    public function getModelClass(): string
    {
        return \stdClass::class;
    }
}
