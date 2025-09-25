<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Orchid\Platform\Models\Role;

class OverrideOrchidAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orchid:admin 
                            {--id= : The ID of an existing user to give admin role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an admin user or assign admin role to existing user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('id');

        if ($userId) {
            return $this->assignAdminRoleToUser($userId);
        }

        return $this->createNewAdminUser();
    }

    /**
     * Assign admin role to existing user
     */
    protected function assignAdminRoleToUser($userId)
    {
        $user = User::find($userId);

        if (! $user) {
            $this->error("User with ID {$userId} not found.");

            return Command::FAILURE;
        }

        $adminRole = Role::where('slug', 'admin')->first();

        if (! $adminRole) {
            $this->error('Admin role not found. Please run the RoleSeeder first.');

            return Command::FAILURE;
        }

        // Remove any existing admin permissions from user
        $user->update(['permissions' => []]);

        // Add admin role
        $user->addRole($adminRole);

        $this->info("Admin role assigned to user: {$user->name} ({$user->email})");

        return Command::SUCCESS;
    }

    /**
     * Create new admin user
     */
    protected function createNewAdminUser()
    {
        $name = $this->ask('What is your name?', 'admin');
        $email = $this->ask('What is your email?', 'admin@admin.com');
        $password = $this->secret('What is the password?');

        if (User::where('email', $email)->exists()) {
            $this->error('User already exists');

            return Command::FAILURE;
        }

        // Create the user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'auth_type' => 'local',
            'approval' => true,
            'username' => $email,
            'publicKey' => '',
            'employeetype' => 'staff', // Default to staff for admin users
        ]);

        // Find and assign the admin role
        $adminRole = Role::where('slug', 'admin')->first();

        if ($adminRole) {
            $user->addRole($adminRole);
            $this->info("Admin user created and assigned 'admin' role successfully.");
        } else {
            // Fallback: if no admin role exists, create user with all permissions
            $user->update([
                'permissions' => \Orchid\Support\Facades\Dashboard::getAllowAllPermission(),
            ]);
            $this->warn('Admin user created with all permissions (no admin role found).');
        }

        return Command::SUCCESS;
    }
}
