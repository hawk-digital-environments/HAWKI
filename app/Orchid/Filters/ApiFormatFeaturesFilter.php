<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class ApiFormatFeaturesFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Features';
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['features'];
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
        $feature = $this->request->get('features');
        
        if ($feature === null || $feature === '') {
            return $builder;
        }

        switch ($feature) {
            case 'streaming':
                return $builder->whereJsonContains('metadata->supports_streaming', true);
            case 'functions':
                return $builder->whereJsonContains('metadata->supports_function_calling', true);
            case 'grounding':
                return $builder->whereJsonContains('metadata->supports_grounding', true);
            case 'vision':
                return $builder->whereJsonContains('metadata->supports_vision', true);
            default:
                return $builder;
        }
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('features')
                ->options([
                    'streaming' => 'Streaming Support',
                    'functions' => 'Function Calling',
                    'grounding' => 'Grounding',
                    'vision' => 'Vision/Image Support',
                ])
                ->empty('All Features')
                ->value($this->request->get('features'))
                ->title('Features'),
        ];
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        $feature = $this->request->get('features');
        $featureText = [
            'streaming' => 'Streaming Support',
            'functions' => 'Function Calling',
            'grounding' => 'Grounding',
            'vision' => 'Vision/Image Support',
        ];
        
        return $this->name().': '.($featureText[$feature] ?? 'All');
    }
}
