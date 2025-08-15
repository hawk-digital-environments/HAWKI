<?php

declare(strict_types=1);

namespace App\Orchid\Screens\User;

use App\Orchid\Layouts\User\UserEditLayout;
use App\Orchid\Layouts\User\UserPasswordLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Orchid\Access\Impersonation;
use App\Models\User;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class UserEditScreen extends Screen
{
    /**
     * @var User
     */
    public $user;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(User $user): iterable
    {
        $user->load(['roles']);

        return [
            'user'       => $user,
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
            'platform.systems.users',
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

            //Button::make(__('Remove'))
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
                ->description('Basic user information for local user account.')
                ->commands(
                    Button::make('Save')
                        ->type(Color::BASIC)
                        ->icon('bs.check-circle')
                        ->canSee($this->user->exists)
                        ->method('save')
                ),

            Layout::block(UserPasswordLayout::class)
                ->title('Password Settings')
                ->description('Set initial password and password reset requirements for the local user.')
                ->commands(
                    Button::make('Save')
                        ->type(Color::BASIC)
                        ->icon('bs.check-circle')
                        ->canSee($this->user->exists)
                        ->method('save')
                ),
        ];
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(User $user, Request $request)
    {
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
        if (!$user->exists) {
            $validationRules['user.username'] = [
                'required',
                'string',
                'max:255',
                Rule::unique(User::class, 'username'),
            ];
        }

        // Password is required for new local users
        if (!$user->exists) {
            $validationRules['user.password'] = ['required', 'string', 'min:8'];
        }

        $request->validate($validationRules);

        // Prepare user data
        $userData = $request->collect('user')->except(['password', 'permissions', 'roles'])->toArray();
        
        // Force auth_type to 'local' for all users created in this screen
        $userData['auth_type'] = 'local';
        
        // Handle password for local users
        if ($request->filled('user.password')) {
            $userData['password'] = Hash::make($request->input('user.password'));
        }
        
        // Ensure required fields have default values for new users
        if (!$user->exists) {
            $userData['publicKey'] = $userData['publicKey'] ?? '';
            $userData['bio'] = $userData['bio'] ?? null;
            $userData['avatar_id'] = $userData['avatar_id'] ?? null;
            $userData['isRemoved'] = false;
        }
        
        // For new local users, set reset_pw based on checkbox (default true)
        if (!$user->exists) {
            $userData['reset_pw'] = $request->boolean('user.reset_pw', true);
        } elseif ($user->exists) {
            // For existing users, preserve the reset_pw value from form
            $userData['reset_pw'] = $request->boolean('user.reset_pw', false);
        }

        $user
            ->fill($userData)
            ->save();

        Toast::info('User was saved.');

        return redirect()->route('platform.systems.users');
    }

    /**
     * @throws \Exception
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove(User $user)
    {
        $user->delete();

        Toast::info('User was removed');

        return redirect()->route('platform.systems.users');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function loginAs(User $user)
    {
        Impersonation::loginAs($user);

        Toast::info('You are now impersonating this user');

        return redirect()->route(config('platform.index'));
    }
}
