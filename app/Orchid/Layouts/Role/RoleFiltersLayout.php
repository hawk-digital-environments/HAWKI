<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Role;

use App\Orchid\Filters\SelfAssignFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class RoleFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            SelfAssignFilter::class,
        ];
    }
}
