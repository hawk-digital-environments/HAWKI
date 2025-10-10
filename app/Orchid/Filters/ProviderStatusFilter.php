<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class ProviderStatusFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Provider Status';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['provider_status'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $status = $this->request->get('provider_status');

        if (is_null($status) || $status === '') {
            return $builder;
        }

        return $builder->where('is_active', (bool) $status);
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Select::make('provider_status')
                ->fromModel(\App\Models\ApiProvider::class, 'is_active')
                ->options([
                    '' => 'All Providers',
                    '1' => 'Active',
                    '0' => 'Inactive',
                ])
                ->value($this->request->get('provider_status'))
                ->title('Status')
                ->empty('All Providers'),
        ];
    }

    /**
     * Get the display value for the filter.
     */
    public function value(): string
    {
        $status = $this->request->get('provider_status');
        
        return match ($status) {
            '1' => 'Active',
            '0' => 'Inactive',
            default => 'All Providers'
        };
    }
}
