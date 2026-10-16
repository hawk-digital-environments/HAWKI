<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant;

use App\Models\Ai\AiTool;
use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAttachment;
use App\Models\User;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Assistant\AgentToolRegistry;
use App\Services\Assistant\AssistantPromptComposer;
use App\Services\Assistant\AssistantRunComposer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use Tests\Unit\Services\Assistant\AssistantRunComposerTest\AssistantRunComposerTestFixtures\StubAgentTool;

#[CoversClass(AssistantRunComposer::class)]
class AssistantRunComposerTest extends TestCase
{
    public function testItConstructs(): void
    {
        static::assertInstanceOf(
            AssistantRunComposer::class,
            new AssistantRunComposer(
                $this->createMock(AssistantPromptComposer::class),
                new AgentToolRegistry($this->app),
                new NullLogger(),
            ),
        );
    }

    public function testActiveAgentToolInjectsItsTransferStringsAndSupersedesExplicitAttachments(): void
    {
        $run = $this->composerWithKnowledgeAgentTool(
            available: true,
            transferStrings: ['hawki-rag-query-search:{"dataset_id":"assistant_12"}'],
        )->compose($this->assistantWithTools([
            $this->tool('hawki-rag-query-search', WellKnownCapabilities::KNOWLEDGE_BASE),
            $this->tool('hawki-rag-web-search-tool', WellKnownCapabilities::WEB_SEARCH),
            $this->tool('some-uncategorized-tool', null),
        ]));

        static::assertSame(
            [
                'hawki-rag-web-search-tool',
                'some-uncategorized-tool',
                'hawki-rag-query-search:{"dataset_id":"assistant_12"}',
            ],
            $run->toolTransferStrings,
        );
    }

    public function testAnAttachedKnowledgeToolWithADifferentNameCoexistsWithTheAmbientGrant(): void
    {
        // Another knowledge_base tool the creator attached: not superseded —
        // its usage is up to the model and its own description.
        $run = $this->composerWithKnowledgeAgentTool(
            available: true,
            transferStrings: ['hawki-rag-query-search:{"dataset_id":"assistant_12"}'],
        )->compose($this->assistantWithTools([
            $this->tool('external-kb-search', WellKnownCapabilities::KNOWLEDGE_BASE),
        ]));

        static::assertSame(
            [
                'external-kb-search',
                'hawki-rag-query-search:{"dataset_id":"assistant_12"}',
            ],
            $run->toolTransferStrings,
        );
    }

    public function testASameNameAttachmentIsSupersededAndLoggedAsAConfigurationOverlap(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Attached ai_tool is superseded by an active agent tool with the same name — the ambient grant wins; rename the attached tool or remove the attachment',
            static::callback(static fn (array $context): bool => $context['tool_name'] === 'hawki-rag-query-search'),
        );

        $run = $this->composerWithKnowledgeAgentTool(
            available: true,
            transferStrings: ['hawki-rag-query-search:{"dataset_id":"assistant_12"}'],
            logger: $logger,
        )->compose($this->assistantWithTools([
            $this->tool('hawki-rag-query-search', WellKnownCapabilities::KNOWLEDGE_BASE),
        ]));

