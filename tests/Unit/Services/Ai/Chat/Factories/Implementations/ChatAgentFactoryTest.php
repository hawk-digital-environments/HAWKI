<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Chat\Factories\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Ai\AiTool;
use App\Services\Ai\Agents\Implementations\Chat\ChatToolResolver;
use App\Services\Ai\AiService;
use App\Services\Ai\Chat\Factories\Implementations\ChatAgentFactory;
use App\Services\Ai\Chat\Tools\ClientTool;
use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Chat\Values\Messages\ToolMessage;
use App\Services\Ai\Chat\Values\Tools\ToolDefinition;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\Tools\LaravelAi\LaravelToolResolver;
use App\Services\Storage\FileStorageService;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Tools\ToolNameResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(ChatAgentFactory::class)]
class ChatAgentFactoryTest extends TestCase
{
    private AiModelRepository&MockObject $modelRepository;
    private ChatToolResolver&MockObject $chatToolResolver;
    private LaravelToolResolver&MockObject $laravelToolResolver;
    private AiProviderProxyResolver&MockObject $providerProxyResolver;
    private LoggerInterface&Stub $logger;
    private ChatAgentFactory $sut;
    private AiModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelRepository = $this->createMock(AiModelRepository::class);
        $this->chatToolResolver = $this->createMock(ChatToolResolver::class);
        $this->laravelToolResolver = $this->createMock(LaravelToolResolver::class);
        $this->providerProxyResolver = $this->createMock(AiProviderProxyResolver::class);
        $this->logger = self::createStub(LoggerInterface::class);

        $this->model = new AiModel(['model_id' => 'gpt-4o']);
        $this->model->setRelation('parameters', collect());

        $this->modelRepository->method('findOneOrFail')->willReturn($this->model);
        $this->providerProxyResolver->method('resolveForModel')->willReturn(new AiProviderProxy(
            provider: new AiProvider(['provider_id' => 'openAi', 'name' => 'OpenAI']),
            adapter: self::createStub(ProviderAdapterInterface::class),
            driver: self::createStub(Provider::class),
        ));

