<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\ApiFormat;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class ProviderApiFormatFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'API Format';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['api_format'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $formatId = $this->request->get('api_format');

        if (empty($formatId)) {
            return $builder;
        }

        return $builder->where('api_format_id', $formatId);
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        return [
            Select::make('api_format')
                ->fromModel(ApiFormat::class, 'display_name', 'id')
                ->value($this->request->get('api_format'))
                ->title('API Format')
                ->empty('All Formats'),
        ];
    }

    /**
     * Get the display value for the filter.
     */
    public function value(): string
    {
        $formatId = $this->request->get('api_format');
        
        if (empty($formatId)) {
            return 'All Formats';
        }

        $format = ApiFormat::find($formatId);
        return $format ? $format->display_name : 'Unknown Format';
    }
}
