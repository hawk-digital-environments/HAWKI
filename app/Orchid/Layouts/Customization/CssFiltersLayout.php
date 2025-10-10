<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Customization;

use App\Orchid\Filters\CssSearchFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class CssFiltersLayout extends Selection
{
    /**
     * @return Filter[]
     */
    public function filters(): iterable
    {
        return [
            CssSearchFilter::class,
        ];
    }
}
