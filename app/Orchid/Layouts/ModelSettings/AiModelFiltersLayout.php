<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Orchid\Filters\AiModelActiveFilter;
use App\Orchid\Filters\AiModelDateFilter;
use App\Orchid\Filters\AiModelProviderFilter;
use App\Orchid\Filters\AiModelSearchFilter;
use App\Orchid\Filters\AiModelVisibleFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class AiModelFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            AiModelSearchFilter::class,
            AiModelProviderFilter::class,
            AiModelActiveFilter::class,
            AiModelVisibleFilter::class,
            AiModelDateFilter::class,
        ];
    }
}
