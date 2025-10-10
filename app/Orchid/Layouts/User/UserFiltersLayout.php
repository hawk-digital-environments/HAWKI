<?php

namespace App\Orchid\Layouts\User;

use App\Orchid\Filters\AuthTypeFilter;
use App\Orchid\Filters\RoleFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class UserFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            RoleFilter::class,
            AuthTypeFilter::class,
        ];
    }
}
