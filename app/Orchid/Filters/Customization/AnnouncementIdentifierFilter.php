<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Customization;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;

class AnnouncementIdentifierFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Identifier';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['identifier'];
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
        return $builder->where('title', 'like', '%' . $this->request->get('identifier') . '%');
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        return [
            Input::make('identifier')
                ->type('text')
                ->value($this->request->get('identifier'))
                ->placeholder('Search by identifier...')
                ->title('Identifier'),
        ];
    }
}
