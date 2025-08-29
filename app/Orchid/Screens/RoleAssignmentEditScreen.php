<?php

namespace App\Orchid\Screens;

use App\Models\Employeetype;
use App\Models\EmployeetypeRole;
use App\Orchid\Layouts\RoleAssignment\CurrentAssignmentsLayout;
use App\Orchid\Layouts\RoleAssignment\EmployeetypeDefinitionLayout;
use App\Orchid\Layouts\RoleAssignment\RoleMappingLayout;
use App\Services\EmployeetypeMappingService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Illuminate\Http\Request;
use Orchid\Platform\Models\Role;
use Illuminate\Validation\Rule;

class RoleAssignmentEditScreen extends Screen
{
    /**
     * @var Employeetype
     */
    public $employeetype;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Employeetype $employeetype = null): iterable
    {
        // Create new instance if none provided (for create route)
        $this->employeetype = $employeetype ?: new Employeetype();
        
        return [
            'employeetype' => $this->employeetype,
            'assigned_roles' => $this->employeetype->exists ? 
                $this->employeetype->employeetypeRoles()->pluck('role_id')->toArray() : 
                [],
            'current_assignments' => $this->employeetype->exists ? 
                $this->employeetype->employeetypeRoles()->with('role')->get() : 
                collect(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return ($this->employeetype && $this->employeetype->exists) 
            ? "Edit Mapping: {$this->employeetype->display_name}"
            : 'Create New Employeetype Mapping';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Save')
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        $layouts = [
            Layout::block([
                EmployeetypeDefinitionLayout::class
            ])
                ->title('Employeetype Definition')
                ->description('Define the employeetype value that the authentication system will provide'),

            Layout::block([
                RoleMappingLayout::class
            ])
                ->title('Role Mapping')
                ->description('Select which roles users will receive when their authentication system returns this employeetype'),
        ];

        // Add current assignments display only if employeetype exists
        if ($this->employeetype && $this->employeetype->exists) {
            $layouts[] = Layout::block([
                CurrentAssignmentsLayout::class
            ])
                ->title('Current Role Assignments')
                ->description('Manage existing role assignments for this employeetype');
        }

        return $layouts;
    }

    /**
     * Save the employeetype and role assignment
     */
    public function save(Request $request, Employeetype $employeetype = null)
    {
        $request->validate([
            'employeetype.display_name' => 'required|string|max:255',
            'employeetype.raw_value' => [
                'required',
                'string',
                'max:255',
                Rule::unique('employeetypes', 'raw_value')
                    ->where('auth_method', $request->input('employeetype.auth_method'))
                    ->ignore($employeetype ? $employeetype->id : null)
                    ->where(function($query) {
                        $query->where('raw_value', '!=', ''); // Ignore empty raw values for uniqueness
                    })
            ],
            'employeetype.auth_method' => 'required|in:LDAP,OIDC,Shibboleth,system',
            'employeetype.is_active' => 'boolean',
            'employeetype.description' => 'nullable|string',
            'assigned_roles' => 'nullable|array',
            'assigned_roles.*' => 'integer|exists:roles,id',
        ]);

        // Create new instance if none provided (for create route)
        if (!$employeetype) {
            $employeetype = new Employeetype();
        }

        // Save employeetype
        $employeetype->fill($request->get('employeetype', []))->save();

        // Handle role assignments
        $assignedRoles = $request->get('assigned_roles', []);
        
        // Remove all existing role assignments
        $employeetype->employeetypeRoles()->delete();
        
        // Create new role assignments
        if (!empty($assignedRoles)) {
            foreach ($assignedRoles as $index => $roleId) {
                EmployeetypeRole::create([
                    'employeetype_id' => $employeetype->id,
                    'role_id' => $roleId,
                    'is_primary' => $index === 0, // First role is primary
                ]);
            }
        }

        Toast::success('Employeetype mapping has been saved successfully.');

        return redirect()->route('platform.role-assignments');
    }

    /**
     * Make a role assignment primary
     */
    public function makePrimary(Request $request)
    {
        $assignmentId = $request->get('assignment');
        $assignment = EmployeetypeRole::find($assignmentId);
        
        if (!$assignment) {
            Toast::error('Assignment not found.');
            return redirect()->back();
        }

        // Remove primary flag from all other assignments for this employeetype
        EmployeetypeRole::where('employeetype_id', $assignment->employeetype_id)
            ->update(['is_primary' => false]);
        
        // Set this assignment as primary
        $assignment->update(['is_primary' => true]);
        
        Toast::success('Assignment set as primary successfully.');
        
        return redirect()->back();
    }
    
    /**
     * Remove a role assignment
     */
    public function removeAssignment(Request $request)
    {
        $assignmentId = $request->get('assignment');
        $assignment = EmployeetypeRole::find($assignmentId);
        
        if (!$assignment) {
            Toast::error('Assignment not found.');
            return redirect()->back();
        }
        
        $roleName = $assignment->role ? $assignment->role->name : 'Unknown Role';
        $assignment->delete();
        
        Toast::success("Role assignment '{$roleName}' has been removed successfully.");
        
        return redirect()->back();
    }

    /**
     * Permission required to access this screen
     */
    public function permission(): ?iterable
    {
        return ['platform.role-assignments'];
    }
}
