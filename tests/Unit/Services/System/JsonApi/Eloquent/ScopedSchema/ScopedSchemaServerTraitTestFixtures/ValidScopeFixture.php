<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ValidScopeFixture implements Scope
{
    public function apply(Builder $builder, Model $model): void {}
}
