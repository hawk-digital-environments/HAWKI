<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ensure you call the correct seeder class here
        $this->call([
            AppSettingsSeeder::class,
            ApiFormatsSeeder::class,     // ← Add API formats first
            RoleSeeder::class,           // ← Create roles first
            UserSeeder::class,           // ← Then users (can reference roles)
            AppSystemTextSeeder::class,
            AppLocalizedTextSeeder::class,
            AppCssSeeder::class,
            AppSystemImageSeeder::class,
            ApiProvidersSeeder::class,   // ← Using the more complete seeder with base_url support
            AiAssistantPromptSeeder::class,
            AiAssistantSeeder::class,    // ← AI Assistants after prompts
            MailTemplateSeeder::class,   // ← Mail templates after core system setup
        ]);
    }
}
