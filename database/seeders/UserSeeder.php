<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // System user "AI" - ID=1 (for HAWKI system service)
        $systemUser = User::firstOrCreate([
            'email' => 'HAWKI@hawk.de',
        ], [
            'name' => 'AI',
            'username' => 'HAWKI',
            'employeetype' => 'AI',
            'auth_type' => 'local',              // System user, marked as local
            'publicKey' => '0',
            'avatar_id' => 'hawkiAvatar.jpg',
            'password' => null, // System user has no login password
        ]);

        // Admin user for new installations
        $adminUser = User::firstOrCreate([
            'email' => 'admin@hawk.de',
        ], [
            'name' => 'admin',
            'username' => 'admin',
            'employeetype' => 'admin',
            'auth_type' => 'local',              // Important: Local authentication
            'password' => Hash::make('password'),
            'publicKey' => '',
        ]);

        // Assign admin role (if available)
        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole && !$adminUser->roles->contains($adminRole->id)) {
            $adminUser->roles()->attach($adminRole);
        }

        $this->command->info("System user 'AI' created/updated (ID: {$systemUser->id})");
        $this->command->info("Admin user 'admin' created/updated (ID: {$adminUser->id})");
        
        if ($adminRole) {
            $this->command->info("Admin role assigned");
        } else {
            $this->command->warn("⚠️ Admin role not found - run RoleSeeder first!");
        }
    }
}
