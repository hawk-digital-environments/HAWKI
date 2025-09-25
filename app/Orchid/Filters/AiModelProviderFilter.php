<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\ApiProvider;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class AiModelProviderFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Provider';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['provider_filter'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $providerId = $this->request->get('provider_filter');

        if (empty($providerId)) {
            return $builder;
        }

        return $builder->where('ai_models.provider_id', $providerId);
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Select::make('provider_filter')
                ->fromModel(ApiProvider::class, 'provider_name', 'id')
                ->value($this->request->get('provider_filter'))
                ->title('Provider')
                ->empty('All Providers'),
        ];
    }

    /**
     * Get the value to display for this filter.
     */
    public function value(): string
    {
        $providerId = $this->request->get('provider_filter');
        
        if (empty($providerId)) {
            return '';
        }

        $provider = ApiProvider::find($providerId);
        
        return $provider ? $provider->provider_name : "Provider #{$providerId}";
    }


}
