<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin role with all permissions
        $adminRole = Role::firstOrCreate([
            'slug' => 'admin',
        ], [
            'name' => 'Administrator',
            'permissions' => [
                'platform.index' => true,
                'platform.access.roles' => true,
                'platform.access.users' => true,
                'platform.role-assignments' => true,
                'platform.systems.settings' => true,
                'platform.systems.models' => true,
                'platform.modelsettings.providers' => true,
                'systems.modelsettings' => true,
                'platform.modelsettings.models' => true,
                'platform.modelsettings.utilitymodels' => true,
                'platform.dashboard' => true,
                'chat.access' => true,
                'groupchat.access' => true,
            ],
            'selfassign' => false,
        ]);

        // Standard university roles
        Role::firstOrCreate(['slug' => 'student'], [
            'name' => 'Studierende',
            'permissions' => [
                'chat.access' => true,
                'groupchat.access' => true,
            ],
            'selfassign' => true,
        ]);

        Role::firstOrCreate(['slug' => 'lecturer'], [
            'name' => 'Lehrende', 
            'permissions' => [
                'chat.access' => true,
                'groupchat.access' => true,
            ],
            'selfassign' => true,
        ]);

        Role::firstOrCreate(['slug' => 'staff'], [
            'name' => 'Mitarbeiter',
            'permissions' => [
                'chat.access' => true,
                'groupchat.access' => true,
            ],
            'selfassign' => true,
        ]);

        Role::firstOrCreate(['slug' => 'guest'], [
            'name' => 'Gast',
            'permissions' => [
                'chat.access' => true,
                'groupchat.access' => false, // Guests can't access group chats
            ],
            'selfassign' => true,
        ]);

        Role::firstOrCreate(['slug' => 'mod'], [
            'name' => 'Moderator',
            'permissions' => [
                'platform.index' => true, // Moderators also get admin panel access
                'platform.access.users' => true, // Can manage users
                'chat.access' => true,
                'groupchat.access' => true,
            ],
            'selfassign' => false,
        ]);

        $this->command->info('Basic roles created/updated');
    }
}

