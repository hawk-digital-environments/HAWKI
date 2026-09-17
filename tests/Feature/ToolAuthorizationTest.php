<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Ai\AiTool;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\Permission;
use App\Services\Admin\RoleAssignmentService;
use App\Services\Ai\Agents\Implementations\Chat\ChatToolResolver;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Tools\AbstractTool;
use App\Services\Ai\Tools\Exceptions\ToolAccessException;
use App\Services\Ai\Tools\LaravelAi\AuthorizedTextGateway;
use App\Services\Ai\Tools\LaravelAi\LaravelToolResolver;
use App\Services\Ai\Tools\LaravelAi\NativeToolAuthorizations;
use App\Services\Ai\Tools\ToolAuthorization;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\TextGenerationLoop;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversNothing;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Tests\TestCase;

#[CoversNothing()]
class ToolAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private User $actor;
    private Role $role;
    private AiModel $model;
    private AiTool $tool;
    private AgentRequestContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->resolving(\App\JsonApi\V1\AiModels\AiModelSchema::class, static fn ($schema) => $schema->useServiceContainerFallback(true));
        $this->app->resolving(\App\JsonApi\V1\AiToolCapabilities\AiToolCapabilitySchema::class, static fn ($schema) => $schema->useServiceContainerFallback(true));
        ToolAuthorizationProbe::$calls = 0;
        $this->actor = User::factory()->create();
        $this->role = Role::create(['name' => 'tool-test-' . $this->actor->id, 'display_name' => 'Tool test', 'guard_name' => 'web']);
        $this->role->syncPermissions(['tools.use', 'ai.capabilities.web_search.use']);
        app(RoleAssignmentService::class)->replace($this->actor, [$this->role->id]);
        $provider = AiProvider::create(['provider_id' => 'tool-test-' . $this->actor->id, 'name' => 'Test', 'active' => true, 'adapter_key' => 'openai', 'api_url' => 'https://example.invalid', 'api_key' => 'test-only']);
        $this->model = AiModel::create([
            'model_id' => 'tool-test-' . $this->actor->id, 'label' => 'Test', 'provider_id' => $provider->id,
            'active' => true, 'status' => OnlineStatus::ONLINE,
            'flags' => \App\Services\Ai\Models\Flags\Values\AiModelFlags::fromArray([]),
            'limits' => new \App\Services\Ai\Models\Limits\Values\NullAiModelLimits(),
            'pricing' => new \App\Services\Ai\Models\Pricing\Values\NullPricing(),
            'settings' => AiModelSettings::fromArray(['tool_calling' => true, 'native_capabilities' => true]),
            'native_capabilities' => NativeAiModelCapabilities::fromArray(['web_search']),
        ]);
        $this->model->usageRules()->create(['usage_type' => WellKnownUsageTypes::MAIN_APP]);
        $this->tool = AiTool::create(['name' => 'probe-' . $this->actor->id, 'type' => 'function', 'class_name' => ToolAuthorizationProbe::class,
            'description' => 'Test probe', 'capability' => 'web_search', 'active' => true, 'access_rule' => 'web_search']);
        $this->model->tools()->attach($this->tool->id, ['type' => 'manual']);
        $this->context = new AgentRequestContext(app(AiProviderProxyResolver::class)->resolve($provider), $this->model, new AiModelParameters(), actorId: $this->actor->id);
    }

    public function testPhpToolWithoutMcpServerResolvesAndExecutes(): void
    {
        $tool = app(LaravelToolResolver::class)->resolveToolForCapability('web_search', $this->context);
        self::assertSame('ok', $tool->handle(new Request([])));
        self::assertSame(1, ToolAuthorizationProbe::$calls);
    }

    public function testAllForgedSelectionPathsDenyWithoutCallingDownstream(): void
    {
        $this->role->syncPermissions([]);
        foreach ([$this->tool->name, 'capability:web_search:' . $this->tool->name, 'capability:web_search:auto', 'capability:web_search:native'] as $selection) {
            $this->assertAccessFailure(fn () => [...app(ChatToolResolver::class)->findTools([$selection], $this->context)], 'TOOL_ACCESS_DENIED');
        }
        self::assertSame(0, ToolAuthorizationProbe::$calls);
    }

    public function testAlreadyResolvedToolRechecksLoadedActorsAfterRevocation(): void
    {
        $this->actor->load('roles.permissions');
        $tool = app(LaravelToolResolver::class)->resolveToolByName($this->tool->name, $this->context);
        $this->role->syncPermissions([]);
        $this->assertAccessFailure(fn () => $tool->handle(new Request([])), 'TOOL_ACCESS_DENIED');
        self::assertSame(0, ToolAuthorizationProbe::$calls);
    }

    public function testDisabledInitiatingUserCannotUseAResolvedTool(): void
    {
        $tool = app(LaravelToolResolver::class)->resolveToolByName($this->tool->name, $this->context);
        DB::table('users')->where('id', $this->actor->id)->update(['admin_disabled' => true]);
        $this->assertAccessFailure(fn () => $tool->handle(new Request([])), 'TOOL_ACCESS_DENIED');
        self::assertSame(0, ToolAuthorizationProbe::$calls);
    }

    public function testModelAndToolConfigurationAreRechecked(): void
    {
        foreach ([['ai_tools', $this->tool->id, 'active', false], ['ai_models', $this->model->id, 'active', false], ['ai_providers', $this->model->provider_id, 'active', false]] as [$table, $id, $column, $value]) {
            DB::table($table)->where('id', $id)->update([$column => $value]);
            $this->assertAccessFailure(fn () => app(ToolAuthorization::class)->authorizeTool($this->tool, $this->context), 'TOOL_UNAVAILABLE');
            DB::table($table)->where('id', $id)->update([$column => true]);
        }
        $this->model->tools()->detach();
        $this->assertAccessFailure(fn () => app(ToolAuthorization::class)->authorizeTool($this->tool, $this->context), 'TOOL_UNAVAILABLE');
    }

    public function testExplicitCapabilityCannotSelectAnUnrelatedTool(): void
    {
        $this->tool->update(['mapped_capability' => 'knowledge_base']);
        $this->assertAccessFailure(fn () => [...app(ChatToolResolver::class)->findTools(['capability:web_search:' . $this->tool->name], $this->context)], 'TOOL_UNAVAILABLE');
    }

    public function testNativeSelectionRequiresModelSettingAndDedicatedGrant(): void
    {
        app(LaravelToolResolver::class)->resolveNativeToolForCapability('web_search', $this->context);
        $this->model->update(['settings' => AiModelSettings::fromArray(['tool_calling' => true, 'native_capabilities' => false])]);
        $this->assertAccessFailure(fn () => app(LaravelToolResolver::class)->resolveNativeToolForCapability('web_search', $this->context), 'TOOL_UNAVAILABLE');
        $this->model->update(['settings' => AiModelSettings::fromArray(['tool_calling' => true, 'native_capabilities' => true])]);
        $this->role->syncPermissions(['tools.use', 'tools.internal_search.use']);
        $this->tool->update(['access_rule' => 'internal_search']);
        app(LaravelToolResolver::class)->resolveToolForCapability('web_search', $this->context);
        self::assertSame([], app(ToolAuthorization::class)->nativeModelIds('web_search', $this->actor));
    }

    public function testNativeGatewayRechecksAuthorizationBeforeEveryDispatch(): void
    {
        $native = app(LaravelToolResolver::class)->resolveNativeToolForCapability('web_search', $this->context);
        $gateway = $this->createMock(StepTextGateway::class);
        $gateway->expects(self::never())->method('generateTextStep');
        $gateway->expects(self::never())->method('generateStreamStep');
        $wrapped = new AuthorizedTextGateway($gateway);
        $this->role->syncPermissions([]);
        $driver = $this->context->provider->driver;
        $this->assertAccessFailure(fn () => $wrapped->generateTextStep($driver, 'test', '', [], [$native], null, null, null, new StepContext(0, false)), 'TOOL_ACCESS_DENIED');
        $this->assertAccessFailure(fn () => [...$wrapped->generateStreamStep('test', $driver, 'test', '', [], [$native], null, null, null, new StepContext(1, false))], 'TOOL_ACCESS_DENIED');
    }

    public function testDiscoveryFiltersBeforePaginationAndInModelRelationships(): void
    {
        $denied = AiTool::create(['name' => 'denied-' . $this->actor->id, 'type' => 'function', 'description' => 'Restricted', 'active' => true, 'access_rule' => 'unavailable']);
        $this->model->tools()->attach($denied->id, ['type' => 'manual']);
        $this->actingAs($this->actor);
        $headers = ['Accept' => 'application/vnd.api+json'];
        $this->getJson('/api/hawki/v1/ai-tools?sort=-id&page[size]=1', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $this->tool->id);
        $this->getJson('/api/hawki/v1/ai-models/' . $this->model->id . '/relationships/tools', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $this->tool->id);
        $this->getJson('/api/hawki/v1/ai-models/' . $this->model->id . '?include=tools', $headers)->assertOk()->assertJsonMissingExact(['id' => (string) $denied->id, 'type' => 'ai-tools']);
        $this->getJson('/api/hawki/v1/ai-tools/' . $denied->id, $headers)->assertNotFound();
        $server = \App\Models\Ai\McpServer::create(['url' => 'https://example.invalid/catalog', 'server_label' => 'Catalog', 'type' => 'http', 'timeouts' => \App\Services\Ai\Tools\Values\McpServerTimeouts::fromArray([]), 'api_key' => 'test-only']);
        $this->tool->update(['mcp_server_id' => $server->id]);
        $denied->update(['mcp_server_id' => $server->id]);
        $this->getJson('/api/hawki/v1/mcp-servers/' . $server->id . '/relationships/tools', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $this->tool->id);
        $this->getJson('/api/hawki/v1/mcp-servers/' . $server->id . '/tools', $headers)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/hawki/v1/mcp-servers/' . $server->id . '?include=tools', $headers)->assertOk()->assertJsonMissingExact(['id' => (string) $denied->id, 'type' => 'ai-tools']);
        $this->role->syncPermissions([]);
        $this->getJson('/api/hawki/v1/ai-tools', $headers)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/hawki/v1/ai-tool-capabilities', $headers)->assertOk()->assertJsonCount(0, 'data');
    }

    public function testAdminCatalogAndExplicitOperatorBootstrap(): void
    {
        $this->role->syncPermissions(['admin.access', 'roles.manage', 'mcp.manage']);
        $this->actingAs($this->actor);
        $response = $this->getJson('/api/hawki/v1/admin-roles', ['Accept' => 'application/vnd.api+json'])->assertOk();
        self::assertContains('tools.use', array_column($response->json('meta.permission_catalog'), 'name'));
        $this->artisan('rbac:grant-tool-access', ['role' => $this->role->name, 'rule' => 'web_search'])->assertSuccessful();
        self::assertTrue($this->actor->hasPermissionTo('ai.capabilities.web_search.use'));
        $this->assertDatabaseHas('admin_audit_log', ['action' => 'grant-tool-access', 'resource_id' => (string) $this->role->id]);
    }

    public function testMcpRevocationMakesNoClientCall(): void
    {
        $server = \App\Models\Ai\McpServer::create(['url' => 'https://example.invalid/mcp', 'server_label' => 'Test MCP', 'timeouts' => \App\Services\Ai\Tools\Values\McpServerTimeouts::fromArray([]), 'api_key' => 'test-only']);
        $server->forceFill(['status' => OnlineStatus::ONLINE])->save();
        $this->tool->update(['type' => 'mcp', 'mcp_server_id' => $server->id, 'mcp_name' => 'search', 'mcp_config' => ['inputSchema' => ['type' => 'object', 'properties' => []]]]);
        $this->tool->load('server');
        $client = $this->createMock(\App\Services\Ai\Tools\Mcp\HawkiMcpClient::class);
        $client->expects(self::never())->method('callTool');
        $inner = new \App\Services\Ai\Tools\LaravelAi\LaravelMcpTool(app(\Psr\Log\LoggerInterface::class), $this->tool, $client);
        $tool = new \App\Services\Ai\Tools\LaravelAi\AuthorizedTool($inner, $this->tool, $this->context);
        $this->role->syncPermissions([]);
        $this->assertAccessFailure(fn () => $tool->handle(new Request([])), 'TOOL_ACCESS_DENIED');
    }

    public function testTrustedActorSurvivesBackgroundAuthenticationChanges(): void
    {
        $tool = app(LaravelToolResolver::class)->resolveToolByName($this->tool->name, $this->context);
        $other = User::factory()->create();
        $this->actingAs($other);
        self::assertSame('ok', $tool->handle(new Request([])));
        $this->role->syncPermissions([]);
        $this->assertAccessFailure(fn () => $tool->handle(new Request([])), 'TOOL_ACCESS_DENIED');
        $missingActor = new AgentRequestContext($this->context->provider, $this->model, new AiModelParameters());
        $this->assertAccessFailure(fn () => app(LaravelToolResolver::class)->resolveToolByName($this->tool->name, $missingActor), 'TOOL_ACCESS_DENIED');
    }

    public function testRuleChangesRequireBothSectionGrantsAndOldRuleGrantability(): void
    {
        $this->role->syncPermissions(['admin.access', 'mcp.manage']);
        $this->actingAs($this->actor);
        $headers = ['Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json'];
        $data = ['data' => ['type' => 'admin-tools', 'id' => (string) $this->tool->id, 'attributes' => ['active' => true, 'models' => [$this->model->id], 'access_rule' => 'unavailable']]];
        $version = app(\App\Services\Admin\Repositories\ToolRepository::class)->readOne($this->actor, (string) $this->tool->id)['_version'];
        $headers['If-Match'] = '"' . $version . '"';
        $this->patchJson('/api/hawki/v1/admin-tools/' . $this->tool->id, $data, $headers)->assertForbidden();
        $this->role->givePermissionTo('roles.manage');
        $this->patchJson('/api/hawki/v1/admin-tools/' . $this->tool->id, $data, $headers)->assertUnprocessable();
        $this->role->givePermissionTo(['tools.use', 'ai.capabilities.web_search.use']);
        $this->patchJson('/api/hawki/v1/admin-tools/' . $this->tool->id, $data, $headers)->assertOk()->assertJsonPath('data.attributes.access_rule', 'unavailable');
    }

    public function testForgedHttpSelectionIsRejectedBeforeOpeningStream(): void
    {
        $this->role->syncPermissions([]);
        $this->actingAs($this->actor);
        // The dev container exports APP_ENV=local, so the framework does not auto-skip forgery protection here.
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->postJson('/req/streamAI', [
            'broadcast' => false,
            'payload' => ['model' => $this->model->model_id, 'stream' => true,
                'actorId' => 1, 'tools' => ['capability:web_search:native'],
                'messages' => [['role' => 'system', 'content' => ['text' => 'Test']], ['role' => 'user', 'content' => ['text' => 'Test']]]],
        ])->assertForbidden()->assertJsonPath('code', 'TOOL_ACCESS_DENIED');
    }

    public function testNativeWebFetchNeedsItsOwnGrantAndIsReachableWithIt(): void
    {
        // The dev database may not have run the permission migration yet.
        SpatiePermission::findOrCreate(Permission::WEB_FETCH_USE->value, 'web');
        [$context, $model] = $this->makeNativeWebFetchContext();

        $this->assertAccessFailure(fn () => app(LaravelToolResolver::class)->resolveNativeToolForCapability('web_fetch', $context), 'TOOL_ACCESS_DENIED');
        self::assertSame([], app(ToolAuthorization::class)->nativeModelIds('web_fetch', $this->actor));

        $this->role->syncPermissions([Permission::TOOLS_USE->value, Permission::WEB_FETCH_USE->value]);
        $native = app(LaravelToolResolver::class)->resolveNativeToolForCapability('web_fetch', $context);
        self::assertInstanceOf(WebFetch::class, $native);
        self::assertContains((string) $model->id, app(ToolAuthorization::class)->nativeModelIds('web_fetch', $this->actor->fresh()));
    }

    public function testGrantedSelectionWithoutAnyUsableImplementationIsUnavailableNotDenied(): void
    {
        $this->model->tools()->detach();
        $this->model->update(['settings' => AiModelSettings::fromArray(['tool_calling' => true, 'native_capabilities' => false])]);
        $this->assertAccessFailure(fn () => [...app(ChatToolResolver::class)->findTools(['capability:web_search:auto'], $this->context)], 'TOOL_UNAVAILABLE');

        $this->actingAs($this->actor);
        // The dev container exports APP_ENV=local, so the framework does not auto-skip forgery protection here.
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->postJson('/req/streamAI', [
            'broadcast' => false,
            'payload' => ['model' => $this->model->model_id, 'stream' => true,
                'actorId' => $this->actor->id, 'tools' => ['capability:web_search:auto'],
                'messages' => [['role' => 'system', 'content' => ['text' => 'Test']], ['role' => 'user', 'content' => ['text' => 'Test']]]],
        ])->assertStatus(422)->assertJsonPath('code', 'TOOL_UNAVAILABLE');
    }

    public function testASharedNativeToolInstanceCannotCrossAuthorizeTwoContexts(): void
    {
        $registry = app(NativeToolAuthorizations::class);
        $shared = new WebSearch();
        $mine = $registry->register($shared, 'web_search', $this->context);
        $actorless = new AgentRequestContext($this->context->provider, $this->model, new AiModelParameters());
        $theirs = $registry->register($shared, 'web_search', $actorless);

        self::assertNotSame($mine, $theirs);
        $registry->authorize([$mine]);
        $this->assertAccessFailure(fn () => $registry->authorize([$theirs]), 'TOOL_ACCESS_DENIED');
        // The instance the filter event handed out is not itself a key and stays unusable.
        $this->assertAccessFailure(fn () => $registry->authorize([$shared]), 'TOOL_ACCESS_DENIED');
    }

    public function testADriverThatCannotBeWrappedRefusesToSend(): void
    {
        $driver = new GatewaylessTextProvider($this->createMock(\Laravel\Ai\Contracts\Gateway\Gateway::class), ['name' => 'test', 'driver' => 'test', 'key' => 'test-only'], app(\Illuminate\Contracts\Events\Dispatcher::class));
        $context = new AgentRequestContext(
            new \App\Services\Ai\Providers\Values\AiProviderProxy($this->context->provider->getRealProvider(), $this->context->provider->adapter, $driver),
            $this->model,
            new AiModelParameters(),
            actorId: $this->actor->id
        );
        $agent = new ToolAuthorizationAgent($context, 'Test', [], [], 'Test');
        foreach (['send', 'sendStreaming'] as $method) {
            try {
                $agent->{$method}();
                self::fail('Expected a refusal to send through an unwrappable driver.');
            } catch (\LogicException $exception) {
                self::assertStringContainsString(GatewaylessTextProvider::class, $exception->getMessage());
            }
        }
    }

    /** @return array{0: AgentRequestContext, 1: AiModel} */
    private function makeNativeWebFetchContext(): array
    {
        $provider = AiProvider::create(['provider_id' => 'fetch-test-' . $this->actor->id, 'name' => 'Fetch test', 'active' => true, 'adapter_key' => 'gemini', 'api_url' => 'https://example.invalid', 'api_key' => 'test-only']);
        $model = AiModel::create([
            'model_id' => 'fetch-test-' . $this->actor->id, 'label' => 'Fetch test', 'provider_id' => $provider->id,
            'active' => true, 'status' => OnlineStatus::ONLINE,
            'flags' => \App\Services\Ai\Models\Flags\Values\AiModelFlags::fromArray([]),
            'limits' => new \App\Services\Ai\Models\Limits\Values\NullAiModelLimits(),
            'pricing' => new \App\Services\Ai\Models\Pricing\Values\NullPricing(),
            'settings' => AiModelSettings::fromArray(['tool_calling' => true, 'native_capabilities' => true]),
            'native_capabilities' => NativeAiModelCapabilities::fromArray(['web_fetch']),
        ]);
        $model->usageRules()->create(['usage_type' => WellKnownUsageTypes::MAIN_APP]);

        return [new AgentRequestContext(app(AiProviderProxyResolver::class)->resolve($provider), $model, new AiModelParameters(), actorId: $this->actor->id), $model];
    }

    public function testModelOnlineStatusDoesNotGateNativeTool(): void
    {
        $this->model->update(['status' => OnlineStatus::OFFLINE]);
        // Other seeded models may also offer native web search; only this model's presence is the point.
        self::assertContains((string) $this->model->id, app(ToolAuthorization::class)->nativeModelIds('web_search', $this->actor));
        self::assertInstanceOf(WebSearch::class, app(LaravelToolResolver::class)->resolveNativeToolForCapability('web_search', $this->context));
    }

    public function testAutomaticCapabilityLookupSkipsAnUnauthorizedCandidate(): void
    {
        $this->tool->update(['access_rule' => 'unavailable']);
        $allowed = $this->tool->replicate();
        $allowed->fill(['name' => 'allowed-' . $this->actor->id, 'access_rule' => 'web_search'])->save();
        $this->model->tools()->attach($allowed->id, ['type' => 'manual']);
        $resolved = app(LaravelToolResolver::class)->resolveToolForCapability('web_search', $this->context);
        self::assertSame($allowed->name, $resolved->name());
        self::assertSame('ok', $resolved->handle(new Request([])));
    }

    public function testNativeGatewayAllowsFirstStepAndDeniesRevokedContinuation(): void
    {
        $native = app(LaravelToolResolver::class)->resolveNativeToolForCapability('web_search', $this->context);
        $gateway = $this->createMock(StepTextGateway::class);
        $gateway->expects(self::once())->method('generateTextStep')->willReturn($this->createStub(\Laravel\Ai\Gateway\StepResponse::class));
        $wrapped = new AuthorizedTextGateway($gateway);
        $driver = $this->context->provider->driver;
        $wrapped->generateTextStep($driver, 'test', '', [], [$native], null, null, null, new StepContext(0, false));
        $this->role->syncPermissions([]);
        $this->assertAccessFailure(fn () => $wrapped->generateTextStep($driver, 'test', '', [], [$native], null, null, null, new StepContext(1, false)), 'TOOL_ACCESS_DENIED');
    }

    public function testMcpServerConfigurationIsRecheckedBeforeClientCall(): void
    {
        $server = \App\Models\Ai\McpServer::create(['url' => 'https://example.invalid/mcp', 'server_label' => 'Test MCP', 'timeouts' => \App\Services\Ai\Tools\Values\McpServerTimeouts::fromArray([]), 'api_key' => 'test-only']);
        $server->forceFill(['status' => OnlineStatus::ONLINE])->save();
        $this->tool->update(['type' => 'mcp', 'mcp_server_id' => $server->id, 'mcp_name' => 'search', 'mcp_config' => ['inputSchema' => ['type' => 'object', 'properties' => []]]]);
        $this->tool->load('server');
        $client = $this->createMock(\App\Services\Ai\Tools\Mcp\HawkiMcpClient::class);
        $client->expects(self::never())->method('callTool');
        $inner = new \App\Services\Ai\Tools\LaravelAi\LaravelMcpTool(app(\Psr\Log\LoggerInterface::class), $this->tool, $client);
        $tool = new \App\Services\Ai\Tools\LaravelAi\AuthorizedTool($inner, $this->tool, $this->context);
        $server->update(['url' => 'https://example.invalid/reconfigured']);
        $this->assertAccessFailure(fn () => $tool->handle(new Request([])), 'TOOL_UNAVAILABLE');
    }

    public function testSdkCannotContinueOrReportSuccessAfterLocalToolRevocation(): void
    {
        $tool = app(LaravelToolResolver::class)->resolveToolByName($this->tool->name, $this->context);
        $gateway = $this->createMock(StepTextGateway::class);
        $gateway->expects(self::once())->method('generateTextStep')->willReturnCallback(function () {
            $this->role->syncPermissions([]);
            return new \Laravel\Ai\Gateway\StepResponse('', [new \Laravel\Ai\Responses\Data\ToolCall('call-1', $this->tool->name, [])], \Laravel\Ai\Responses\Data\FinishReason::ToolCalls, new \Laravel\Ai\Responses\Data\Usage(), new \Laravel\Ai\Responses\Data\Meta());
        });
        $this->context->provider->driver->useTextGateway($gateway);
        $agent = new ToolAuthorizationAgent($this->context, 'Test', [], [$tool], 'Test');
        $this->assertAccessFailure(fn () => $agent->send(), 'TOOL_ACCESS_DENIED');
        self::assertSame(0, ToolAuthorizationProbe::$calls);
    }

    public function testSdkStreamStopsAfterLocalToolRevocation(): void
    {
        $tool = app(LaravelToolResolver::class)->resolveToolByName($this->tool->name, $this->context);
        $gateway = $this->createMock(StepTextGateway::class);
        $gateway->expects(self::once())->method('generateStreamStep')->willReturnCallback(function (): \Generator {
            $this->role->syncPermissions([]);
            yield from [];
            return new \Laravel\Ai\Gateway\StepResponse('', [new \Laravel\Ai\Responses\Data\ToolCall('call-1', $this->tool->name, [])], \Laravel\Ai\Responses\Data\FinishReason::ToolCalls, new \Laravel\Ai\Responses\Data\Usage(), new \Laravel\Ai\Responses\Data\Meta());
        });
        $this->context->provider->driver->useTextGateway($gateway);
        $agent = new ToolAuthorizationAgent($this->context, 'Test', [], [$tool], 'Test');
        $this->assertAccessFailure(fn () => [...$agent->sendStreaming()], 'TOOL_ACCESS_DENIED');
        self::assertSame(0, ToolAuthorizationProbe::$calls);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('mcpConfigurationChanges')]
    public function testFreshlyResolvedMcpToolDoesNotReuseAClientWithOldConfiguration(string $field, mixed $value): void
    {
        $server = \App\Models\Ai\McpServer::create(['url' => 'https://example.invalid/mcp', 'server_label' => 'Test MCP', 'type' => 'http', 'timeouts' => \App\Services\Ai\Tools\Values\McpServerTimeouts::fromArray([]), 'api_key' => 'test-only']);
        $server->forceFill(['status' => OnlineStatus::ONLINE])->save();
        $this->tool->update(['type' => 'mcp', 'mcp_server_id' => $server->id, 'mcp_name' => 'search', 'mcp_config' => ['inputSchema' => ['type' => 'object', 'properties' => []]]]);
        $oldClient = $this->createMock(\App\Services\Ai\Tools\Mcp\HawkiMcpClient::class);
        $oldClient->expects(self::never())->method('callTool');
        $newClient = $this->createMock(\App\Services\Ai\Tools\Mcp\HawkiMcpClient::class);
        $newClient->expects(self::once())->method('callTool')->with('search', [])->willReturn(new \Mcp\Types\CallToolResult([]));
        $factory = $this->createMock(\App\Services\Ai\Tools\Mcp\McpClientFactory::class);
        $factory->expects(self::exactly(2))->method('createForServer')->willReturnOnConsecutiveCalls($oldClient, $newClient);
        $this->app->instance(\App\Services\Ai\Tools\Mcp\McpClientFactory::class, $factory);
        $resolver = app(LaravelToolResolver::class);
        $resolver->resolveToolByName($this->tool->name, $this->context);
        // Unchanged records should still share one client.
        $resolver->resolveToolByName($this->tool->name, $this->context);
        if ($field === 'additional_config') {
            // Existing MySQL schema stores this field as JSON despite the encrypted cast.
            // Exercise cache invalidation against a stored change without changing that schema here.
            DB::table('mcp_servers')->where('id', $server->id)->update([$field => json_encode($value, JSON_THROW_ON_ERROR)]);
        } else {
            $server->update([$field => $field === 'timeouts' ? \App\Services\Ai\Tools\Values\McpServerTimeouts::fromArray($value) : $value]);
        }
        $fresh = $resolver->resolveToolByName($this->tool->name, $this->context);
        $result = json_decode((string) $fresh->handle(new Request([])), true);
        self::assertSame(false, $result['isError'] ?? null);
    }

    public static function mcpConfigurationChanges(): array
    {
        return [
            'endpoint' => ['url', 'https://example.invalid/reconfigured'],
            'credentials' => ['api_key', 'replacement-test-key'],
            'transport' => ['type', 'sse'],
            'options' => ['additional_config', ['headers' => ['X-Test' => 'updated']]],
            'timeouts' => ['timeouts', ['read' => 17]],
        ];
    }

    public function testFreshProviderProxyDoesNotReuseAReconfiguredDriver(): void
    {
        $provider = $this->context->provider->getRealProvider();
        $provider->update(['adapter_key' => 'openai_like']);
        $resolver = app(AiProviderProxyResolver::class);
        $first = $resolver->resolve($provider->fresh());
        $provider->update(['api_key' => 'replacement-test-key', 'api_url' => 'https://example.invalid/reconfigured']);
        $second = $resolver->resolve($provider->fresh());
        self::assertNotSame($first->driver, $second->driver);
        self::assertSame('replacement-test-key', $second->driver->providerCredentials()['key']);
    }

    public function testTwoProvidersUsingTheSameAdapterKeepTheirOwnCredentials(): void
    {
        $first = AiProvider::create(['provider_id' => 'first-' . $this->actor->id, 'name' => 'First', 'adapter_key' => 'anthropic', 'api_key' => 'first-test-key']);
        $second = AiProvider::create(['provider_id' => 'second-' . $this->actor->id, 'name' => 'Second', 'adapter_key' => 'anthropic', 'api_key' => 'second-test-key']);
        $resolver = app(AiProviderProxyResolver::class);
        $firstProxy = $resolver->resolve($first);
        $secondProxy = $resolver->resolve($second);
        self::assertNotSame($firstProxy->driver, $secondProxy->driver);
        self::assertSame('first-test-key', $firstProxy->driver->providerCredentials()['key']);
        self::assertSame('second-test-key', $secondProxy->driver->providerCredentials()['key']);
    }

    public function testInheritedSdkEntryPointsFailClosedWithoutAWorkerAuthorizationRegistry(): void
    {
        foreach (['native', 'local'] as $kind) {
            $tool = $kind === 'native'
                ? app(LaravelToolResolver::class)->resolveNativeToolForCapability('web_search', $this->context)
                : app(LaravelToolResolver::class)->resolveToolByName($this->tool->name, $this->context);
            $gateway = $this->createMock(StepTextGateway::class);
            $gateway->expects(self::never())->method('generateTextStep');
            $gateway->expects(self::never())->method('generateStreamStep');
            $this->context->provider->driver->useTextGateway($gateway);
            $agent = new ToolAuthorizationAgent($this->context, 'Test', [], [$tool], 'Test');
            $this->app->forgetInstance(\App\Services\Ai\Tools\LaravelAi\NativeToolAuthorizations::class);
            foreach (['prompt', 'stream', 'queue', 'broadcast', 'broadcastNow', 'broadcastOnQueue'] as $entry) {
                $arguments = str_starts_with($entry, 'broadcast') ? ['Test', []] : ['Test'];
                $this->assertAccessFailure(fn () => $agent->{$entry}(...$arguments), 'TOOL_ACCESS_DENIED');
            }
        }
        self::assertSame(0, ToolAuthorizationProbe::$calls);
    }

    public function testSerializedSdkJobsAreRejectedAndExistingInvokeJobsCannotDispatchAfterRevocation(): void
    {
        $native = app(LaravelToolResolver::class)->resolveNativeToolForCapability('web_search', $this->context);
        $gateway = $this->createMock(StepTextGateway::class);
        $gateway->expects(self::never())->method('generateTextStep');
        $gateway->expects(self::never())->method('generateStreamStep');
        $this->context->provider->driver->useTextGateway($gateway);
        $agent = new ToolAuthorizationAgent($this->context, 'Test', [], [$native], 'Test');
        $invoke = new \Laravel\Ai\Jobs\InvokeAgent($agent, 'Test');
        $broadcast = new \Laravel\Ai\Jobs\BroadcastAgent($agent, 'Test', []);
        $this->assertAccessFailure(fn () => serialize($invoke), 'TOOL_ACCESS_DENIED');
        $this->assertAccessFailure(fn () => serialize($broadcast), 'TOOL_ACCESS_DENIED');
        $class = ToolAuthorizationAgent::class;
        $legacyPayload = 'O:' . strlen($class) . ':"' . $class . '":0:{}';
        $this->assertAccessFailure(fn () => unserialize($legacyPayload), 'TOOL_ACCESS_DENIED');
        $this->role->syncPermissions([]);
        $this->app->forgetInstance(\App\Services\Ai\Tools\LaravelAi\NativeToolAuthorizations::class);
        $this->assertAccessFailure(fn () => $invoke->handle(), 'TOOL_ACCESS_DENIED');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('capabilityColumns')]
    public function testCapabilityChangesInvalidateAnAlreadyResolvedTool(string $column): void
    {
        $tool = app(LaravelToolResolver::class)->resolveToolByName($this->tool->name, $this->context, [], 'web_search');
        $this->tool->update([$column => 'knowledge_base']);
        $this->assertAccessFailure(fn () => $tool->handle(new Request([])), 'TOOL_UNAVAILABLE');
        self::assertSame(0, ToolAuthorizationProbe::$calls);
    }

    public static function capabilityColumns(): array
    {
        return [['capability'], ['mapped_capability']];
    }

    private function assertAccessFailure(callable $operation, string $code): void
    {
        try {
            $operation();
            self::fail('Expected tool authorization failure.');
        } catch (ToolAccessException $exception) {
            self::assertSame($code, $exception->errorCode);
        }
    }
}

