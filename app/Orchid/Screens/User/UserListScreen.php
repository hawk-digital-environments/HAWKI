<?php

declare(strict_types=1);

namespace App\Orchid\Screens\User;

use App\Orchid\Layouts\User\UserEditLayout;
use App\Orchid\Layouts\User\UserFiltersLayout;
use App\Orchid\Layouts\User\UserImportLayout;
use App\Orchid\Layouts\User\UserListLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Role;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class UserListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'users' => User::with('roles')
                ->filters(UserFiltersLayout::class)
                ->defaultSort('id', 'desc')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'User Management';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'A comprehensive list of all registered users, including their profiles and privileges.';
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
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [

            ModalToggle::make('Import')
                ->icon('bs.upload')
                ->modal('importUsersModal')
                ->method('importUsersFromJson'),

            Link::make(__('Add'))
                ->icon('bs.plus-circle')
                ->route('platform.systems.users.create'),
                

        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return string[]|\Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            UserFiltersLayout::class,
            UserListLayout::class,

            Layout::modal('editUserModal', UserEditLayout::class)
                ->deferred('loadUserOnOpenModal'),
                
            Layout::modal('importUsersModal', UserImportLayout::class)
                ->title('Import')
                ->applyButton('Import Users')
                ->closeButton('Cancel'),
        ];
    }

    /**
     * Loads user data when opening the modal window.
     *
     * @return array
     */
    public function loadUserOnOpenModal(User $user): iterable
    {
        return [
            'user' => $user,
        ];
    }

    public function saveUser(Request $request, User $user): void
    {
        $request->validate([
            'user.email' => [
                'required',
                Rule::unique(User::class, 'email')->ignore($user),
            ],
        ]);

        $user->fill($request->input('user'))->save();

        Toast::info(__('User was saved.'));
    }

    public function remove(Request $request): void
    {
        User::findOrFail($request->get('id'))->delete();

        Toast::info(__('User was removed'));
    }

    /**
     * Toggle the approval status of a user.
     */
    public function toggleApproval(Request $request): void
    {
        $user = User::findOrFail($request->get('id'));
        
        $newApprovalStatus = !$user->approval;
        $user->update(['approval' => $newApprovalStatus]);
        
        $statusText = $newApprovalStatus ? 'approved' : 'revoked approval for';
        Toast::info("User '{$user->name}' has been {$statusText}.");
    }

    /**
     * Import users from JSON file.
     */
    public function importUsersFromJson(Request $request): void
    {
        $request->validate([
            'json_file' => 'required|file|mimes:json|max:2048',
        ]);

        try {
            $file = $request->file('json_file');
            $jsonContent = file_get_contents($file->getPathname());
            $users = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Toast::error('Invalid JSON file format.');
                return;
            }

            if (!is_array($users)) {
                Toast::error('JSON file must contain an array of users.');
                return;
            }

            $importedCount = 0;
            $skippedCount = 0;
            $approvedCount = 0;
            $pendingApprovalCount = 0;
            $errors = [];
            
            // Track usernames and emails within this import to catch duplicates
            $seenUsernames = [];
            $seenEmails = [];

            foreach ($users as $index => $userData) {
                try {
                    // Validate required fields
                    if (!isset($userData['username']) || !isset($userData['email']) || !isset($userData['name'])) {
                        $errors[] = "User at index {$index}: Missing required fields (username, email, or name)";
                        $skippedCount++;
                        continue;
                    }

                    // Check for duplicates within the JSON file
                    if (in_array($userData['username'], $seenUsernames)) {
                        $errors[] = "User at index {$index}: Duplicate username '{$userData['username']}' in JSON file";
                        $skippedCount++;
                        continue;
                    }
                    
                    if (in_array($userData['email'], $seenEmails)) {
                        $errors[] = "User at index {$index}: Duplicate email '{$userData['email']}' in JSON file";
                        $skippedCount++;
                        continue;
                    }
                    
                    // Add to seen lists
                    $seenUsernames[] = $userData['username'];
                    $seenEmails[] = $userData['email'];

                    // Check if user already exists
                    $existingUser = User::where('username', $userData['username'])
                        ->orWhere('email', $userData['email'])
                        ->first();

                    if ($existingUser) {
                        $conflictType = '';
                        if ($existingUser->username === $userData['username']) {
                            $conflictType = "username '{$userData['username']}'";
                        } elseif ($existingUser->email === $userData['email']) {
                            $conflictType = "email '{$userData['email']}'";
                        }
                        $errors[] = "User at index {$index}: {$conflictType} already exists";
                        $skippedCount++;
                        continue;
                    }

                    // Check if employeetype maps to an existing role
                    $employeetype = $userData['employeetype'] ?? null;
                    $hasValidRole = false;
                    
                    if ($employeetype) {
                        $roleSlug = $this->mapEmployeeTypeToRoleSlug($employeetype);
                        if ($roleSlug) {
                            $role = Role::where('slug', $roleSlug)->first();
                            $hasValidRole = (bool) $role;
                        }
                    }

                    // Create new user
                    $newUser = User::create([
                        'name' => $userData['name'],
                        'email' => $userData['email'],
                        'username' => $userData['username'],
                        'password' => isset($userData['password']) ? Hash::make($userData['password']) : Hash::make('password'),
                        'employeetype' => $employeetype,
                        'avatar_id' => $userData['avatar_id'] ?? null,
                        'auth_type' => 'local',
                        'reset_pw' => true,
                        'approval' => $hasValidRole, // Only approve if employeetype maps to valid role
                        'publicKey' => '',
                        'bio' => null,
                        'isRemoved' => false,
                        'permissions' => $userData['permissions'] ?? [],
                    ]);

                    $importedCount++;
                    if ($hasValidRole) {
                        $approvedCount++;
                    } else {
                        $pendingApprovalCount++;
                    }

                } catch (\Exception $e) {
                    $errors[] = "User at index {$index}: " . $e->getMessage();
                    $skippedCount++;
                }
            }

            // Display results
            $message = "Import completed: {$importedCount} users imported";
            if ($approvedCount > 0) {
                $message .= " ({$approvedCount} approved)";
            }
            if ($pendingApprovalCount > 0) {
                $message .= " ({$pendingApprovalCount} pending approval)";
            }
            if ($skippedCount > 0) {
                $message .= ", {$skippedCount} users skipped";
            }
            
            if ($importedCount > 0) {
                Toast::info($message);
            } else {
                Toast::warning($message);
            }

            // Display errors if any
            if (!empty($errors)) {
                $errorMessage = "Errors during import:\n" . implode("\n", array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $errorMessage .= "\n... and " . (count($errors) - 5) . " more errors";
                }
                Toast::error($errorMessage);
            }

        } catch (\Exception $e) {
            Toast::error('Error processing JSON file: ' . $e->getMessage());
        }
    }

    /**
     * Map employeetype values to Orchid role slugs dynamically
     * Same logic as in UserObserver to ensure consistency
     */
    private function mapEmployeeTypeToRoleSlug(string $employeetype): ?string
    {
        $employeetype = trim($employeetype);
        
        // First try exact slug match (case-insensitive)
        $role = Role::whereRaw('LOWER(slug) = ?', [strtolower($employeetype)])->first();
        if ($role) {
            return $role->slug;
        }
        
        // Then try exact name match (case-insensitive)
        $role = Role::whereRaw('LOWER(name) = ?', [strtolower($employeetype)])->first();
        if ($role) {
            return $role->slug;
        }
        
        // No match found
        return null;
    }
}
