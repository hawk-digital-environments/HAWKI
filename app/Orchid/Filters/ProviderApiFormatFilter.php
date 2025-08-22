<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\ProviderSetting;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class ProviderApiFormatFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'API Format';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['api_format'];
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
        $format = $this->request->get('api_format');
        
        if (empty($format)) {
            return $builder;
        }

        return $builder->where('api_format', $format);
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('api_format')
                ->options($this->getApiFormatOptions())
                ->empty('All Formats')
                ->value($this->request->get('api_format'))
                ->title('API Format'),
        ];
    }

    /**
     * Get available API format options for filtering
     *
     * @return array
     */
    private function getApiFormatOptions(): array
    {
        return ProviderSetting::whereNotNull('api_format')
            ->distinct()
            ->pluck('api_format')
            ->mapWithKeys(fn ($format) => [$format => ucfirst($format)])
            ->toArray();
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $format = $this->request->get('api_format');
        return $this->name().': '.ucfirst($format);
    }
}