        static::assertSame(
            ['hawki-rag-query-search:{"dataset_id":"assistant_12"}'],
            $run->toolTransferStrings,
        );
    }

    public function testActiveAgentToolLeavesPersistedCapabilityStringsUntouched(): void
    {
        $assistant = $this->assistantWithTools([]);
        $assistant->capabilities = [
            'capability:knowledge_base:auto',
            'capability:web_search:native',
        ];

        $run = $this->composerWithKnowledgeAgentTool(
            available: true,
            transferStrings: ['hawki-rag-query-search:{"dataset_id":"assistant_12"}'],
        )->compose($assistant);

        static::assertSame(
            [
                'capability:knowledge_base:auto',
                'capability:web_search:native',
                'hawki-rag-query-search:{"dataset_id":"assistant_12"}',
            ],
            $run->toolTransferStrings,
        );
    }

    public function testUnavailableAgentToolLeavesExplicitSelectionsUntouched(): void
    {
        $assistant = $this->assistantWithTools([
            $this->tool('hawki-rag-query-search', WellKnownCapabilities::KNOWLEDGE_BASE),
        ]);
        $assistant->capabilities = ['capability:knowledge_base:auto'];

        $run = $this->composerWithKnowledgeAgentTool(
            available: false,
            transferStrings: ['hawki-rag-query-search:{"dataset_id":"assistant_12"}'],
        )->compose($assistant);

        static::assertSame(
            [
                'capability:knowledge_base:auto',
                'hawki-rag-query-search',
            ],
            $run->toolTransferStrings,
        );
    }

    public function testPromptComposerLearnsWhetherKnowledgeIsHandledByAnAgentTool(): void
    {
        $flags = [];

        $promptComposer = $this->createMock(AssistantPromptComposer::class);
        $promptComposer->method('compose')->willReturnCallback(
            static function (Assistant $assistant, ?User $actor = null, bool $knowledgeHandledByAgentTool = false) use (&$flags): string {
                $flags[] = $knowledgeHandledByAgentTool;

                return 'composed system prompt';
            },
        );

        $stub = new StubAgentTool();
        $composer = new AssistantRunComposer($promptComposer, $this->registry($stub), new NullLogger());

        $stub->available = true;
        $composer->compose($this->assistantWithTools([]));

        $stub->available = false;
        $composer->compose($this->assistantWithTools([]));

        static::assertSame([true, false], $flags);
    }

    public function testActiveAgentToolUsageInstructionsAreForwardedToThePromptComposer(): void
    {
        $instructionsSeen = [];

        $promptComposer = $this->createMock(AssistantPromptComposer::class);
        $promptComposer->method('compose')->willReturnCallback(
            static function (Assistant $assistant, ?User $actor = null, bool $knowledgeHandledByAgentTool = false, array $agentToolInstructions = []) use (&$instructionsSeen): string {
                $instructionsSeen[] = $agentToolInstructions;

                return 'composed system prompt';
            },
        );

        $stub = new StubAgentTool();
        $stub->available = true;
        $stub->usageInstructions = '[KNOWLEDGE TOOL MODULE]' . "\n\n" . 'Search the knowledge tool FIRST.';
        $composer = new AssistantRunComposer($promptComposer, $this->registry($stub), new NullLogger());

        $composer->compose($this->assistantWithTools([]));

        $stub->usageInstructions = null;
        $composer->compose($this->assistantWithTools([]));

        static::assertSame(
            [
                ['[KNOWLEDGE TOOL MODULE]' . "\n\n" . 'Search the knowledge tool FIRST.'],
                [],
            ],
            $instructionsSeen,
        );
    }

    public function testNonStringCapabilityEntriesPassThrough(): void
    {
        $assistant = $this->assistantWithTools([]);
        $assistant->capabilities = [42, 'capability:web_search:native'];

        $run = $this->composerWithKnowledgeAgentTool(
            available: true,
            transferStrings: ['hawki-rag-query-search:{"dataset_id":"assistant_12"}'],
        )->compose($assistant);

        static::assertSame(
            [
                42,
                'capability:web_search:native',
                'hawki-rag-query-search:{"dataset_id":"assistant_12"}',
            ],
            $run->toolTransferStrings,
        );
    }

    private function composerWithKnowledgeAgentTool(bool $available, array $transferStrings, ?LoggerInterface $logger = null): AssistantRunComposer
    {
        $stub = new StubAgentTool();
        $stub->available = $available;
        $stub->transferStrings = $transferStrings;

        return new AssistantRunComposer($this->stubPromptComposer(), $this->registry($stub), $logger ?? new NullLogger());
    }

    private function stubPromptComposer(): AssistantPromptComposer&MockObject
    {
        $promptComposer = $this->createMock(AssistantPromptComposer::class);
        $promptComposer->method('compose')->willReturn('composed system prompt');

        return $promptComposer;
    }

    private function registry(StubAgentTool $tool): AgentToolRegistry
    {
        $this->app->forgetInstance(StubAgentTool::class);
        $this->app->instance(StubAgentTool::class, $tool);

        return (new AgentToolRegistry($this->app))
            ->declare($tool->key(), StubAgentTool::class);
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
            ->setRelation('assistantAttachments', collect([new AssistantAttachment]));
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
