<?php

declare(strict_types=1);

namespace App\Orchid\Filters\Customization;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Platform\Models\Role;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;

class AnnouncementAudienceFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Audience';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['audience'];
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
        $audience = $this->request->get('audience');
        
        if ($audience === 'global') {
            return $builder->where('is_global', true);
        }
        
        return $builder->where('is_global', false)
                      ->whereJsonContains('target_roles', $audience);
    }

    /**
     * Get the display fields.
     *
     * @return Field[]
     */
    public function display(): iterable
    {
        $roles = Role::all()->pluck('name', 'slug')->toArray();
        $roles = ['global' => 'Global'] + $roles;

        return [
            Select::make('audience')
                ->options($roles)
                ->value($this->request->get('audience'))
                ->empty('All Audiences')
                ->title('Audience'),
        ];
    }
}
