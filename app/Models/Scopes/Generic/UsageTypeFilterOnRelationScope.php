<?php
declare(strict_types=1);


namespace App\Models\Scopes\Generic;


use App\Models\Scopes\Traits\UsageTypeAwareScopeTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class UsageTypeFilterOnRelationScope implements Scope
{
    use UsageTypeAwareScopeTrait;

    /**
     * @param string $relationName Eloquent relation name to constrain (e.g. `'usageRules'`).
     * @param string $fieldName Column on the related table holding the usage type (default `usage_type`).
     */
    public function __construct(
        private readonly string $relationName,
        private readonly string $fieldName = 'usage_type'
    )
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas($this->relationName, function (Builder $query) {
            $query->where($this->fieldName, $this->getCurrentUsageType());
        });
    }
}
