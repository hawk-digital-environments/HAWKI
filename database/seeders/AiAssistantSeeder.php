<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AiAssistantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hawkiUser = \App\Models\User::where('username', 'HAWKI')->first();
        if (!$hawkiUser) {
            $hawkiUser = \App\Models\User::first();
        }

        // Always use the first AI Model's system_id as default until admin changes it
        $defaultAiModel = \App\Models\AiModel::first();
        $defaultAiModelSystemId = $defaultAiModel ? $defaultAiModel->system_id : null;

        // Hardcoded mapping: Assistant Key -> Prompt Title
        // These 4 system prompt types are hardcoded and map to the AiAssistantPrompt titles
        $promptMapping = [
            'default_assistant' => 'Default Prompt',
            'title_generator' => 'Name Prompt', 
            'prompt_improver' => 'Improvement Prompt',
            'summarizer' => 'Summary Prompt'
        ];

        $assistants = [
            [
                'key' => 'default_assistant',
                'name' => 'Default Assistant',
                'description' => 'Der Standard-Assistent für allgemeine Aufgaben und Unterhaltungen',
                'status' => 'active',
                'visibility' => 'public',
                'owner_id' => $hawkiUser->id,
                'ai_model' => $defaultAiModelSystemId,
                'prompt' => $promptMapping['default_assistant'],
                'tools' => null
            ],
            [
                'key' => 'title_generator',
                'name' => 'Title Generator',
                'description' => 'Generiert aussagekräftige Titel und Überschriften für verschiedene Inhalte',
                'status' => 'active',
                'visibility' => 'public',
                'owner_id' => $hawkiUser->id,
                'ai_model' => $defaultAiModelSystemId,
                'prompt' => $promptMapping['title_generator'],
                'tools' => null
            ],
            [
                'key' => 'prompt_improver',
                'name' => 'Prompt Improver',
                'description' => 'Optimiert und verbessert bestehende Prompts für bessere AI-Interaktionen',
                'status' => 'active',
                'visibility' => 'public',
                'owner_id' => $hawkiUser->id,
                'ai_model' => $defaultAiModelSystemId,
                'prompt' => $promptMapping['prompt_improver'],
                'tools' => null
            ],
            [
                'key' => 'summarizer',
                'name' => 'Summarizer',
                'description' => 'Erstellt präzise Zusammenfassungen von Texten, Dokumenten und Inhalten',
                'status' => 'active',
                'visibility' => 'public',
                'owner_id' => $hawkiUser->id,
                'ai_model' => $defaultAiModelSystemId,
                'prompt' => $promptMapping['summarizer'],
                'tools' => null
            ]
        ];

        $created = 0;
        $updated = 0;
        
        foreach ($assistants as $assistantData) {
            $assistant = \App\Models\AiAssistant::firstOrCreate(
                ['key' => $assistantData['key']], 
                $assistantData
            );
            
            if ($assistant->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $this->command->info("AI Assistants seeded successfully: {$created} created, {$updated} updated.");
        $this->command->info('All assistants use default AI Model (first available) and are mapped to system prompt types.');
    }
}
