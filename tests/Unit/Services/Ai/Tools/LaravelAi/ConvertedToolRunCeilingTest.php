<?php
declare(strict_types=1);


namespace Tests\Unit\Services\Ai\Tools\LaravelAi;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\Tools\Implementations\TestTool;
use App\Services\Ai\Tools\LaravelAi\LaravelMcpTool;
use App\Services\Ai\Tools\LaravelAi\LaravelToolConverter;
use App\Services\Ai\Tools\LaravelAi\LaravelToolResolver;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Values\ToolType;
use App\Services\Ai\Values\OnlineStatus;
use App\Providers\AiServiceProvider;
use App\Services\System\Container\ServiceLocator;
use App\Utils\Lists\LazySingletonList;
use Illuminate\Support\Collection;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Tools\LaravelAi\ConvertedToolRunCeilingTest\ConvertedToolRunCeilingTestFixtures\RunLimitedStubTool;

/**
 * Pins the per-tool call ceiling ("max runs") behaviour of converted HAWKI tools.
 *
 *  - a tool's own ceiling (TestTool: 1 run) rejects further calls with a
 *    model-workable [ERROR] string the loop can continue from (TOOL_CALL_BUG.md, R3);
 *  - the model's `max_tool_calling_rounds` budget must be applied to every
 *    converted tool (MCP + function), where 0 leaves the tool unlimited;
 *  - the budget may only tighten a tool's ceiling, never loosen it;
 *  - the resolver forwards the run's model budget into the converter.
 */
