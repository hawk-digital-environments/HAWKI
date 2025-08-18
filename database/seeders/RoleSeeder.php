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
                'platform.systems.roles' => true,
                'platform.systems.users' => true,
                'platform.systems.attachment' => true,
            ],
            'selfassign' => false,
        ]);

        // Standard university roles
        Role::firstOrCreate(['slug' => 'student'], [
            'name' => 'Studierende',
            'permissions' => [],
            'selfassign' => true,
        ]);

        Role::firstOrCreate(['slug' => 'lecturer'], [
            'name' => 'Lehrende', 
            'permissions' => [],
            'selfassign' => true,
        ]);

        Role::firstOrCreate(['slug' => 'staff'], [
            'name' => 'Mitarbeiter',
            'permissions' => [],
            'selfassign' => true,
        ]);

        Role::firstOrCreate(['slug' => 'guest'], [
            'name' => 'Gast',
            'permissions' => [],
            'selfassign' => true,
        ]);

        Role::firstOrCreate(['slug' => 'mod'], [
            'name' => 'Moderator',
            'permissions' => [],
            'selfassign' => false,
        ]);

        $this->command->info('Basic roles created/updated');
    }
}