class ToolAuthorizationProbe extends AbstractTool
{
    public static int $calls = 0;
    public function name(): string { return 'authorization-probe'; }
    public function description(): string { return 'Authorization test probe'; }
    public function schema(JsonSchema $schema): array { return []; }
    public function __invoke(): string { ++self::$calls; return 'ok'; }
}

class ToolAuthorizationAgent extends \App\Services\Ai\Agents\Implementations\Chat\ChatAgent
{
    public function middleware(): array
    {
        return [(new \App\Services\Ai\Agents\Middleware\LoggingMiddleware())->useServiceContainerFallback(true)];
    }
}

/** A text driver without the SDK's HasTextGateway trait, so the authorization wrapper cannot be installed. */
class GatewaylessTextProvider extends Provider implements TextProvider
{
    public function prompt(AgentPrompt $prompt): AgentResponse
    {
        throw new \LogicException('Must never be reached.');
    }

    public function stream(AgentPrompt $prompt): StreamableAgentResponse
    {
        throw new \LogicException('Must never be reached.');
    }

    public function useTextGateway(StepTextGateway $gateway): self
    {
        throw new \LogicException('Must never be reached.');
    }

    public function textGenerationLoop(): TextGenerationLoop
    {
        throw new \LogicException('Must never be reached.');
    }

    public function defaultTextModel(): string
    {
        return 'test';
    }

    public function cheapestTextModel(): string
    {
        return 'test';
    }

    public function smartestTextModel(): string
    {
        return 'test';
    }
}
