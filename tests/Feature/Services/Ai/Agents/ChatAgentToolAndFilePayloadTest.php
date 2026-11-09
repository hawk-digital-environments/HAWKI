<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Ai\Agents;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Models\User;
use App\Services\Ai\Agents\Exceptions\InvalidToolTransferStringException;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgentFromLegacyRequestFactory;
use App\Services\Ai\Agents\Middleware\LoggingMiddleware;
use App\Services\Ai\AiService;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Io\Values\AiModelIoMethods;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\Tools\Exceptions\ToolNotFoundException;
use App\Services\Ai\Tools\Implementations\TestTool;
use App\Services\Ai\Tools\Values\ToolType;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\FileType;
use App\Services\Storage\Values\StoredFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\OpenAiProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Feature\Services\Ai\Agents\ChatAgentToolAndFilePayloadTestFixtures\CapturingTextGateway;
use Tests\TestCase;

/**
 * End-to-end coverage of the tool and file handling on the agent path a
 * regenerate or thread reply takes: the frontend payload (with the message's
 * tool snapshot and the history's attachment uuids) goes through
 * {@see ChatAgentFromLegacyRequestFactory} — the real tool resolvers, the real
 * attachment registration — and the assembled run is captured at the provider
 * boundary.
 *
 * Tools are enforced by model assignment: a tool is resolved from the
 * *current* model's attached tools, and the resolved tool set crosses the
 * provider boundary. Files are enforced softly: a model that cannot handle
 * files (or image input) receives a metadata block instead of the attachment,
 * and the run proceeds.
 */
#[CoversClass(ChatAgentFromLegacyRequestFactory::class)]
class ChatAgentToolAndFilePayloadTest extends TestCase
{
    use DatabaseTransactions;

    private const string MODEL_ID = 'regen-target-model';

    private CapturingTextGateway $gateway;

    public function testARegenerationRunWithAToolSnapshotResolvesTheToolAgainstThePickedModel(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->mockProviderInfrastructure();

        $agent = $this->app->get(AiService::class)->getAgent($this->regenPayload(
            modelId: self::MODEL_ID,
            tools: ['test_tool'],
            attachedTools: [$this->makeTool()],
        ));
        $agent->send();

        static::assertCount(1, $this->gateway->capturedSteps);
        $step = $this->gateway->capturedSteps[0];

        // The switched model runs the show, and the snapshot's tool reached the
        // provider boundary resolved — not as a raw transfer string.
        static::assertSame(self::MODEL_ID, $step['model']);
        static::assertCount(1, $step['tools']);
    }

    public function testARegenerationOnAModelWithoutTheToolAssignedIsRejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        // The picked model has no tools assigned at all — the frontend menu
        // filters this pick, and the backend rejects it.
        $this->mockProviderInfrastructure(attachedTools: []);

