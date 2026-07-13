<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Services\Assistant\AssistantPromptComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AssistantPromptComposer::class)]
class AssistantPromptComposerTest extends TestCase
{
    use RefreshDatabase;

    public function testEmptySettingValueAddsNoPrompt(): void
    {
        $assistant = Assistant::factory()->create(['system_prompt' => 'BASE PROMPT']);
        $setting = $this->formatSetting();

        $assistant->settingValues()->create([
            'setting_id' => $setting->id,
            'value' => '',
        ]);

        $prompt = app(AssistantPromptComposer::class)->compose($assistant);

        self::assertStringContainsString('BASE PROMPT', $prompt);
    }

    public function testNonEmptySettingValueAddsPromptFragment(): void
    {
        $assistant = Assistant::factory()->create(['system_prompt' => 'BASE PROMPT']);
        $setting = $this->formatSetting();

        $assistant->settingValues()->create([
            'setting_id' => $setting->id,
            'value' => 'casual',
        ]);

        $prompt = app(AssistantPromptComposer::class)->compose($assistant);

        self::assertSame("BASE PROMPT\n\nFORMALITY MODULE\nlevel: casual", $prompt);
    }

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
}
