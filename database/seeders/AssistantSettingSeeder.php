<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Assistant\Values\AssistantPromptTemplate;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Seeder;

class AssistantSettingSeeder extends Seeder
{
    public function run(ConnectionInterface $connection): void
    {
        $now = now();

        $connection->table('assistant_settings')->updateOrInsert(
            ['key' => 'language'],
            [
                'label' => 'assistants.settings.language.label',
                'description' => 'assistants.settings.language.description',
                'ui_type' => 'select',
                'ui_options' => json_encode([
                    ['value' => '', 'label' => 'assistants.settings.language.options.not_set'],
                    ['value' => 'en', 'label' => 'assistants.settings.language.options.en'],
                    ['value' => 'de', 'label' => 'assistants.settings.language.options.de'],
                ]),
                'prompt_template' => AssistantPromptTemplate::LANGUAGE,
                'default_value' => json_encode(''),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $connection->table('assistant_settings')->updateOrInsert(
            ['key' => 'formality'],
            [
                'label' => 'assistants.settings.formality.label',
                'description' => 'assistants.settings.formality.description',
                'ui_type' => 'select',
                'ui_options' => json_encode([
                    ['value' => '', 'label' => 'assistants.settings.formality.options.not_set'],
                    ['value' => 'casual', 'label' => 'assistants.settings.formality.options.casual'],
                    ['value' => 'balanced', 'label' => 'assistants.settings.formality.options.balanced'],
                    ['value' => 'professional', 'label' => 'assistants.settings.formality.options.professional'],
                    ['value' => 'academic', 'label' => 'assistants.settings.formality.options.academic'],
                ]),
                'prompt_template' => AssistantPromptTemplate::FORMALITY,
                'default_value' => json_encode('balanced'),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $connection->table('assistant_settings')->updateOrInsert(
            ['key' => 'answer_length'],
            [
                'label' => 'assistants.settings.answer_length.label',
                'description' => 'assistants.settings.answer_length.description',
                'ui_type' => 'select',
                'ui_options' => json_encode([
                    ['value' => '', 'label' => 'assistants.settings.answer_length.options.not_set'],
                    ['value' => 'concise', 'label' => 'assistants.settings.answer_length.options.concise'],
                    ['value' => 'balanced', 'label' => 'assistants.settings.answer_length.options.balanced'],
                    ['value' => 'detailed', 'label' => 'assistants.settings.answer_length.options.detailed'],
                ]),
                'prompt_template' => AssistantPromptTemplate::ANSWER_LENGTH,
                'default_value' => json_encode('balanced'),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }
}
