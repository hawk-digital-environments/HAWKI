<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Orchid\Filters\LocalizedTextSearchFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class LocalizedTextFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            LocalizedTextSearchFilter::class,
        ];
    }
}
