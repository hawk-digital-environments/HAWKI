<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\ProviderSetting;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class ProviderStatusFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Status';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['is_active'];
    }

    /**
     * Apply to a given Eloquent query builder.
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function run(Builder $builder): Builder
    {
        $status = $this->request->get('is_active');
        
        if ($status === null || $status === '') {
            return $builder;
        }

        return $builder->where('is_active', (bool) $status);
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('is_active')
                ->options([
                    1 => 'Active',
                    0 => 'Inactive',
                ])
                ->empty('All Status')
                ->value($this->request->get('is_active'))
                ->title('Status'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $status = $this->request->get('is_active');
        $statusText = $status === '1' ? 'Active' : 'Inactive';
        return $this->name().': '.$statusText;
    }
}
