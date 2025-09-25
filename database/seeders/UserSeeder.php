<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Orchid\Platform\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // System user "AI" - ID=1 (for HAWKI system service)
        $systemUser = User::updateOrCreate([
            'username' => 'HAWKI',
        ], [
            'email' => 'HAWKI@hawk.de',
            'name' => 'HAWKI',
            'employeetype' => 'AI',
            'auth_type' => 'local',              // System user, marked as local
            'publicKey' => '0',
            'avatar_id' => 'hawkiAvatar.jpg',
            'password' => null, // System user has no login password
        ]);

        // Admin user for new installations - check by username first
        $adminUser = User::where('username', 'admin')->first();

        if (! $adminUser) {
            // Create new admin user only if no admin user exists
            $adminUser = User::create([
                'email' => 'admin@hawk.de',
                'name' => 'admin',
                'username' => 'admin',
                'employeetype' => 'admin',
                'auth_type' => 'local',              // Important: Local authentication
                'password' => Hash::make('password'),
                'publicKey' => '',
            ]);
            $this->command->info("Admin user 'admin' created (ID: {$adminUser->id})");
        } else {
            $this->command->info("Admin user 'admin' already exists (ID: {$adminUser->id})");
        }

        // Assign admin role (if available)
        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole && ! $adminUser->roles->contains($adminRole->id)) {
            $adminUser->roles()->attach($adminRole);
            $this->command->info("Admin role assigned to user 'admin'");
        } elseif ($adminRole) {
            $this->command->info("Admin role already assigned to user 'admin'");
        } else {
            $this->command->warn('⚠️ Admin role not found - run RoleSeeder first!');
        }

        $this->command->info("System user 'AI' created/updated (ID: {$systemUser->id})");
    }
}
