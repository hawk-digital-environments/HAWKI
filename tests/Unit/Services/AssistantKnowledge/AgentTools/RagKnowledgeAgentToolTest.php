<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AssistantKnowledge\AgentTools;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAttachment;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Providers\AssistantKnowledgeServiceProvider;
use App\Services\Assistant\AgentToolRegistry;
use App\Services\AssistantKnowledge\AgentTools\RagKnowledgeAgentTool;
use App\Services\Rag\Config\RagConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RagKnowledgeAgentTool::class)]
#[CoversClass(AssistantKnowledgeServiceProvider::class)]
class RagKnowledgeAgentToolTest extends TestCase
{
    public function testItConstructs(): void
    {
        config(['rag.enabled' => true, 'rag.dataset_prefix' => 'assistant_']);

        static::assertInstanceOf(RagKnowledgeAgentTool::class, $this->sut());
    }

    public function testItServesTheKnowledgeBaseCapabilityKey(): void
    {
        static::assertSame(WellKnownCapabilities::KNOWLEDGE_BASE, $this->sut()->key());
    }

    public function testItIsAvailableWhenRetrievalIsEnabledAndTheAssistantCarriesKnowledgeFiles(): void
    {
        config(['rag.enabled' => true, 'rag.dataset_prefix' => 'assistant_']);

        static::assertTrue($this->sut()->isAvailable($this->assistant(attachments: 1)));
        static::assertFalse($this->sut()->isAvailable($this->assistant(attachments: 0)));
    }

    public function testItIsUnavailableWhenTheModuleIsDisabled(): void
    {
        config(['rag.enabled' => false, 'rag.dataset_prefix' => 'assistant_']);

        static::assertFalse($this->sut()->isAvailable($this->assistant(attachments: 1)));
    }

    public function testItsTransferStringAddressesTheHawkiToolByNameAndCarriesTheAssistantDataset(): void
    {
        config(['rag.enabled' => true, 'rag.dataset_prefix' => 'assistant_']);

        static::assertSame(
            ['hawki-rag-query-search:{"dataset_id":"assistant_42"}'],
            $this->sut()->toolTransferStrings($this->assistant(attachments: 1)),
        );
    }

    public function testItsTransferStringFollowsAConfiguredToolName(): void
    {
        config(['rag.enabled' => true, 'rag.dataset_prefix' => 'assistant_', 'rag.query_tool' => 'custom-kb-search']);

        static::assertSame(
            ['custom-kb-search:{"dataset_id":"assistant_42"}'],
            $this->sut()->toolTransferStrings($this->assistant(attachments: 1)),
        );
    }

    public function testItRespectsAConfiguredDatasetPrefix(): void
    {
        config(['rag.enabled' => true, 'rag.dataset_prefix' => 'kb-']);

        static::assertSame(
            ['hawki-rag-query-search:{"dataset_id":"kb-42"}'],
            $this->sut()->toolTransferStrings($this->assistant(attachments: 1)),
        );
    }

    public function testTheProviderDeclaresItForTheKnowledgeBaseCapability(): void
    {
        $tool = app(AgentToolRegistry::class)->get(WellKnownCapabilities::KNOWLEDGE_BASE);

        static::assertInstanceOf(RagKnowledgeAgentTool::class, $tool);
    }

    public function testItsUsageInstructionsReferenceTheFileKnowledgeToolByName(): void
    {
        config(['rag.enabled' => true, 'rag.dataset_prefix' => 'assistant_']);

        $instructions = $this->sut()->usageInstructions($this->assistant(attachments: 1));

        static::assertStringContainsString('[KNOWLEDGE TOOL MODULE]', $instructions);
        static::assertStringContainsString('MUST always call the hawki-rag-query-search tool before answering', $instructions);
        static::assertStringContainsString('knowledge_tool: hawki-rag-query-search', $instructions);
        static::assertStringContainsString('at most 5 times per answer', $instructions);
        static::assertStringContainsString('### Re-Search Rule', $instructions);
        static::assertStringContainsString('### No-Evidence Rule', $instructions);
        static::assertStringContainsString('Copy the citeId character-for-character', $instructions);
        static::assertStringNotContainsString('{{tool_name}}', $instructions);
    }

    private function sut(): RagKnowledgeAgentTool
    {
        return new RagKnowledgeAgentTool(
            RagConfig::fromArray([
                'enabled' => (bool) config('rag.enabled'),
                'datasetPrefix' => (string) config('rag.dataset_prefix'),
                'queryToolName' => (string) config('rag.query_tool', 'hawki-rag-query-search'),
            ]),
        );
    }

    private function assistant(int $attachments, string $model = 'gpt-4.1'): Assistant
    {
        $assistant = new Assistant;
        $assistant->forceFill(['id' => 42, 'model' => $model]);

        return $assistant->setRelation(
            'assistantAttachments',
            collect(array_fill(0, $attachments, new AssistantAttachment)),
        );
    }
}
