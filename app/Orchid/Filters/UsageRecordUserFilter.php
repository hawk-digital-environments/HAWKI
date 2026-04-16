<?php

namespace App\Orchid\Filters;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class UsageRecordUserFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'User';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['user_id'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $userId = $this->request->get('user_id');

        if (empty($userId)) {
            return $builder;
        }

        return $builder->where('user_id', $userId);
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        return [
            Select::make('user_id')
                ->fromModel(User::class, 'name')
                ->empty('All Users')
                ->value($this->request->get('user_id'))
                ->title($this->name()),
        ];
    }
}
