<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Orchid\Filters\PromptSearchFilter;
use App\Orchid\Filters\PromptCategoryFilter;
use App\Orchid\Filters\PromptCreatorFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class PromptFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            PromptSearchFilter::class,
            PromptCategoryFilter::class,
            PromptCreatorFilter::class,
        ];
    }
}