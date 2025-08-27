<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Orchid\Filters\LanguageModelSearchFilter;
use App\Orchid\Filters\LanguageModelProviderFilter;
use App\Orchid\Filters\LanguageModelActiveFilter;
use App\Orchid\Filters\LanguageModelVisibleFilter;
use App\Orchid\Filters\LanguageModelDateFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class LanguageModelFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            LanguageModelSearchFilter::class,
            LanguageModelProviderFilter::class,
            LanguageModelActiveFilter::class,
            LanguageModelVisibleFilter::class,
            LanguageModelDateFilter::class,
        ];
    }
}
