<?php

namespace App\Orchid\Layouts\User;

use App\Orchid\Filters\AuthTypeFilter;
use App\Orchid\Filters\RoleFilter;
use App\Orchid\Filters\UserCreatedDateFilter;
use App\Orchid\Filters\UserSearchFilter;
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
            UserSearchFilter::class,
            RoleFilter::class,
            AuthTypeFilter::class,
            UserCreatedDateFilter::class,
        ];
    }
}
