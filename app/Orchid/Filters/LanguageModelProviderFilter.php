<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\ProviderSetting;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class LanguageModelProviderFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Provider';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['provider_id'];
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
        $providerId = $this->request->get('provider_id');
        
        if ($providerId === null || $providerId === '') {
            return $builder;
        }

        return $builder->where('provider_id', $providerId);
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('provider_id')
                ->options($this->getProviderOptions())
                ->empty('All Providers')
                ->value($this->request->get('provider_id'))
                ->title('Provider'),
        ];
    }

    /**
     * Get available provider options for filtering
     *
     * @return array
     */
    private function getProviderOptions(): array
    {
        return ProviderSetting::all()
            ->pluck('provider_name', 'id')
            ->toArray();
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $providerId = $this->request->get('provider_id');
        
        if (empty($providerId)) {
            return '';
        }
        
        $provider = ProviderSetting::find($providerId);
        return $this->name().': '.($provider ? $provider->provider_name : 'Unknown');
    }
}
