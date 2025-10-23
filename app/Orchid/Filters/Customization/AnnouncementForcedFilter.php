<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Customization;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class AnnouncementForcedFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Forced';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['is_forced'];
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
        return $builder->where('is_forced', $this->request->get('is_forced'));
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        return [
            Select::make('is_forced')
                ->options([
                    '1' => 'Forced',
                    '0' => 'Not Forced',
                ])
                ->value($this->request->get('is_forced'))
                ->empty('All')
                ->title('Forced'),
        ];
    }
}
