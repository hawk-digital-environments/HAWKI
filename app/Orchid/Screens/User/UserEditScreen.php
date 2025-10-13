<?php

declare(strict_types=1);

namespace App\Orchid\Screens\User;

use App\Models\User;
use App\Orchid\Layouts\User\UserApprovalLayout;
use App\Orchid\Layouts\User\UserEditLayout;
use App\Orchid\Layouts\User\UserPasswordLayout;
use App\Orchid\Layouts\User\UserRoleLayout;
use App\Services\EmployeetypeMappingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Orchid\Access\Impersonation;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class UserEditScreen extends Screen
{
    /**
     * @var User
     */
    public $user;

    protected EmployeetypeMappingService $employeetypeMappingService;

    public function __construct(EmployeetypeMappingService $employeetypeMappingService)
    {
        $this->employeetypeMappingService = $employeetypeMappingService;
    }

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(User $user): iterable
    {
        $user->load(['roles']);

        return [
            'user' => $user,
            'permission' => $user->getStatusPermission(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return $this->user->exists ? 'Edit User' : 'Create User';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'User profile and privileges, including their associated role.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.access.users',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Impersonate user')
                ->icon('bg.box-arrow-in-right')
                ->confirm('You can revert to your original state by logging out.')
                ->method('loginAs')
                ->canSee($this->user->exists && $this->user->id !== \request()->user()->id),

            // Button::make(__('Remove'))
            //    ->icon('bs.trash3')
            //    ->confirm(__('Once the account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.'))
            //    ->method('remove')
            //    ->canSee($this->user->exists),

            Button::make('Save')
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    /**
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::block(UserEditLayout::class)
                ->title('Profile Information')
                ->description('Basic user information for local user account.'),

            Layout::block(UserPasswordLayout::class)
                ->title('Password Settings')
                ->description('Set initial password and password reset requirements for the local user.'),

            Layout::block(UserApprovalLayout::class)
                ->title('User Approval')
                ->description('Control whether this user is approved to access the system.'),

            Layout::block(UserRoleLayout::class)
                ->title('Orchid Roles')
                ->description('Orchid platform roles for admin panel access. The employeetype role is automatically added, additional roles can be assigned manually.'),
        ];
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(User $user, Request $request)
    {
        // Prevent admin from deactivating themselves
        $currentUserId = $request->user()->id;
        if ($user->exists && $user->id === $currentUserId) {
            $currentApproval = $user->approval;
            $newApproval = $request->boolean('user.approval', $currentApproval);

            if ($currentApproval && ! $newApproval) {
                Toast::error('You cannot deactivate your own account while logged in.');

                return redirect()->back()->withInput();
            }
        }

        // Basic validation
        $validationRules = [
            'user.email' => [
                'required',
                'email',
                Rule::unique(User::class, 'email')->ignore($user),
            ],
            'user.name' => ['required', 'string', 'max:255'],
            'user.employeetype' => ['required', 'string'],
        ];

        // Username validation only for new users (since it's disabled for existing users)
        if (! $user->exists) {
            $validationRules['user.username'] = [
                'required',
                'string',
                'max:255',
                Rule::unique(User::class, 'username'),
            ];
        }

        // Password is required for new local users
        if (! $user->exists) {
            $validationRules['user.password'] = ['required', 'string', 'min:8'];
        }

        $request->validate($validationRules);

        // Prepare user data
        $userData = $request->collect('user')->except(['password', 'permissions', 'roles'])->toArray();

        // Force auth_type to 'local' for all users created in this screen
        $userData['auth_type'] = 'local';

        // Handle approval status - default to true for new users if not explicitly set
        if (! $user->exists) {
            $userData['approval'] = $request->boolean('user.approval', true);
        } else {
            // For existing users, use the form value
            $userData['approval'] = $request->boolean('user.approval', $user->approval);
        }

        // Handle password for local users
        if ($request->filled('user.password')) {
            $userData['password'] = Hash::make($request->input('user.password'));
        }

        // Ensure required fields have default values for new users
        if (! $user->exists) {
            $userData['publicKey'] = $userData['publicKey'] ?? '';
            $userData['bio'] = $userData['bio'] ?? null;
            $userData['avatar_id'] = $userData['avatar_id'] ?? null;
            $userData['isRemoved'] = false;
        }

        // For new local users, set reset_pw based on checkbox (default true)
        if (! $user->exists) {
            $userData['reset_pw'] = $request->boolean('user.reset_pw', true);
        } elseif ($user->exists) {
            // For existing users, preserve the reset_pw value from form
            $userData['reset_pw'] = $request->boolean('user.reset_pw', false);
        }

        // Store original approval status to detect changes
        $originalApproval = $user->exists ? $user->approval : true;

        $user
            ->fill($userData)
            ->save();

        // Handle role assignments - always sync roles (even if empty to remove all)
        $user->roles()->sync($request->input('user.roles', []));

        // Handle approval status changes after role sync
        if ($user->exists && $originalApproval !== $user->approval) {
            if (! $user->approval) {
                // If approval was deactivated, remove all roles
                $user->roles()->detach();
            } else {
                // If approval was activated, ensure required role is present
                $this->ensureRequiredRole($user);
            }
        } elseif ($user->approval && ! empty($user->employeetype)) {
            // For normal saves, ensure required role is present if user is approved
            $this->ensureRequiredRole($user);
        }

        Toast::info('User was saved.');

        return redirect()->route('platform.systems.users');
    }

    /**
     * Ensure the user has the required role based on their employeetype
     */
    private function ensureRequiredRole(User $user): void
    {
        if (empty($user->employeetype) || ! $user->approval) {
            return;
        }

        // Map employeetype to role slug (same logic as Observer)
        $requiredRoleSlug = $this->mapEmployeeTypeToRoleSlug($user->employeetype);
        if (! $requiredRoleSlug) {
            return;
        }

        // Find the corresponding role
        $requiredRole = \App\Models\Role::where('slug', $requiredRoleSlug)->first();
        if (! $requiredRole) {
            return;
        }

        // Add the required role if not already present
        if (! $user->roles()->where('roles.id', $requiredRole->id)->exists()) {
            $user->roles()->attach($requiredRole->id);
        }
    }

    /**
     * Map employee type to role slug using EmployeetypeMappingService
     */
    private function mapEmployeeTypeToRoleSlug(string $employeeType): string
    {
        try {
            $authMethod = config('auth.authentication_method', 'LDAP');
            return $this->employeetypeMappingService->mapEmployeetypeToRole($employeeType, $authMethod);
        } catch (\Exception $e) {
            return 'guest';
        }
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Exception
     */
    public function remove(User $user)
    {
        $user->delete();

        Toast::info(__('User was removed'));

        return redirect()->route('platform.systems.users');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function loginAs(User $user)
    {
        Impersonation::loginAs($user);

        Toast::info(__('You are now impersonating this user'));

        return redirect()->route(config('platform.index'));
    }
}
