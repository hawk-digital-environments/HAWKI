<?php
declare(strict_types=1);


namespace App\Models\Scopes\Generic;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

readonly class ActiveFilterScope implements Scope
{
    /**
     * @param string $fieldName Column to compare against (default `active`).
     * @param string $activeValue Value that indicates an active record (default `1`).
     */
    public function __construct(
        private string $fieldName = 'active',
        private string $activeValue = '1'
    )
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($this->fieldName, $this->activeValue);
    }
}
