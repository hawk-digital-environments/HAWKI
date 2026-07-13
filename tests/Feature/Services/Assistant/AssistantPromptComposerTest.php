<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Models\User;
use App\Services\Assistant\AssistantPromptComposer;
use App\Services\Assistant\Values\AssistantPromptTemplate;
use App\Services\Chat\Attachment\Repositories\AttachmentRepository;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\FileReference;
use App\Services\Storage\Values\StoredFileCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AssistantPromptComposer::class)]
class AssistantPromptComposerTest extends TestCase
{
    use RefreshDatabase;

    public function testEmptySettingValueAddsNoPrompt(): void
    {
        // max_tokens 0: no token budget, so only the setting modules can apply.
        $assistant = Assistant::factory()->create(['system_prompt' => 'BASE PROMPT', 'max_tokens' => 0]);
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
        // max_tokens 0: this test asserts only the formality fragment.
        $assistant = Assistant::factory()->create(['system_prompt' => 'BASE PROMPT', 'max_tokens' => 0]);
        $setting = $this->formatSetting();

        $assistant->settingValues()->create([
            'setting_id' => $setting->id,
            'value' => 'casual',
        ]);

        $prompt = app(AssistantPromptComposer::class)->compose($assistant);

        self::assertSame("BASE PROMPT\n\nFORMALITY MODULE\nlevel: casual", $prompt);
    }

    /**
     * Two knowledge files (one markdown, one plain text) attached to the
     * assistant must be rendered as a [KNOWLEDGE BASE MODULE] fragment at
     * the end of the system prompt, with each file's content prefixed by a
     * "Source: {filename}" line.
     *
     * The full expected prompt is asserted byte-for-byte so that any change
     * to the AssistantPromptTemplate::KNOWLEDGE_FILES module forces a
     * conscious test update.
     */
    public function testAttachmentsAreInjectedAsKnowledgeBaseFragment(): void
    {
        // FileStorageService writes to the "file_storage" disk (local_file_storage
        // by default), not the "default" disk, so that is the one we must fake
        // for an hermetic Test.
        Storage::fake(config('filesystems.file_storage', 'local_file_storage'));

        $user = User::factory()->create();
        $assistant = Assistant::factory()->create([
            'system_prompt' => 'BASE PROMPT',
            'creator_id' => $user->id,
            // No token budget: this test asserts only the knowledge-base fragment.
            'max_tokens' => 0,
        ]);

        $storage = app(FileStorageService::class);
        $repository = app(AttachmentRepository::class);

        // .md files: content is forwarded as-is (PlainTextLanguageType::MARKDOWN).
        $markdownFile = $storage->store(
            FileReference::fromContent('report.md', 'Revenue grew 12% in Q4.'),
            StoredFileCategory::ASSISTANT,
        );

        // .txt files: content is wrapped in a fenced code block by
        // StoredFileExtract::getContent() because the language type is TEXT,
        // not MARKDOWN.
        $textFile = $storage->store(
            FileReference::fromContent('notes.txt', 'Meeting notes from 2025-03-14.'),
            StoredFileCategory::ASSISTANT,
        );

        $repository->assignToAssistant($assistant, $markdownFile, $user);
        $repository->assignToAssistant($assistant, $textFile, $user);

        // The creator passes the viewAttachments gate, so knowledge files are
        // injected into the composed prompt.
        $prompt = app(AssistantPromptComposer::class)->compose($assistant, $user);

        // Compose the expected prompt by replicating the composer's substitution:
        // the system prompt, followed by the KNOWLEDGE_FILES module with the
        // {{content}} placeholder replaced by the per-file "Source: ..." blocks.
        //
        // This intentionally references AssistantPromptTemplate::KNOWLEDGE_FILES
        // so any edit to the template forces a conscious update to this test.
        $expectedContent = "Source: report.md\nRevenue grew 12% in Q4."
            . "\n\n"
            . "Source: notes.txt\n```text\nMeeting notes from 2025-03-14.\n```";

        $expected = 'BASE PROMPT'
            . "\n\n"
            . str_replace('{{content}}', $expectedContent, AssistantPromptTemplate::KNOWLEDGE_FILES);

        self::assertSame($expected, $prompt);
    }

