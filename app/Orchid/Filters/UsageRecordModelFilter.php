<?php

namespace App\Orchid\Filters;

use App\Models\Records\UsageRecord;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class UsageRecordModelFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Model';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['model'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $model = $this->request->get('model');

        if (empty($model)) {
            return $builder;
        }

        return $builder->where('model', $model);
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        // Get distinct models from the database
        $models = UsageRecord::select('model')
            ->distinct()
            ->orderBy('model')
            ->pluck('model', 'model')
            ->toArray();

        return [
            Select::make('model')
                ->options($models)
                ->empty('All Models')
                ->value($this->request->get('model'))
                ->title($this->name()),
        ];
    }
}
