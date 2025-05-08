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
            UserSeeder::class,
            AppSettingsSeeder::class,
            AppLocalizedTextSeeder::class,
            AppSystemTextSeeder::class,
            AppCssSeeder::class,
            //ToDo: add ProviderSettingsSeeder::class,
            AppSystemPromptSeeder::class,
        ]);
    }
}
