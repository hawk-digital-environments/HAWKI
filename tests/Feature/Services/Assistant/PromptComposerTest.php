<?php

namespace Tests\Feature\Services\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Services\Assistant\PromptComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptComposerTest extends TestCase
{
    use RefreshDatabase;

    private function formatSetting(): AssistantSetting
    {
        return AssistantSetting::factory()->create([
            'key' => 'formality',
            'prompt_template' => "FORMALITY MODULE\nlevel: {{value}}",
            'ui_options' => [
                ['value' => '', 'label' => 'Not set'],
                ['value' => 'casual', 'label' => 'Casual'],
            ],
        ]);
    }

    public function test_empty_setting_value_adds_no_prompt(): void
    {
        $assistant = Assistant::factory()->create(['system_prompt' => 'BASE PROMPT']);
        $setting = $this->formatSetting();

        $assistant->settingValues()->create([
            'setting_id' => $setting->id,
            'value' => '',
        ]);

        $prompt = app(PromptComposer::class)->compose($assistant);

        $this->assertSame('BASE PROMPT', $prompt);
    }

    public function test_non_empty_setting_value_adds_prompt_fragment(): void
    {
        $assistant = Assistant::factory()->create(['system_prompt' => 'BASE PROMPT']);
        $setting = $this->formatSetting();

        $assistant->settingValues()->create([
            'setting_id' => $setting->id,
            'value' => 'casual',
        ]);

        $prompt = app(PromptComposer::class)->compose($assistant);

        $this->assertSame("BASE PROMPT\n\nFORMALITY MODULE\nlevel: casual", $prompt);
    }
}
