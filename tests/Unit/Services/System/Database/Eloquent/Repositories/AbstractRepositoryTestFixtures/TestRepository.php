<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories\AbstractRepositoryTestFixtures;

use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;

#[UseModel(TestEloquentModel::class)]
class TestRepository extends AbstractRepository
{
}
