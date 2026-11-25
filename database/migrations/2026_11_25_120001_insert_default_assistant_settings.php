<?php

declare(strict_types=1);

use App\Services\Assistant\Values\AssistantPromptTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    private const KEYS = ['language', 'formality', 'answer_style'];

    public function up(): void
    {
        $now = now();

        $settings = [
            'language' => [
                'options' => ['', 'en', 'de'],
                'prompt_template' => AssistantPromptTemplate::LANGUAGE,
            ],
            'formality' => [
                'options' => ['', 'casual', 'balanced', 'professional', 'academic'],
                'prompt_template' => AssistantPromptTemplate::FORMALITY,
            ],
            'answer_style' => [
                'options' => ['', 'concise', 'balanced', 'detailed'],
                'prompt_template' => AssistantPromptTemplate::ANSWER_STYLE,
            ],
        ];

        foreach ($settings as $key => $setting) {
            DB::table('assistant_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'label' => "assistants.settings.{$key}.label",
                    'description' => "assistants.settings.{$key}.description",
                    'ui_type' => 'select',
                    'ui_options' => json_encode(array_map(
                        static fn (string $value): array => [
                            'value' => $value,
                            'label' => "assistants.settings.{$key}.options." . ('' === $value ? 'not_set' : $value),
                        ],
                        $setting['options'],
                    )),
                    'prompt_template' => $setting['prompt_template'],
                    'default_value' => json_encode(''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('assistant_settings')->whereIn('key', self::KEYS)->delete();
    }
};
