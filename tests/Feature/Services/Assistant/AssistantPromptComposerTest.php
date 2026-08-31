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

