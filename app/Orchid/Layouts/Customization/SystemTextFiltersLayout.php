<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Orchid\Filters\SystemTextSearchFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class SystemTextFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            SystemTextSearchFilter::class,
        ];
    }
}
