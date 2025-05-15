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
            UserSeeder::class,
            AppSystemTextSeeder::class,
            AppLocalizedTextSeeder::class,
            AppCssSeeder::class,
            AppSystemImageSeeder::class,
            //ToDo: add ProviderSettingsSeeder::class,
            AppSystemPromptSeeder::class,
        ]);
    }
}
