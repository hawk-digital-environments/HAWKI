<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Orchid\Filters\SystemImageSearchFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class SystemImageFiltersLayout extends Selection
{
    /**
     * @return Filter[]
     */
    public function filters(): iterable
    {
        return [
            SystemImageSearchFilter::class,
        ];
    }
}
