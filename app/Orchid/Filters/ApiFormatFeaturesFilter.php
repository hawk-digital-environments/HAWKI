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
     */
    public function name(): string
    {
        return 'Features';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): ?array
    {
        return ['features_filter'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $feature = $this->request->get('features_filter');

        if (empty($feature)) {
            return $builder;
        }

        return $builder->whereJsonContains('metadata->features', $feature);
    }

    /**
     * Get the display fields.
     */
    public function display(): iterable
    {
        $commonFeatures = [
            '' => 'All Features',
            'streaming' => 'Streaming Support',
            'functions' => 'Function Calling',
            'vision' => 'Vision/Image Support',
            'embeddings' => 'Embeddings',
            'fine-tuning' => 'Fine-tuning',
            'moderation' => 'Content Moderation',
        ];

        return [
            Select::make('features_filter')
                ->options($commonFeatures)
                ->value($this->request->get('features_filter'))
                ->title('Features')
                ->empty('All Features'),
        ];
    }
}
