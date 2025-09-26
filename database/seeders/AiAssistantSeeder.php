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

        // Get AI Model with ID=1
        $defaultAiModel = \App\Models\AiModel::find(1);
        $aiModelSystemId = $defaultAiModel ? $defaultAiModel->system_id : null;

        $assistants = [
            [
                'key' => 'default_assistant',
                'name' => 'Default Assistant',
                'description' => 'Der Standard-Assistent für allgemeine Aufgaben und Unterhaltungen',
                'status' => 'active',
                'visibility' => 'public',
                'owner_id' => $hawkiUser->id,
                'ai_model' => $aiModelSystemId,
                'prompt' => 'Default_Prompt',
                'tools' => null
            ],
            [
                'key' => 'title_generator',
                'name' => 'Title Generator',
                'description' => 'Generiert aussagekräftige Titel und Überschriften für verschiedene Inhalte',
                'status' => 'active',
                'visibility' => 'public',
                'owner_id' => $hawkiUser->id,
                'ai_model' => $aiModelSystemId,
                'prompt' => 'Name_Prompt',
                'tools' => null
            ],
            [
                'key' => 'prompt_improver',
                'name' => 'Prompt Improver',
                'description' => 'Optimiert und verbessert bestehende Prompts für bessere AI-Interaktionen',
                'status' => 'active',
                'visibility' => 'public',
                'owner_id' => $hawkiUser->id,
                'ai_model' => $aiModelSystemId,
                'prompt' => 'Improvement_Prompt',
                'tools' => null
            ],
            [
                'key' => 'summarizer',
                'name' => 'Summarizer',
                'description' => 'Erstellt präzise Zusammenfassungen von Texten, Dokumenten und Inhalten',
                'status' => 'active',
                'visibility' => 'public',
                'owner_id' => $hawkiUser->id,
                'ai_model' => $aiModelSystemId,
                'prompt' => 'Summery_Prompt',
                'tools' => null
            ]
        ];

        foreach ($assistants as $assistantData) {
            \App\Models\AiAssistant::firstOrCreate(
                ['key' => $assistantData['key']], 
                $assistantData
            );
        }

        $this->command->info('AI Assistants seeded successfully.');
    }
}
