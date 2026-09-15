<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant;

use App\Models\Ai\AiTool;
use App\Models\Assistants\Assistant;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Assistant\AssistantPromptComposer;
use App\Services\Assistant\AssistantRunComposer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(AssistantRunComposer::class)]
class AssistantRunComposerTest extends TestCase
{
    public function testKnowledgeBaseToolCarriesTheAssistantDatasetSetting(): void
    {
        config(['rag.enabled' => true, 'rag.dataset_prefix' => 'assistant_']);

        $run = $this->composer()->compose($this->assistantWithTools([
            $this->tool('hawki-rag-query-search', WellKnownCapabilities::KNOWLEDGE_BASE),
            $this->tool('hawki-rag-web-search-tool', WellKnownCapabilities::WEB_SEARCH),
            $this->tool('some-uncategorized-tool', null),
        ]));

        static::assertSame(
            [
                'hawki-rag-query-search:{"dataset_id":"assistant_12"}',
                'hawki-rag-web-search-tool',
                'some-uncategorized-tool',
            ],
            $run->toolTransferStrings,
        );
    }

    public function testNoDatasetSettingWhenRagIsDisabled(): void
    {
        config(['rag.enabled' => false, 'rag.dataset_prefix' => 'assistant_']);

        $run = $this->composer()->compose($this->assistantWithTools([
            $this->tool('hawki-rag-query-search', WellKnownCapabilities::KNOWLEDGE_BASE),
        ]));

        static::assertSame(['hawki-rag-query-search'], $run->toolTransferStrings);
    }

    private function composer(): AssistantRunComposer
    {
        $promptComposer = $this->createMock(AssistantPromptComposer::class);
        $promptComposer->method('compose')->willReturn('composed system prompt');

        return new AssistantRunComposer(
            promptComposer: $promptComposer,
            ragEnabled: (bool) config('rag.enabled'),
            ragDatasetPrefix: (string) config('rag.dataset_prefix'),
        );
    }

    /**
     * @param list<AiTool> $tools
     */
    private function assistantWithTools(array $tools): Assistant
    {
        $assistant = new Assistant;
        $assistant->forceFill([
            'id' => 12,
            'model' => 'gpt-4.1',
            'allow_model_select' => false,
            'temp' => 0.7,
        ]);

        return $assistant
            ->setRelation('ai_tools', collect($tools))
            ->setRelation('assistantAttachments', collect());
    }

    private function tool(string $name, ?string $capability): AiTool
    {
        return new AiTool([
            'name' => $name,
            'capability' => $capability,
            'mapped_capability' => null,
            'active' => true,
        ]);
    }
}
