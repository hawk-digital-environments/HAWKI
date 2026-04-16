<?php

declare(strict_types=1);

namespace App\Orchid\Screens\User;

use App\Models\User;
use App\Orchid\Layouts\User\UserApprovalLayout;
use App\Orchid\Layouts\User\UserEditLayout;
use App\Orchid\Layouts\User\UserPasswordLayout;
use App\Orchid\Layouts\User\UserRoleLayout;
use App\Orchid\Layouts\User\SystemUserAvatarLayout;
use App\Orchid\Layouts\User\UserWebAuthnLayout;
use App\Services\EmployeetypeMappingService;
use App\Services\Storage\AvatarStorageService;
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
        $isSystemUser = $this->user->exists && $this->user->id === 1;

        return [
            Button::make('Impersonate user')
                ->icon('bg.box-arrow-in-right')
                ->confirm('You can revert to your original state by logging out.')
                ->method('loginAs')
                ->canSee($this->user->exists && $this->user->id !== \request()->user()->id && !$isSystemUser),

            // Remove button - hidden for system user (ID=1)
            // Button::make(__('Remove'))
            //    ->icon('bs.trash3')
            //    ->confirm(__('Once the account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.'))
            //    ->method('remove')
            //    ->canSee($this->user->exists && !$isSystemUser),

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
        $isSystemUser = $this->user->exists && $this->user->id === 1;

        $layouts = [
            Layout::block(UserEditLayout::class)
                ->title('Profile Information')
                ->description('Basic user information for local user account.'),
        ];

        // Avatar upload only for system user
        if ($isSystemUser) {
            $layouts[] = Layout::block(SystemUserAvatarLayout::class)
                ->title('Avatar Settings')
                ->description('Upload and manage the AI assistant profile picture.');
        }

        // Only show password, approval, and role layouts for non-system users
        if (!$isSystemUser) {
            $layouts[] = Layout::block(UserPasswordLayout::class)
                ->title('Password Settings')
                ->description('Set initial password and password reset requirements for the local user.');

            $layouts[] = Layout::block(UserWebAuthnLayout::class)
                ->title('WebAuthn Settings')
                ->description('Manage the user\'s WebAuthn passkey configuration.');

            $layouts[] = Layout::block(UserApprovalLayout::class)
                ->title('User Approval')
                ->description('Control whether this user is approved to access the system.');

            $layouts[] = Layout::block(UserRoleLayout::class)
                ->title('Orchid Roles')
                ->description('Orchid platform roles for admin panel access. The employeetype role is automatically added, additional roles can be assigned manually.');
        }

        return $layouts;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(User $user, Request $request)
    {
        $isSystemUser = $user->exists && $user->id === 1;

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
        ];

        // Username validation - for system user (ID=1) it's editable, for others only on creation
        if ($isSystemUser) {
            // System user: username is editable and must be unique
            $validationRules['user.username'] = [
                'required',
                'string',
                'max:255',
                Rule::unique(User::class, 'username')->ignore($user),
            ];
        } elseif (!$user->exists) {
            // New users: username required and must be unique
            $validationRules['user.username'] = [
                'required',
                'string',
                'max:255',
                Rule::unique(User::class, 'username'),
            ];
        }

        // Employeetype validation - readonly for system user
        if (!$isSystemUser) {
            $validationRules['user.employeetype'] = ['required', 'string'];
        }

        // Password is required for new local users (but not for system user)
        if (!$user->exists && !$isSystemUser) {
            $validationRules['user.password'] = ['required', 'string', 'min:8'];
        }

        // Avatar validation for system user
        if ($isSystemUser && $request->hasFile('user.avatar_file')) {
            $validationRules['user.avatar_file'] = ['required', 'image', 'max:10240']; // 10MB max
        }

        $request->validate($validationRules);

        // Handle avatar upload for system user
        if ($isSystemUser && $request->hasFile('user.avatar_file')) {
            $this->handleAvatarUpload($user, $request->file('user.avatar_file'));
        }

        // Prepare user data
        $userData = $request->collect('user')->except(['password', 'permissions', 'roles', 'avatar'])->toArray();

        // For system user, preserve employeetype
        if ($isSystemUser) {
            unset($userData['employeetype']);
        }

        // CRITICAL: auth_type is immutable - only set for NEW users, never change existing users
        if (! $user->exists) {
            // New users created via Orchid admin panel are always local users
            $userData['auth_type'] = 'local';
        }
        // For existing users: DO NOT set auth_type - it must remain unchanged

        // Handle approval status - system user is always approved
        if ($isSystemUser) {
            $userData['approval'] = true;
        } elseif (! $user->exists) {
            // Default to true for new users if not explicitly set
            $userData['approval'] = $request->boolean('user.approval', true);
        } else {
            // For existing users, use the form value
            $userData['approval'] = $request->boolean('user.approval', $user->approval);
        }

        // Handle password for local users (not for system user)
        if ($request->filled('user.password') && !$isSystemUser) {
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
        // System user never needs password reset
        if (!$isSystemUser) {
            if (! $user->exists) {
                $userData['reset_pw'] = $request->boolean('user.reset_pw', true);
            } elseif ($user->exists) {
                // For existing users, preserve the reset_pw value from form
                $userData['reset_pw'] = $request->boolean('user.reset_pw', false);
            }
        }

        // Handle WebAuthn passkey reset
        if (!$isSystemUser && $user->exists) {
            $userData['webauthn_pk'] = $request->boolean('user.webauthn_pk', false);
        }

        // Store original approval status to detect changes
        $originalApproval = $user->exists ? $user->approval : true;

        $user
            ->fill($userData)
            ->save();

        // Handle role assignments - skip for system user
        if (!$isSystemUser) {
            // Always sync roles (even if empty to remove all)
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
        }

        Toast::info('User was saved.');

        return redirect()->route('platform.systems.users');
    }

    /**
     * Handle avatar upload for system user
     * Uses the exact same logic as ProfileService::assignAvatar()
     */
    private function handleAvatarUpload(User $user, $uploadedFile): void
    {
        try {
            \Log::info('Avatar upload attempt', [
                'user_id' => $user->id,
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_size' => $uploadedFile->getSize(),
            ]);
            
            // Use ProfileService logic (same code)
            $avatarStorage = app(AvatarStorageService::class);
            $uuid = \Illuminate\Support\Str::uuid()->toString(); // Convert to string!
            
            // Get file extension
            $extension = $uploadedFile->getClientOriginalExtension();
            if (!$extension) {
                $mime = $uploadedFile->getMimeType();
                $extension = \Illuminate\Support\Arr::last(explode('/', $mime));
            }
            
            $filename = $uuid . '.' . $extension;
            
            \Log::info('Storing avatar', ['uuid' => $uuid, 'filename' => $filename]);
            
            // Store avatar (ProfileService uses the UploadedFile directly)
            $stored = $avatarStorage->store(
                file: $uploadedFile,
                filename: $filename,
                uuid: $uuid,
                category: 'profile_avatars',
                temp: false
            );
            
            if ($stored) {
                // Delete old avatar if exists
                if (!empty($user->avatar_id) && $user->avatar_id !== $uuid) {
                    \Log::info('Deleting old avatar', ['old_avatar_id' => $user->avatar_id]);
                    $avatarStorage->delete($user->avatar_id, 'profile_avatars');
                }
                
                // Update user
                $user->update(['avatar_id' => $uuid]);
                \Log::info('Avatar updated successfully', ['new_avatar_id' => $uuid]);
                
                Toast::success('Avatar updated successfully.');
            } else {
                \Log::error('Failed to store avatar');
                Toast::error('Failed to store avatar.');
            }
            
        } catch (\Exception $e) {
            \Log::error('Avatar upload exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            Toast::error('Failed to upload avatar: ' . $e->getMessage());
        }
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
