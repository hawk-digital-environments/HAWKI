<?php

namespace App\Orchid\Layouts\Settings;

use App\Orchid\Filters\BackupDateFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class BackupFiltersLayout extends Selection
{
    /**
     * @return Filter[]
     */
    public function filters(): iterable
    {
        return [
            BackupDateFilter::class,
        ];
    }
}
