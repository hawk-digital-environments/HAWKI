<?php

namespace App\Orchid\Layouts\Settings;

use App\Orchid\Filters\UsageRecordDateFilter;
use App\Orchid\Filters\UsageRecordModelFilter;
use App\Orchid\Filters\UsageRecordSearchFilter;
use App\Orchid\Filters\UsageRecordTypeFilter;
use App\Orchid\Filters\UsageRecordUserFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class UsageRecordFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            UsageRecordSearchFilter::class,
            UsageRecordUserFilter::class,
            UsageRecordTypeFilter::class,
            UsageRecordModelFilter::class,
            UsageRecordDateFilter::class,
        ];
    }
}
