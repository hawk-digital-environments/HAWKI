<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Customization;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class AnnouncementTypeFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Type';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['type'];
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
        return $builder->where('type', $this->request->get('type'));
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        return [
            Select::make('type')
                ->options([
                    'policy' => 'Policy',
                    'news' => 'News',
                    'system' => 'System',
                    'event' => 'Event',
                    'info' => 'Info',
                ])
                ->value($this->request->get('type'))
                ->empty('All Types')
                ->title('Type'),
        ];
    }
}
