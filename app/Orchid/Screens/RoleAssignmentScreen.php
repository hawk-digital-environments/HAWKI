<?php

namespace App\Orchid\Screens;

use App\Models\Employeetype;
use App\Models\EmployeetypeRole;
use App\Orchid\Layouts\RoleAssignment\RoleAssignmentsListLayout;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Illuminate\Http\Request;
use Orchid\Platform\Models\Role;

class RoleAssignmentScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'employeetypes' => Employeetype::with(['employeetypeRoles.role'])->get(), // Alle Einträge anzeigen, nicht nur aktive
            'available_roles' => Role::all()->pluck('name', 'slug'),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Role Assignments';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Manage how authentication system values are mapped to user roles during account creation.';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Add')
                ->icon('bs.plus-circle')
                ->method('createMapping'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            RoleAssignmentsListLayout::class,
        ];
    }

    /**
     * Edit employeetype mapping
     */
    public function editMapping(Request $request)
    {
        $employeetypeId = $request->get('id');
        $employeetype = Employeetype::find($employeetypeId);
        
        if (!$employeetype) {
            Toast::error('Employeetype not found.');
            return redirect()->route('platform.role-assignments');
        }

        return redirect()->route('platform.role-assignments.edit', $employeetype);
    }

    /**
     * Remove employeetype mapping completely
     */
    public function removeMapping(Request $request)
    {
        $employeetypeId = $request->get('id');
        $employeetype = Employeetype::find($employeetypeId);
        
        if (!$employeetype) {
            Toast::error('Employeetype not found.');
            return redirect()->route('platform.role-assignments');
        }

        $displayName = $employeetype->display_name;
        
        // Remove all role assignments first
        $employeetype->employeetypeRoles()->delete();
        
        // Remove the employeetype itself
        $employeetype->delete();
        
        Toast::success("Mapping '{$displayName}' has been removed successfully.");
        
        return redirect()->route('platform.role-assignments');
    }

    /**
     * Toggle employeetype active status
     */
    public function toggleActive(Request $request)
    {
        $employeetypeId = $request->get('id');
        $employeetype = Employeetype::find($employeetypeId);
        
        if (!$employeetype) {
            Toast::error('Employeetype not found.');
            return redirect()->route('platform.role-assignments');
        }

        $employeetype->update(['is_active' => !$employeetype->is_active]);
        
        $status = $employeetype->is_active ? 'activated' : 'deactivated';
        Toast::success("Employeetype '{$employeetype->display_name}' has been {$status}.");
        
        return redirect()->route('platform.role-assignments');
    }

    /**
     * Create a new employeetype mapping
     */
    public function createMapping(Request $request)
    {
        // Find the next available increment number
        $baseRawValue = 'new_mapping';
        $increment = 1;
        
        while (Employeetype::where('raw_value', $baseRawValue . '_' . $increment)
                           ->where('auth_method', 'LDAP')
                           ->exists()) {
            $increment++;
        }
        
        $finalRawValue = $baseRawValue . '_' . $increment;
        $displayName = 'New Mapping ' . $increment;
        
        // Create a new empty employeetype
        $employeetype = Employeetype::create([
            'raw_value' => $finalRawValue,
            'auth_method' => 'LDAP',
            'display_name' => $displayName,
            'is_active' => true,
            'description' => 'Please configure this mapping',
        ]);

        Toast::success("New mapping '{$employeetype->display_name}' created. Click 'Edit Mapping' to configure it.");
        
        return redirect()->route('platform.role-assignments');
    }

    /**
     * Permission required to access this screen
     */
    public function permission(): ?iterable
    {
        return ['platform.role-assignments'];
    }
}
