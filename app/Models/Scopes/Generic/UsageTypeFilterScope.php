<?php
declare(strict_types=1);


namespace App\Models\Scopes\Generic;


use App\Models\Scopes\Traits\UsageTypeAwareScopeTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class UsageTypeFilterScope implements Scope
{
    use UsageTypeAwareScopeTrait;

    /**
     * @param string $fieldName Column holding the usage type (default `usage_type`).
     */
    public function __construct(
        private readonly string $fieldName = 'usage_type'
    )
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($this->fieldName, $this->getCurrentUsageType());
    }
}