#[CoversClass(LaravelToolConverter::class)]
#[CoversClass(LaravelToolResolver::class)]
#[CoversClass(LaravelMcpTool::class)]
#[CoversClass(TestTool::class)]
class ConvertedToolRunCeilingTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeMcpAiTool(): AiTool
    {
        $server = new McpServer();
        $server->status = OnlineStatus::ONLINE;

        $aiTool = new AiTool();
        $aiTool->type = ToolType::MCP;
        $aiTool->name = 'knowledge_search';
        $aiTool->description = 'Searches the knowledge base.';
        $aiTool->mcp_name = 'knowledge_search';
        $aiTool->mcp_config = ['inputSchema' => ['type' => 'object']];
        $aiTool->setRelation('server', $server);

        return $aiTool;
    }

    private function makeFunctionAiTool(string $className = TestTool::class): AiTool
    {
        $aiTool = new AiTool();
        $aiTool->type = ToolType::FUNCTION;
        $aiTool->name = 'test_tool';
        $aiTool->description = 'Test tool.';
        $aiTool->class_name = $className;

        return $aiTool;
    }

    /**
     * Builds a converter whose MCP client answers every call with a canned result.
     */
    private function makeConverter(
        HawkiMcpClient&MockObject                 $client,
        (ServiceLocator&MockObject)|null          $serviceLocator = null
    ): LaravelToolConverter
    {
        $clientList = $this->createMock(LazySingletonList::class);
        $clientList->method('get')->willReturn($client);

        return new LaravelToolConverter(
            logger: $this->createMock(LoggerInterface::class),
            mcpClientList: $clientList,
            serviceLocator: $serviceLocator ?? $this->app->make(ServiceLocator::class),
        );
    }

    private function makeMcpClient(int|null $expectedCalls = null): HawkiMcpClient&MockObject
    {
        $client = $this->createMock(HawkiMcpClient::class);

        $mocker = $expectedCalls !== null
            ? $client->expects($this->exactly($expectedCalls))->method('callTool')
            : $client->method('callTool');
        $mocker->willReturn(
            new \Mcp\Types\CallToolResult(content: [new \Mcp\Types\TextContent('ok')])
        );

        return $client;
    }

    /**
     * Builds real model settings backed by the booted app's setting registry, so
     * registry defaults (5 non-streaming / 3 streaming rounds) apply.
     */
    private function makeSettingsWithRegistry(array $explicit = []): AiModelSettings
    {
        $settings = AiModelSettings::fromArray($explicit);
        $settings->setService(
            \App\Services\Ai\Models\Settings\AiModelSettingRegistry::class,
            $this->app->make(\App\Services\Ai\Models\Settings\AiModelSettingRegistry::class)
        );

        return $settings;
    }

    private function makeContextWithMcpTool(AiTool $aiTool, AiModelSettings $settings): AgentRequestContext
    {
        $model = $this->createMock(AiModel::class);
        $model->method('__get')->willReturnCallback(
            fn(string $key) => match ($key) {
                'settings' => $settings,
                'tools' => new Collection([$aiTool]),
                default => null,
            }
        );

        return new AgentRequestContext(
            provider: new AiProviderProxy(
                provider: $this->createMock(\App\Models\Ai\AiProvider::class),
                adapter: $this->createMock(ProviderAdapterInterface::class),
                driver: $this->createMock(\Laravel\Ai\Providers\Provider::class),
            ),
            model: $model,
            modelParameters: new AiModelParameters(),
        );
    }

    // =========================================================================
    // Tests — a tool's own ceiling
    // =========================================================================

    public function testItRejectsTestToolCallsBeyondItsOwnRunCeiling(): void
    {
        $sut = new TestTool($this->createMock(LoggerInterface::class));

        $first = (string)$sut->handle(new Request(['message' => 'hi', 'count' => 1]));

        static::assertStringNotContainsString('[ERROR]', $first);

        $second = (string)$sut->handle(new Request(['message' => 'hi', 'count' => 1]));

        static::assertStringContainsString('[ERROR]', $second);
        static::assertStringContainsString('maximum number of runs (1)', $second);
    }

    // =========================================================================
    // Tests — the model budget applied at conversion time
    // =========================================================================

    public function testItAppliesTheModelRunCeilingToConvertedMcpTools(): void
    {
        $converter = $this->makeConverter($this->makeMcpClient());

        $converted = $converter->convert($this->makeMcpAiTool(), [], 3);
        static::assertInstanceOf(LaravelMcpTool::class, $converted);

        for ($i = 1; $i <= 3; $i++) {
            static::assertStringNotContainsString(
                '[ERROR]',
                (string)$converted->handle(new Request([])),
                "Call {$i} must still execute within the ceiling of 3."
            );
        }

        $fourth = (string)$converted->handle(new Request([]));

        static::assertStringContainsString('[ERROR]', $fourth);
        static::assertStringContainsString('maximum number of runs (3)', $fourth);
    }

    public function testItKeepsConvertedToolsUnlimitedWhenNoModelCeilingIsSet(): void
    {
        $client = $this->makeMcpClient(expectedCalls: 5);
        $converter = $this->makeConverter($client);

        $converted = $converter->convert($this->makeMcpAiTool(), [], 0);

        for ($i = 1; $i <= 5; $i++) {
            static::assertStringNotContainsString('[ERROR]', (string)$converted->handle(new Request([])));
        }
    }

    public function testItKeepsAToolsOwnCeilingWhenTheModelBudgetIsLooser(): void
    {
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('get')->willReturn(
            new TestTool($this->createMock(LoggerInterface::class))
        );
        $converter = $this->makeConverter($this->makeMcpClient(), $serviceLocator);

        // TestTool declares 1 run of its own; the model budget of 5 is only a default
        // for tools without a ceiling and must not override it.
        $converted = $converter->convert($this->makeFunctionAiTool(), [], 5);

        static::assertStringNotContainsString('[ERROR]', (string)$converted->handle(new Request(['message' => 'hi'])));
        static::assertStringContainsString(
            'maximum number of runs (1)',
            (string)$converted->handle(new Request(['message' => 'hi']))
        );
    }

    public function testItRespectsAToolsOwnCeilingEvenWhenItExceedsTheModelBudget(): void
    {
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('get')->willReturn(
            new RunLimitedStubTool(10)
        );
        $converter = $this->makeConverter($this->makeMcpClient(), $serviceLocator);

        // The tool declares 10 runs; the model budget of 5 must not clamp it — the
        // declaration is authoritative.
        $converted = $converter->convert(
            $this->makeFunctionAiTool(RunLimitedStubTool::class),
            [],
            5
        );

        for ($i = 1; $i <= 10; $i++) {
            static::assertSame(
                'stub-ok',
                (string)$converted->handle(new Request([])),
                "Call {$i} must execute within the tool's own ceiling of 10."
            );
        }

        $eleventh = (string)$converted->handle(new Request([]));

        static::assertStringContainsString('[ERROR]', $eleventh);
        static::assertStringContainsString('maximum number of runs (10)', $eleventh);
    }

    // =========================================================================
    // Tests — the resolver forwards the model budget
    // =========================================================================

    public function testItForwardsTheModelRoundLimitFromTheRequestContextToTheConverter(): void
    {
        $aiTool = $this->makeMcpAiTool();
        $client = $this->makeMcpClient(expectedCalls: 5);
        $resolver = new LaravelToolResolver($this->makeConverter($client));

        // Registry default rounds: 5.
        $converted = $resolver->resolveToolByName(
            'knowledge_search',
            $this->makeContextWithMcpTool($aiTool, $this->makeSettingsWithRegistry())
        );

        for ($i = 1; $i <= 5; $i++) {
            static::assertStringNotContainsString('[ERROR]', (string)$converted->handle(new Request([])));
        }

        static::assertStringContainsString(
            'maximum number of runs (5)',
            (string)$converted->handle(new Request([]))
        );
    }
}
