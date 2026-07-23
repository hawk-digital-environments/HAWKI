<?php
declare(strict_types=1);


namespace App\Models\Scopes\Generic;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

readonly class ActiveFilterOnRelationScope implements Scope
{
    /**
     * @param string $relationName Eloquent relation name to constrain (e.g. `'provider'`).
     * @param string $fieldName Column on the related table (default `active`).
     * @param string $activeValue Value that indicates an active related record (default `1`).
     */
    public function __construct(
        private string $relationName,
        private string $fieldName = 'active',
        private string $activeValue = '1'
    )
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas($this->relationName, function (Builder $query) {
            $query->where($this->fieldName, $this->activeValue);
        });
    }
}
