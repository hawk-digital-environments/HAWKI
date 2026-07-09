<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories\Traits\GuessesModelNameTraitTestFixtures;

use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel as LaravelUseModel;

#[LaravelUseModel(TestEloquentModel::class)]
class WithWrongAttributeRepository extends AbstractRepository
{
}
