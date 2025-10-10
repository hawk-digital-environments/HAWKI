<?php

namespace App\Orchid\Layouts\RoleAssignment;

use App\Models\Employeetype;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class RoleAssignmentsListLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'employeetypes';

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): iterable
    {
        return [
            TD::make('auth_method', 'Auth Method')
                ->sort()
                ->render(function (Employeetype $employeetype) {
                    $colors = [
                        'LDAP' => 'info',
                        'OIDC' => 'success',
                        'Shibboleth' => 'warning',
                        'system' => 'secondary',
                    ];
                    $color = $colors[$employeetype->auth_method] ?? 'light';

                    return "<span class=\"badge bg-{$color}\">{$employeetype->auth_method}</span>";
                }),

            TD::make('raw_value', 'Raw Value')
                ->sort()
                ->render(function (Employeetype $employeetype) {
                    return '<code>'.e($employeetype->raw_value).'</code>';
                }),

            TD::make('display_name', 'Name')
                ->sort()
                ->render(function (Employeetype $employeetype) {
                    $name = $employeetype->display_name;
                    if (! $employeetype->is_active) {
                        return '<span class="text-muted"><s>'.$name.'</s> <small class="badge bg-secondary">inactive</small></span>';
                    }

                    return $name;
                }),

            TD::make('mapped_role', 'Mapped Role')
                ->render(function (Employeetype $employeetype) {
                    $primaryRole = $employeetype->primaryRoleAssignment();
                    if ($primaryRole && $primaryRole->role) {
                        $roleName = $primaryRole->role->name;

                        return "<span class=\"badge bg-primary\">{$roleName}</span>";
                    }

                    return '<span class="badge bg-danger">No Mapping</span>';
                }),

            TD::make('actions', 'Actions')
                ->align(TD::ALIGN_CENTER)
                ->width('120px')
                ->render(function (Employeetype $employeetype) {
                    return DropDown::make()
                        ->icon('bs.three-dots-vertical')
                        ->list([
                            Button::make('Edit Mapping')
                                ->icon('bs.pencil')
                                ->method('editMapping')
                                ->parameters(['id' => $employeetype->id]),

                            Button::make($employeetype->is_active ? 'Deactivate' : 'Activate')
                                ->icon($employeetype->is_active ? 'bs.eye-slash' : 'bs.eye')
                                ->method('toggleActive')
                                ->parameters(['id' => $employeetype->id])
                                ->confirm('Are you sure you want to '.($employeetype->is_active ? 'deactivate' : 'activate').' this mapping?'),

                            Button::make('Remove Mapping')
                                ->icon('bs.trash')
                                ->method('removeMapping')
                                ->parameters(['id' => $employeetype->id])
                                ->confirm("Are you sure you want to remove the mapping '{$employeetype->display_name}'? This action cannot be undone."),
                        ]);
                }),
        ];
    }

    protected function iconNotFound(): string
    {
        return 'bs.info-circle';
    }

    protected function textNotFound(): string
    {
        return 'No role assignments found.';
    }

    protected function subNotFound(): string
    {
        return 'Create your first employeetype to role mapping to get started.';
    }
}
