<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\ModelSettings;

use App\Orchid\Filters\AssistantSearchFilter;
use App\Orchid\Filters\AssistantStatusFilter;
use App\Orchid\Filters\AssistantVisibilityFilter;
use App\Orchid\Filters\AssistantOwnerFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class AssistantFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            AssistantSearchFilter::class,
            AssistantStatusFilter::class,
            AssistantVisibilityFilter::class,
            AssistantOwnerFilter::class,
        ];
    }
}