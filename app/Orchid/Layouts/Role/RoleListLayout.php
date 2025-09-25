<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Role;

use App\Models\Role;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class RoleListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'roles';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('name', 'Name')
                ->sort()
                ->cantHide()
                ->render(fn (Role $role) => Link::make($role->name)
                    ->route('platform.systems.roles.edit', $role->id)),

            TD::make('slug', 'Slug')
                ->sort()
                ->cantHide(),

            TD::make('selfassign', 'Self-assignable')
                ->sort()
                ->render(fn (Role $role) => $role->selfassign
                    ? '<span class="badge bg-success">true</span>'
                    : '<span class="badge bg-secondary">false</span>'
                ),

            TD::make('created_at', 'Created')
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->defaultHidden()
                ->sort(),

            TD::make('updated_at', 'Last edit')
                ->usingComponent(DateTimeSplit::class)
                ->align(TD::ALIGN_RIGHT)
                ->sort(),
        ];
    }
}
