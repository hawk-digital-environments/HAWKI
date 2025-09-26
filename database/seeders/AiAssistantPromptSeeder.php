<?php

namespace Database\Seeders;

use App\Models\AiAssistantPrompt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class AiAssistantPromptSeeder extends Seeder
{
    /**
     * Seed the application's AI assistant prompts.
     */
    public function run(): void
    {
        Log::info('Running AiAssistantPrompt seeder...');

        // Array mit allen Standard-Prompts
        $systemPrompts = [
            // Default system prompt für allgemeine Konversationen
            [
                'category' => 'utility',
                'title' => 'Default Prompt',
                'description' => 'Standard-Systemprompt für allgemeine Konversationen',
                'content' => 'Du bist ein intelligentes und unterstützendes KI-Assistenzsystem für alle Hochschulangehörigen der HAWK Hildesheim/Holzminden/Göttingen. Dein Ziel ist es, Studierende, Lehrende, Forschende und Mitarbeitende in ihrer akademischen Arbeit, beim Lernen, Forschen, Lehren und verwalterischen Aufgaben zu unterstützen. Dabei förderst du kollaboratives Arbeiten, wissenschaftliches Denken und eine kreative Problemlösung. Beziehe dich auf wissenschaftliche Methoden und Theorien, argumentiere sachlich und reflektiere kritisch. Sei objektiv und verzichte auf unbegründete Meinungen. Fördere akademische Integrität und unterstütze keine Plagiate. Sei inklusiv, wertschätzend und respektiere Vielfalt.',
                'language' => 'de_DE',
                'created_by' => 1,
            ],
            [
                'category' => 'utility',
                'title' => 'Default Prompt',
                'description' => 'Standard system prompt for general conversations',
                'content' => 'You are an intelligent and supportive AI assistance system for all university members of HAWK Hildesheim/Holzminden/Göttingen. Your goal is to support students, lecturers, researchers and staff in their academic work, learning, research, teaching and administrative tasks. You will promote collaborative work, scientific thinking and creative problem solving. Refer to scientific methods and theories, argue objectively and reflect critically. Be objective and refrain from unfounded opinions. Promote academic integrity and do not support plagiarism. Be inclusive, appreciative and respect diversity.',
                'language' => 'en_US',
                'created_by' => 1,
            ],

            // Title generator prompt für die automatische Titelerstellung
            [
                'category' => 'utility',
                'title' => 'Name Prompt',
                'description' => 'Prompt für die automatische Titelerstellung',
                'content' => 'Du bist ein Assistent, der einem erhaltenen Nachrichtentext einen drei Wörter umfassenden Titel zuweist. Du antwortest nur mit dem Namen. Der Name beschreibt die Nachricht genau. Die Benennung soll auf Deutsch sein.',
                'language' => 'de_DE',
                'created_by' => 1,
            ],
            [
                'category' => 'utility',
                'title' => 'Name Prompt',
                'description' => 'Prompt for automatic title generation',
                'content' => 'You are an assistant who assigns a three-word title to the message you receive. You only respond with the name. The naming accurately describes the message. The naming should be in english.',
                'language' => 'en_US',
                'created_by' => 1,
            ],

            // Prompt improver prompt für die Optimierung von Benutzeranfragen
            [
                'category' => 'utility',
                'title' => 'Improvement Prompt',
                'description' => 'Prompt für die Optimierung von Benutzeranfragen',
                'content' => 'Du bist ein Assistent und hilfst dabei, die Eingaben der Benutzer zu verbessern, um das beste Ergebnis von den LLM-Modellen zu erzielen, sobald du eine Eingabe von den Benutzern erhältst. Bitte mache keine Erklärungen und generiere nur die beste Eingabe. Die generierte Eingabe muss in der ursprünglichen Sprache der Eingabe sein.',
                'language' => 'de_DE',
                'created_by' => 1,
            ],
            [
                'category' => 'utility',
                'title' => 'Improvement Prompt',
                'description' => 'Prompt for improving user queries',
                'content' => 'You are an assistant and as you receive a prompt from the user you help to improve the prompts to get the best result from the LLM Models. Please don\'t make any explaination and only generate the best prompt. The generated prompt must be in the original promp language.',
                'language' => 'en_US',
                'created_by' => 1,
            ],

            // Summarizer prompt für die Zusammenfassung von Inhalten
            [
                'category' => 'utility',
                'title' => 'Summary Prompt',
                'description' => 'Prompt für die Zusammenfassung von Inhalten',
                'content' => 'Du bist ein hilfreicher Assistent. Deine Antwort ist immer ein 100 Wörter umfassendes Abstract der gesamten Konversation ohne zusätzliche Erklärungen. Die Zusammenfassung muss auf Deutsch sein.',
                'language' => 'de_DE',
                'created_by' => 1,
            ],
            [
                'category' => 'utility',
                'title' => 'Summary Prompt',
                'description' => 'Prompt for content summarization',
                'content' => 'You\'re a helpful assistant. Your answer is always a 100 words abstract of the whole conversation without any addition explanation. The summery must be in english.',
                'language' => 'en_US',
                'created_by' => 1,
            ],
        ];

        // Einfügen der Prompts in die Datenbank (nur wenn sie noch nicht existieren)
        $count = 0;
        foreach ($systemPrompts as $promptData) {
            AiAssistantPrompt::firstOrCreate(
                [
                    'title' => $promptData['title'],
                    'language' => $promptData['language'],
                ],
                $promptData
            );
            $count++;
        }

        Log::info("AiAssistantPrompt seeder completed: {$count} prompts created or updated");
        
        // Only output to command if we're in a command context
        if ($this->command) {
            $this->command->info("AiAssistantPrompt seeder completed: {$count} prompts created or updated");
        }
    }
}