        try {
            $this->app->get(AiService::class)->getAgent($this->regenPayload(
                modelId: self::MODEL_ID,
                tools: ['test_tool'],
            ))->send();
            static::fail('Expected ToolNotFoundException');
        } catch (ToolNotFoundException $exception) {
            // The client-facing contract: a TOOL_UNAVAILABLE code the frontend
            // translates, never a raw 500.
            $response = $exception->render();
            static::assertSame(422, $response->status());
            static::assertSame('TOOL_UNAVAILABLE', $response->getData(true)['code']);
            static::assertStringContainsString('No tool found with name "test_tool"', $exception->getMessage());
        }
    }

    public function testAModelWithoutToolCallingRejectsAnyRequestedTool(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->mockProviderInfrastructure();

        try {
            $this->app->get(AiService::class)->getAgent($this->regenPayload(
                modelId: self::MODEL_ID,
                tools: ['test_tool'],
                attachedTools: [$this->makeTool()],
                toolCalling: false,
            ))->send();
            static::fail('Expected InvalidToolTransferStringException');
        } catch (InvalidToolTransferStringException $exception) {
            static::assertSame(422, $exception->render()->status());
        }

        // A tool-free run on the same model stays legal.
        $agent = $this->app->get(AiService::class)->getAgent($this->regenPayload(
            modelId: self::MODEL_ID,
            tools: [],
            attachedTools: [$this->makeTool()],
            toolCalling: false,
        ));
        $agent->send();
        static::assertCount(0, $this->gateway->capturedSteps[0]['tools']);
    }

    public function testAnUnknownCapabilityInTheSnapshotRendersToolUnavailable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->mockProviderInfrastructure();

        try {
            $this->app->get(AiService::class)->getAgent($this->regenPayload(
                modelId: self::MODEL_ID,
                tools: ['capability:unknown_cap:auto'],
                attachedTools: [$this->makeTool()],
            ))->send();
            static::fail('Expected InvalidToolTransferStringException');
        } catch (InvalidToolTransferStringException $exception) {
            // The client-facing contract: a 422 with a TOOL_UNAVAILABLE code,
            // never a raw 500.
            $response = $exception->render();
            static::assertSame(422, $response->status());
            static::assertSame('TOOL_UNAVAILABLE', $response->getData(true)['code']);
        }
    }

    public function testCapabilityAndByNameResolutionBothRejectOfflineServerTools(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        // The only test_tool sits on an offline MCP server.
        $offlineTool = $this->makeTool(online: false);
        $this->mockProviderInfrastructure();

        // `capability:...:auto` filters for online servers …
        try {
            $this->app->get(AiService::class)->getAgent($this->regenPayload(
                modelId: self::MODEL_ID,
                tools: ['capability:web_search:auto'],
                attachedTools: [$offlineTool],
            ))->send();
            static::fail('Expected ToolNotFoundException');
        } catch (ToolNotFoundException) {
            // pinned: the capability path enforces online status
        }

        // … and so does the by-name snapshot of the same tool.
        try {
            $this->app->get(AiService::class)->getAgent($this->regenPayload(
                modelId: self::MODEL_ID,
                tools: ['test_tool'],
                attachedTools: [$offlineTool],
            ))->send();
            static::fail('Expected ToolNotFoundException');
        } catch (ToolNotFoundException $exception) {
            static::assertStringContainsString('MCP server', $exception->getMessage());
            static::assertSame('TOOL_UNAVAILABLE', $exception->render()->getData(true)['code']);
        }
    }

    public function testThreadHistoryAttachmentsAreSkippedForAModelThatCannotHandleFiles(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->mockProviderInfrastructure();
        $this->mockFileStorageWithPlainTextFile('Notes from the attachment.');

        $agent = $this->app->get(AiService::class)->getAgent($this->regenPayload(
            modelId: self::MODEL_ID,
            tools: [],
            messages: [
                ['role' => 'system', 'content' => ['text' => 'You are a helpful assistant.']],
                ['role' => 'user', 'content' => ['text' => 'Summarize my notes', 'attachments' => ['file-uuid-1']]],
            ],
            fileUpload: false,
        ));
        $agent->send();

        $messages = $this->gateway->capturedSteps[0]['messages'];
        $prompt = $messages[array_key_last($messages)];
        static::assertInstanceOf(UserMessage::class, $prompt);

        // The file did not reach the model as an attachment — the run proceeds
        // with a metadata block explaining the skip instead of aborting.
        static::assertCount(0, $prompt->attachments->all());
        static::assertStringContainsString('HKI_META_SKIPPED_ATTACHMENTS', $prompt->content);
        static::assertStringContainsString('Attachments not allowed for this model', $prompt->content);
        static::assertStringContainsString('Summarize my notes', $prompt->content);
    }

    public function testAMissingAttachmentUuidBecomesAnErrorBlockInsteadOfAborting(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->mockProviderInfrastructure();
        $this->mockFileStorage(fn (): ?StoredFile => null);

        $agent = $this->app->get(AiService::class)->getAgent($this->regenPayload(
            modelId: self::MODEL_ID,
            tools: [],
            messages: [
                ['role' => 'system', 'content' => ['text' => 'You are a helpful assistant.']],
                ['role' => 'user', 'content' => ['text' => 'Summarize my notes', 'attachments' => ['gone-uuid']]],
            ],
        ));
        $agent->send();

        $messages = $this->gateway->capturedSteps[0]['messages'];
        $prompt = $messages[array_key_last($messages)];
        static::assertInstanceOf(UserMessage::class, $prompt);
        static::assertStringContainsString('HKI_META_ATTACHMENTS_WITH_ERRORS', $prompt->content);
        static::assertStringContainsString('Summarize my notes', $prompt->content);
    }

    public function testAnImageAttachmentOnAModelWithoutImageInputIsSkipped(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->mockProviderInfrastructure();
        $this->mockFileStorageWithFile(FileType::IMAGE);

        $agent = $this->app->get(AiService::class)->getAgent($this->regenPayload(
            modelId: self::MODEL_ID,
            tools: [],
            messages: [
                ['role' => 'system', 'content' => ['text' => 'You are a helpful assistant.']],
                ['role' => 'user', 'content' => ['text' => 'Describe the picture', 'attachments' => ['image-uuid-1']]],
            ],
            // File upload is allowed, but the model has text input only.
            fileUpload: true,
            input: ['text'],
        ));
        $agent->send();

        $messages = $this->gateway->capturedSteps[0]['messages'];
        $prompt = $messages[array_key_last($messages)];
        static::assertInstanceOf(UserMessage::class, $prompt);
        static::assertCount(0, $prompt->attachments->all());
        static::assertStringContainsString('HKI_META_SKIPPED_ATTACHMENTS', $prompt->content);
        static::assertStringContainsString('does not support image input', $prompt->content);
    }

    // =========================================================================
    // Payload / fixtures
    // =========================================================================

    /**
     * Reduced copy of a real frontend regenerate payload: the message's tool
     * snapshot rides `payload.tools`, thread history messages may carry
     * attachment uuids, and `isUpdate`/`messageId` mark the in-place update.
     *
     * @param array<int, array<string, mixed>>|null $messages
     * @param list<AiTool&MockObject>|null $attachedTools
     */
    private function regenPayload(
        string $modelId,
        array  $tools,
        ?array $messages = null,
        array  $attachedTools = null,
        bool   $fileUpload = true,
        array  $input = ['text'],
        bool   $toolCalling = true,
    ): array {
        // The model mock resolves per model id; the tool attachment and the
        // file-related settings are configured before the agent is built.
        $this->nextModel = [
            'tools' => $attachedTools,
            'fileUpload' => $fileUpload,
            'input' => $input,
            'toolCalling' => $toolCalling,
        ];

        return [
            'broadcast' => false,
            'isUpdate' => true,
            'messageId' => '2.001',
            'slug' => '',
            'payload' => [
                'model' => $modelId,
                'broadcast' => false,
                'stream' => true,
                'messages' => $messages ?? [
                    ['role' => 'system', 'content' => ['text' => 'You are a helpful assistant.']],
                    ['role' => 'user', 'content' => ['text' => 'Summarize the attached tool run']],
                    ['role' => 'assistant', 'content' => ['text' => 'The tool reported: all good.']],
                    ['role' => 'user', 'content' => ['text' => 'Again please']],
                ],
                'tools' => $tools,
                'params' => ['temp' => 0.2],
            ],
        ];
    }

    /** @var array{tools: array|null, fileUpload: bool, input: list<string>, toolCalling: bool} */
    private array $nextModel = ['tools' => null, 'fileUpload' => true, 'input' => ['text'], 'toolCalling' => true];

    /**
     * Replaces the model lookup and provider resolution with in-memory stubs
     * and installs the capturing gateway on a real provider driver, so the
     * full agent pipeline runs without touching the database provider config
     * or the network.
     *
     * @param list<AiTool&MockObject>|null $attachedTools
     */
    private function mockProviderInfrastructure(?array $attachedTools = null): void
    {
        if ($attachedTools !== null) {
            $this->nextModel['tools'] = $attachedTools;
        }

        $events = $this->app->make(\Illuminate\Contracts\Events\Dispatcher::class);

        $driver = new OpenAiProvider(
            new OpenAiGateway($events),
            ['name' => 'fake-openai', 'driver' => 'openai', 'key' => 'test-key'],
            $events,
        );
        $this->gateway = new CapturingTextGateway(['Fake reply.']);
        $driver->useTextGateway($this->gateway);

        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('getAdditionalDriverOptions')->willReturn([]);

        $proxy = new AiProviderProxy(
            provider: $this->createMock(\App\Models\Ai\AiProvider::class),
            adapter: $adapter,
            driver: $driver,
        );

        $this->mock(AiProviderProxyResolver::class, static function ($mock) use ($proxy): void {
            $mock->shouldReceive('resolveForModel')->andReturn($proxy);
        });

        $this->mock(AiModelRepository::class, fn ($mock) => $mock->shouldReceive('findOneOrFail')
            ->andReturnUsing(fn (string $modelId): AiModel => $this->makeModel($modelId)));

        // LoggingMiddleware resolves logger/auth through its service locator, whose
        // container fallback is auto-disabled under PHPUnit — re-enable it so the
        // booted test container provides those services.
        $this->app->instance(
            LoggingMiddleware::class,
            (new LoggingMiddleware())->useServiceContainerFallback(true)
        );
    }

    private function makeModel(string $modelId): AiModel
    {
        $flags = $this->createMock(AiModelFlags::class);
        $flags->method('hasFeatureSamplingParameters')->willReturn(true);

        $tools = $this->nextModel['tools'] ?? [];
        $input = $this->nextModel['input'];
        // Missing keys read as false (`AiModelSettings::canUseTools`/`canHandleFiles`
        // default to false), so false values may be omitted or passed explicitly.
        $settings = [
            'tool_calling' => $this->nextModel['toolCalling'],
            'file_upload' => $this->nextModel['fileUpload'],
        ];

        $model = $this->createMock(AiModel::class);
        // AiModel exposes cast properties via Eloquent's magic __get → getAttribute.
        $model->method('__get')->willReturnCallback(
            static fn (string $key): mixed => match ($key) {
                'flags' => $flags,
                'model_id' => $modelId,
                'settings' => AiModelSettings::fromArray($settings),
                'parameters' => \App\Services\Ai\Models\Parameters\Values\AiModelParameters::fromArray([]),
                'input' => AiModelIoMethods::fromArray($input),
                'native_capabilities' => NativeAiModelCapabilities::fromArray([]),
                'tools' => new Collection($tools ?? []),
                default => null,
            }
        );

        return $model;
    }

    /**
     * A function-type AiTool bound to the TestTool implementation, optionally
     * on an offline MCP server (capability resolution rejects those).
     */
    private function makeTool(bool $online = true): AiTool&MockObject
    {
        $server = $this->createMock(McpServer::class);
        $server->method('__get')->willReturnCallback(
            static fn (string $key): mixed => match ($key) {
                'status' => $online ? OnlineStatus::ONLINE : OnlineStatus::OFFLINE,
                default => null,
            }
        );

        $tool = $this->createMock(AiTool::class);
        $tool->method('getEffectiveCapability')->willReturn('web_search');
        $tool->method('__get')->willReturnCallback(
            static fn (string $key): mixed => match ($key) {
                'name' => 'test_tool',
                'type' => ToolType::FUNCTION,
                'class_name' => TestTool::class,
                'capability' => 'web_search',
                'mapped_capability' => null,
                'active' => true,
                'mcp_config' => null,
                'server' => $server,
                default => null,
            }
        );

        return $tool;
    }

    /**
     * @param callable(): ?StoredFile $factory
     */
    private function mockFileStorage(callable $factory): void
    {
        $this->mock(FileStorageService::class, fn ($mock) => $mock->shouldReceive('retrieve')
            ->andReturnUsing(static fn (): ?StoredFile => $factory()));
    }

    private function mockFileStorageWithPlainTextFile(string $content): void
    {
        $this->mockFileStorage(fn (): StoredFile => $this->makeStoredFile(FileType::PLAIN_TEXT, $content));
    }

    private function mockFileStorageWithFile(FileType $type): void
    {
        $this->mockFileStorage(fn (): StoredFile => $this->makeStoredFile($type, 'binary'));
    }

    private function makeStoredFile(FileType $type, string $content): StoredFile
    {
        $file = $this->createMock(StoredFile::class);
        $file->method('getFileType')->willReturn($type);
        $file->method('getContent')->willReturn($content);
        $file->method('getMimeType')->willReturn('text/plain');
        $file->method('getOriginalFilename')->willReturn('notes.txt');
        $file->method('getSize')->willReturn(strlen($content));
        return $file;
    }
}
