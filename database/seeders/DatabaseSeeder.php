<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Ensure you call the correct seeder class here
        $this->call([
            AppSettingsSeeder::class,
            RoleSeeder::class,           // ← Create roles first
            UserSeeder::class,           // ← Then users (can reference roles)
            AppSystemTextSeeder::class,
            AppLocalizedTextSeeder::class,
            AppCssSeeder::class,
            AppSystemImageSeeder::class,
            ProviderSettingsSeeder::class,
            AppSystemPromptSeeder::class,
        ]);
    }
}
