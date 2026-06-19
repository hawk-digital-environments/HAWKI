<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTraitTestFixtures;

use App\Models\Scopes\Abstracts\AbstractScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ValidAbstractScopeFixture extends AbstractScope
{
    public static function getDisablingQueryValue(): string
    {
        return 'test_abstract_scope';
    }

    public function hasAppAndRequestInjected(): bool
    {
        return isset($this->application) && isset($this->request);
    }

    public function apply(Builder $builder, Model $model): void
    {
    }
}
