<?php

namespace App\Orchid\Layouts\RoleAssignment;

use App\Models\EmployeetypeRole;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class CurrentAssignmentsLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'current_assignments';

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): iterable
    {
        return [
            TD::make('role', 'Role')
                ->render(function (EmployeetypeRole $assignment) {
                    if ($assignment->role) {
                        return "<span class='badge bg-primary'>{$assignment->role->name}</span>";
                    } else {
                        return "<span class='badge bg-warning'>Role not found</span>";
                    }
                }),

            TD::make('type', 'Type')
                ->render(function (EmployeetypeRole $assignment) {
                    if ($assignment->is_primary) {
                        return "<span class='badge bg-success'>Primary</span>";
                    } else {
                        return "<span class='badge bg-secondary'>Secondary</span>";
                    }
                }),

            TD::make('created_at', 'Created')
                ->render(function (EmployeetypeRole $assignment) {
                    return "<small class='text-muted'>{$assignment->created_at->format('M d, Y')}</small>";
                }),

            TD::make('actions', 'Actions')
                ->align(TD::ALIGN_CENTER)
                ->width('120px')
                ->render(function (EmployeetypeRole $assignment) {
                    $actions = [];

                    if (! $assignment->is_primary) {
                        $actions[] = Button::make('Make Primary')
                            ->icon('bs.star')
                            ->method('makePrimary')
                            ->parameters(['assignment' => $assignment->id])
                            ->confirm('Make this role the primary assignment?');
                    }

                    $actions[] = Button::make('Remove')
                        ->icon('bs.trash')
                        ->method('removeAssignment')
                        ->parameters(['assignment' => $assignment->id])
                        ->confirm('Are you sure you want to remove this role assignment?');

                    return DropDown::make()
                        ->icon('bs.three-dots-vertical')
                        ->list($actions);
                }),
        ];
    }

    protected function iconNotFound(): string
    {
        return 'bs.info-circle';
    }

    protected function textNotFound(): string
    {
        return 'No role assignments found for this employeetype.';
    }

    protected function subNotFound(): string
    {
        return 'Use the form above to assign roles to this employeetype.';
    }
}
