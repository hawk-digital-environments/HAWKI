<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\ProviderSetting;
use App\Models\ApiFormat;
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
        return ['api_format_id'];
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
        $formatId = $this->request->get('api_format_id');
        
        if (empty($formatId)) {
            return $builder;
        }

        return $builder->where('api_format_id', $formatId);
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('api_format_id')
                ->options($this->getApiFormatOptions())
                ->empty('All Formats')
                ->value($this->request->get('api_format_id'))
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
        return ApiFormat::all()
            ->pluck('display_name', 'id')
            ->toArray();
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $formatId = $this->request->get('api_format_id');
        
        if (empty($formatId)) {
            return '';
        }
        
        $format = ApiFormat::find($formatId);
        return $this->name().': '.($format ? $format->display_name : 'Unknown');
    }
}
