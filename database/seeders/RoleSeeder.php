<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Orchid\Platform\Dashboard;
use Orchid\Platform\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin role with all permissions (dynamic)
        $dashboard = app(Dashboard::class);
        $allPermissions = $dashboard->getAllowAllPermission();

        // Add application-level permissions that are not managed via PlatformProvider
        $allPermissions['chat.access'] = true;
        $allPermissions['groupchat.access'] = true;

        $adminRole = Role::firstOrCreate([
            'slug' => 'admin',
        ], [
            'name' => 'Administrator',
            'permissions' => $allPermissions,
            'selfassign' => false,
        ]);

        // Update existing admin role with all current permissions
        if (! $adminRole->wasRecentlyCreated) {
            $adminRole->update([
                'permissions' => $allPermissions,
            ]);
        }

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
