<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class AssistantOwnerFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Owner';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['assistant_owner'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $ownerId = $this->request->get('assistant_owner');

        if (empty($ownerId)) {
            return $builder;
        }

        return $builder->where('owner_id', $ownerId);
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        $users = User::where('isRemoved', false)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        return [
            Select::make('assistant_owner')
                ->options($users)
                ->value($this->request->get('assistant_owner'))
                ->empty('All Owners')
                ->title('Owner'),
        ];
    }
}