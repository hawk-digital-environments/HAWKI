<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Tools\LaravelAi;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Settings\AiModelSettingRegistry;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\Tools\Exceptions\ToolNotFoundException;
use App\Services\Ai\Tools\LaravelAi\LaravelToolConverter;
use App\Services\Ai\Tools\LaravelAi\LaravelToolResolver;
use App\Services\Ai\Tools\Values\ToolType;
use App\Services\Ai\Values\OnlineStatus;
use Illuminate\Support\Collection;
use App\Services\Ai\Tools\Contracts\ToolInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Pins which tools {@see LaravelToolResolver::resolveToolForCapability()} considers eligible:
 * function tools have no MCP server and must not be filtered (or crash) on its online status.
 */
#[CoversClass(LaravelToolResolver::class)]
class LaravelToolResolverTest extends TestCase
{
    private function makeFunctionTool(bool $active = true): AiTool
    {
        $tool = new AiTool();
        $tool->type = ToolType::FUNCTION;
        $tool->name = 'web_search_tool';
        $tool->capability = 'web_search';
        $tool->active = $active;

        return $tool;
    }

    private function makeMcpTool(OnlineStatus $status): AiTool
    {
        $server = new McpServer();
        $server->status = $status;

        $tool = new AiTool();
        $tool->type = ToolType::MCP;
        $tool->name = 'mcp_web_search';
        $tool->capability = 'web_search';
        $tool->active = true;
        $tool->setRelation('server', $server);

        return $tool;
    }

    private function makeContext(AiTool ...$tools): AgentRequestContext
    {
        $settings = AiModelSettings::fromArray([]);
        $settings->setService(AiModelSettingRegistry::class, $this->app->make(AiModelSettingRegistry::class));

        $model = $this->createMock(AiModel::class);
        $model->method('__get')->willReturnCallback(
            fn(string $key) => match ($key) {
                'settings' => $settings,
                'tools' => new Collection($tools),
                default => null,
            }
        );

        return new AgentRequestContext(
            provider: new AiProviderProxy(
                provider: $this->createMock(AiProvider::class),
                adapter: $this->createMock(ProviderAdapterInterface::class),
                driver: $this->createMock(\Laravel\Ai\Providers\Provider::class),
            ),
            model: $model,
            modelParameters: new AiModelParameters(),
        );
    }

    private function makeResolverExpecting(AiTool $expected): LaravelToolResolver
    {
        $converter = $this->createMock(LaravelToolConverter::class);
        $converter->expects($this->once())
            ->method('convert')
            ->with($this->identicalTo($expected))
            ->willReturn($this->createMock(ToolInterface::class));

        return new LaravelToolResolver($converter);
    }

    public function testItResolvesAFunctionToolWithoutAnMcpServerForItsCapability(): void
    {
        $tool = $this->makeFunctionTool();

        $this->makeResolverExpecting($tool)->resolveToolForCapability('web_search', $this->makeContext($tool));
    }

    public function testItSkipsMcpToolsWhoseServerIsOffline(): void
    {
        $offline = $this->makeMcpTool(OnlineStatus::OFFLINE);
        $function = $this->makeFunctionTool();

        $this->makeResolverExpecting($function)->resolveToolForCapability('web_search', $this->makeContext($offline, $function));
    }

    public function testItResolvesMcpToolsWhoseServerIsOnline(): void
    {
        $online = $this->makeMcpTool(OnlineStatus::ONLINE);

        $this->makeResolverExpecting($online)->resolveToolForCapability('web_search', $this->makeContext($online));
    }

    public function testItThrowsWhenNoActiveToolServesTheCapability(): void
    {
        $resolver = new LaravelToolResolver($this->createMock(LaravelToolConverter::class));

        $this->expectException(ToolNotFoundException::class);

        $resolver->resolveToolForCapability('web_search', $this->makeContext($this->makeFunctionTool(active: false)));
    }
}