        $this->sut = new ChatAgentFactory(
            fileStorageService: self::createStub(FileStorageService::class),
            modelRepository: $this->modelRepository,
            aiService: self::createStub(AiService::class),
            chatToolResolver: $this->chatToolResolver,
            logger: $this->logger,
        );
        $this->sut->setToolResolver($this->laravelToolResolver);
        $this->sut->setProviderProxyResolver($this->providerProxyResolver);
        $this->sut->setUsageContext($this->app->make(\App\Services\System\UsageTypes\UsageContext::class));
    }

    public function testItConstructs(): void
    {
        self::assertInstanceOf(ChatAgentFactory::class, $this->sut);
    }

    public function testItHandsClientDeclaredToolsWithoutServerImplementationToTheClient(): void
    {
        $this->model->setRelation('tools', collect());

        $agent = $this->sut->createAgent($this->request(tools: [
            new ToolDefinition(name: 'read_file', description: 'Reads a local file.', parameters: [
                'type' => 'object',
                'properties' => ['path' => ['type' => 'string']],
            ]),
        ]));

        $tools = iterator_to_array($agent->tools());

        self::assertCount(1, $tools);
        self::assertInstanceOf(ClientTool::class, $tools[0]);
        self::assertSame('read_file', ToolNameResolver::resolve($tools[0]));
    }

    public function testItInterceptsToolsWithAServerSideImplementation(): void
    {
        $this->model->setRelation('tools', collect([
            new AiTool(['name' => 'weather', 'type' => 'function']),
        ]));
        $serverTool = self::createStub(\Laravel\Ai\Contracts\Tool::class);
        $this->laravelToolResolver->expects($this->once())
            ->method('resolveToolByName')
            ->with('weather')
            ->willReturn($serverTool);

        $agent = $this->sut->createAgent($this->request(tools: [
            new ToolDefinition(name: 'weather', description: 'Weather lookup.'),
        ]));

        $tools = iterator_to_array($agent->tools());

        self::assertCount(1, $tools);
        self::assertSame($serverTool, $tools[0]);
    }

    public function testItDeduplicatesTransferStringToolsAgainstDeclaredTools(): void
    {
        $this->model->setRelation('tools', collect([
            new AiTool(['name' => 'web_search', 'type' => 'function']),
        ]));
        $serverTool = new class() implements \Laravel\Ai\Contracts\Tool {
            public function name(): string
            {
                return 'web_search';
            }

            public function description(): string
            {
                return 'Searches the web.';
            }

            public function handle(\Laravel\Ai\Tools\Request $request): string
            {
                return '';
            }

            public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
            {
                return [];
            }
        };
        $this->chatToolResolver->method('findTools')->willReturn([$serverTool]);

        $agent = $this->sut->createAgent($this->request(
            hawkiTools: ['capability:web_search:native'],
            tools: [new ToolDefinition(name: 'web_search', description: 'Searches the web.')],
        ));

        $tools = iterator_to_array($agent->tools());

        self::assertCount(1, $tools);
    }

    public function testItContinuesAfterClientToolResultsWithSyntheticPrompt(): void
    {
        $this->model->setRelation('tools', collect());

        $request = new AiRequest(
            model: 'gpt-4o',
            messages: [
                \App\Services\Ai\Chat\Values\Messages\UserMessage::fromText('List the files'),
                new \App\Services\Ai\Chat\Values\Messages\AssistantMessage(parts: [
                    new \App\Services\Ai\Chat\Values\Parts\ToolCallPart(
                        toolCallId: 'call_1',
                        toolName: 'read_file',
                        toolInput: ['path' => '/tmp'],
                    ),
                ]),
                new ToolMessage(parts: [
                    new \App\Services\Ai\Chat\Values\Parts\ToolResultPart(
                        toolCallId: 'call_1',
                        result: 'file-a',
                    ),
                ]),
            ],
        );

        $agent = $this->sut->createAgent($request);

        $messages = iterator_to_array($agent->messages());

        self::assertInstanceOf(\Laravel\Ai\Messages\ToolResultMessage::class, $messages[array_key_last($messages)]);
        self::assertSame('call_1', $messages[array_key_last($messages)]->toolResults->first()->resultId);
        self::assertStringContainsString('executed by the client and are final', $this->promptString($agent));

        $assistantWithCall = $messages[1];
        self::assertInstanceOf(\Laravel\Ai\Messages\AssistantMessage::class, $assistantWithCall);
        self::assertSame('call_1', $assistantWithCall->toolCalls->first()->resultId);
    }


    public function testItMergesPrecedingReasoningIntoToolCallReplay(): void
    {
        $request = new AiRequest(
            model: 'gpt-4o',
            messages: [
                \App\Services\Ai\Chat\Values\Messages\UserMessage::fromText('Read the file'),
                new \App\Services\Ai\Chat\Values\Messages\AssistantMessage(parts: [
                    new \App\Services\Ai\Chat\Values\Parts\ReasoningPart(
                        reasoning: 'I should read the file.',
                        encryptedContent: 'enc-state-1',
                        providerMetadata: ['item_id' => 'rs_abc'],
                    ),
                ]),
                new \App\Services\Ai\Chat\Values\Messages\AssistantMessage(parts: [
                    new \App\Services\Ai\Chat\Values\Parts\ToolCallPart(
                        toolCallId: 'call_1',
                        toolName: 'read_file',
                        toolInput: ['path' => '/tmp'],
                    ),
                ]),
                new ToolMessage(parts: [
                    new \App\Services\Ai\Chat\Values\Parts\ToolResultPart(toolCallId: 'call_1', result: 'file-a'),
                ]),
                \App\Services\Ai\Chat\Values\Messages\UserMessage::fromText('Thanks'),
            ],
        );

        $messages = iterator_to_array($this->sut->createAgent($request)->messages());

        // The reasoning-only turn must not surface as a placeholder assistant message.
        $assistantMessages = array_values(array_filter(
            $messages,
            static fn (mixed $message): bool => $message instanceof \Laravel\Ai\Messages\AssistantMessage,
        ));
        self::assertCount(1, $assistantMessages);

        $toolCall = $assistantMessages[0]->toolCalls->first();
        self::assertSame('rs_abc', $toolCall->reasoningId);
        self::assertSame('enc-state-1', $toolCall->reasoningEncryptedContent);
        self::assertSame([['type' => 'summary_text', 'text' => 'I should read the file.']], $toolCall->reasoningSummary);
    }

    public function testItMergesSameMessageReasoningIntoToolCalls(): void
    {
        $request = new AiRequest(
            model: 'deepseek-chat',
            messages: [
                \App\Services\Ai\Chat\Values\Messages\UserMessage::fromText('Read the file'),
                new \App\Services\Ai\Chat\Values\Messages\AssistantMessage(parts: [
                    new \App\Services\Ai\Chat\Values\Parts\ReasoningPart(reasoning: 'Thinking...', encryptedContent: 'enc-2'),
                    new \App\Services\Ai\Chat\Values\Parts\ToolCallPart(
                        toolCallId: 'call_2',
                        toolName: 'read_file',
                        toolInput: [],
                    ),
                ]),
                new ToolMessage(parts: [
                    new \App\Services\Ai\Chat\Values\Parts\ToolResultPart(toolCallId: 'call_2', result: 'ok'),
                ]),
            ],
        );

        $messages = iterator_to_array($this->sut->createAgent($request)->messages());

        $assistantMessages = array_values(array_filter(
            $messages,
            static fn (mixed $message): bool => $message instanceof \Laravel\Ai\Messages\AssistantMessage,
        ));
        self::assertCount(1, $assistantMessages);

        $toolCall = $assistantMessages[0]->toolCalls->first();
        self::assertSame('enc-2', $toolCall->reasoningEncryptedContent);
        self::assertMatchesRegularExpression('/^rs_/', (string) $toolCall->reasoningId);
    }

    public function testItDropsReasoningOnlyTurnsWithoutToolCalls(): void
    {
        $request = new AiRequest(
            model: 'gpt-4o',
            messages: [
                \App\Services\Ai\Chat\Values\Messages\UserMessage::fromText('Hi'),
                new \App\Services\Ai\Chat\Values\Messages\AssistantMessage(parts: [
                    new \App\Services\Ai\Chat\Values\Parts\ReasoningPart(reasoning: 'pondering'),
                ]),
                new \App\Services\Ai\Chat\Values\Messages\AssistantMessage(parts: [
                    \App\Services\Ai\Chat\Values\Parts\TextPart::from('Hello'),
                ]),
                \App\Services\Ai\Chat\Values\Messages\UserMessage::fromText('And now?'),
            ],
        );

        $messages = iterator_to_array($this->sut->createAgent($request)->messages());

        // Text assistant turns surface as plain role-typed messages, not AssistantMessage.
        $assistantMessages = array_values(array_filter(
            $messages,
            static fn (mixed $message): bool => $message instanceof \Laravel\Ai\Messages\Message
                && \Laravel\Ai\Messages\MessageRole::Assistant === $message->role,
        ));
        self::assertCount(1, $assistantMessages);
        self::assertSame('Hello', $assistantMessages[0]->content);
    }

    public function testItPassesJsonSchemaResponseFormatsToTheAgent(): void
    {
        $request = $this->request();
        $request = new AiRequest(
            model: 'gpt-4o',
            messages: [\App\Services\Ai\Chat\Values\Messages\UserMessage::fromText('Hi')],
            responseFormat: new \App\Services\Ai\Chat\Values\Configs\ResponseFormatConfig(
                type: \App\Services\Ai\Chat\Values\Configs\ResponseFormatType::JSON_SCHEMA,
                jsonSchema: [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'age' => ['type' => 'integer'],
                    ],
                    'required' => ['name'],
                ],
            ),
        );

        $agent = $this->sut->createAgent($request);

        self::assertInstanceOf(\App\Services\Ai\Agents\Implementations\Chat\StructuredChatAgent::class, $agent);
        self::assertInstanceOf(\Laravel\Ai\Contracts\HasStructuredOutput::class, $agent);
        self::assertSame(['name', 'age'], array_keys($agent->schema(new \Illuminate\JsonSchema\JsonSchemaTypeFactory())));
    }

    public function testItEmitsAJsonObjectInstructionSuffix(): void
    {
        $request = new AiRequest(
            model: 'gpt-4o',
            messages: [\App\Services\Ai\Chat\Values\Messages\UserMessage::fromText('Hi')],
            responseFormat: new \App\Services\Ai\Chat\Values\Configs\ResponseFormatConfig(
                type: \App\Services\Ai\Chat\Values\Configs\ResponseFormatType::JSON_OBJECT,
            ),
        );

        $agent = $this->sut->createAgent($request);

        self::assertNotInstanceOf(\Laravel\Ai\Contracts\HasStructuredOutput::class, $agent);
        self::assertStringContainsString('single JSON object', $agent->instructions());
    }

    /**
     * @param array<int, \App\Services\Ai\Chat\Values\Tools\ToolDefinition> $tools
     */
    private function request(array $tools = [], ?array $hawkiTools = null): AiRequest
    {
        return new AiRequest(
            model: 'gpt-4o',
            messages: [\App\Services\Ai\Chat\Values\Messages\UserMessage::fromText('Hi')],
            tools: [] !== $tools ? $tools : null,
            hawkiExtensions: null !== $hawkiTools ? [AiRequest::HAWKI_EXTENSION_TOOLS => $hawkiTools] : null,
        );
    }

    private function promptString(\App\Services\Ai\Agents\Implementations\Chat\ChatAgent $agent): string
    {
        $reflection = new \ReflectionProperty(\App\Services\Ai\Agents\Adapters\AbstractTextGeneratingAgent::class, 'promptString');

        return $reflection->getValue($agent);
    }
}
