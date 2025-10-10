<?php

namespace App\Orchid\Layouts\ModelSettings;

use App\Orchid\Filters\ApiFormatFeaturesFilter;
use App\Orchid\Filters\ApiFormatSearchFilter;
use App\Orchid\Filters\ApiFormatUsageFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class ApiFormatFiltersLayout extends Selection
{
    /**
     * @return Filter[]
     */
    public function filters(): iterable
    {
        return [
            ApiFormatSearchFilter::class,
            ApiFormatUsageFilter::class,
            ApiFormatFeaturesFilter::class,
        ];
    }
}