    /**
     * A selected answer style plus a token budget must compose the
     * [OUTPUT STYLE CONTROL MODULE] followed by the combined
     * [OUTPUT LENGTH CONTROL MODULE] (token budget with style cooperation).
     *
     * The expected prompt is asserted byte-for-byte so that any change to the
     * AssistantPromptTemplate modules forces a conscious test update.
     */
    public function testAnswerStyleAndMaxTokensComposeBothModules(): void
    {
        $assistant = Assistant::factory()->create([
            'system_prompt' => 'BASE PROMPT',
            'max_tokens' => 1000,
        ]);
        $setting = $this->answerStyleSetting();

        $assistant->settingValues()->create([
            'setting_id' => $setting->id,
            'value' => 'concise',
        ]);

        $prompt = app(AssistantPromptComposer::class)->compose($assistant);

        $expected = 'BASE PROMPT'
            . "\n\n"
            . str_replace('{{value}}', 'concise', AssistantPromptTemplate::ANSWER_STYLE)
            . "\n\n"
            . AssistantPromptTemplate::answerLength(1000, 'concise');

        self::assertSame($expected, $prompt);
    }

    /**
     * A token budget without a selected style composes only the length
     * module, without the style input and cooperation section.
     */
    public function testMaxTokensWithoutStyleComposesLengthModuleOnly(): void
    {
        $assistant = Assistant::factory()->create([
            'system_prompt' => 'BASE PROMPT',
            'max_tokens' => 1000,
        ]);

        $prompt = app(AssistantPromptComposer::class)->compose($assistant);

        $expected = 'BASE PROMPT'
            . "\n\n"
            . AssistantPromptTemplate::answerLength(1000);

        self::assertSame($expected, $prompt);
        self::assertStringNotContainsString('### Style Cooperation', $prompt);
    }

    /**
     * A style-only configuration (max_tokens unset) composes just the style
     * module — the length module is skipped entirely.
     */
    public function testStyleWithoutMaxTokensComposesStyleModuleOnly(): void
    {
        $assistant = Assistant::factory()->create([
            'system_prompt' => 'BASE PROMPT',
            'max_tokens' => 0,
        ]);
        $setting = $this->answerStyleSetting();

        $assistant->settingValues()->create([
            'setting_id' => $setting->id,
            'value' => 'detailed',
        ]);

        $prompt = app(AssistantPromptComposer::class)->compose($assistant);

        $expected = 'BASE PROMPT'
            . "\n\n"
            . str_replace('{{value}}', 'detailed', AssistantPromptTemplate::ANSWER_STYLE);

        self::assertSame($expected, $prompt);
    }

    /**
     * An empty ("not set") style value must not append the style module, and
     * with no token budget either the prompt stays untouched — the "not set"
     * intent is to not augment the system prompt.
     */
    public function testEmptyStyleValueAddsNoModule(): void
    {
        $assistant = Assistant::factory()->create([
            'system_prompt' => 'BASE PROMPT',
            'max_tokens' => 0,
        ]);
        $setting = $this->answerStyleSetting();

        $assistant->settingValues()->create([
            'setting_id' => $setting->id,
            'value' => '',
        ]);

        self::assertSame('BASE PROMPT', app(AssistantPromptComposer::class)->compose($assistant));
    }

    private function answerStyleSetting(): AssistantSetting
    {
        return AssistantSetting::factory()->create([
            'key' => 'answer_style',
            'prompt_template' => AssistantPromptTemplate::ANSWER_STYLE,
            'default_value' => '',
            'ui_options' => [
                ['value' => '', 'label' => 'Not set'],
                ['value' => 'concise', 'label' => 'Concise'],
                ['value' => 'balanced', 'label' => 'Balanced'],
                ['value' => 'detailed', 'label' => 'Detailed'],
            ],
        ]);
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

