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
use App\Services\Ai\Agents\Implementations\Chat\ChatAgentFromLegacyRequestFactory;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Access\Exceptions\ModelAccessException;
use App\Services\Ai\Models\Access\ModelAuthorization;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class ModelAccessTest extends TestCase
{
    use DatabaseTransactions;
    use \Tests\Support\AdminJsonApiRequests;

    private User $actor;
    private Role $actorRole;
    private Role $allowedRole;
    private AiProvider $provider;
    private AiModel $restrictedModel;
    private AiModel $unrestrictedModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->resolving(\App\JsonApi\V1\AiModels\AiModelSchema::class, static fn ($schema) => $schema->useServiceContainerFallback(true));

        $this->actor = User::factory()->create();
        $this->actorRole = Role::create([
            'name' => 'model-actor-' . $this->actor->id,
            'display_name' => 'Model actor',
            'guard_name' => 'web',
        ]);
        $this->actorRole->syncPermissions(['tools.use', 'ai.capabilities.web_search.use']);
        $this->allowedRole = Role::create([
            'name' => 'model-allowed-' . $this->actor->id,
            'display_name' => 'Model allowed',
            'guard_name' => 'web',
        ]);
        app(RoleAssignmentService::class)->replace($this->actor, [$this->actorRole->id]);

        $this->provider = AiProvider::create([
            'provider_id' => 'model-access-' . $this->actor->id,
            'name' => 'Model access',
            'active' => true,
            'adapter_key' => 'openai',
            'api_url' => 'https://example.invalid',
            'api_key' => 'test-only',
        ]);
        $this->restrictedModel = $this->createModel('restricted');
        $this->restrictedModel->allowedRoles()->attach($this->allowedRole->id, ['created_at' => now()]);
        $this->unrestrictedModel = $this->createModel('unrestricted');
    }

    public function testModelCatalogFiltersRestrictedModelsByRoleAndKeepsUnrestrictedModels(): void
    {
        $this->actingAs($this->actor);
        $headers = ['Accept' => 'application/vnd.api+json'];

        $ids = $this->getJson('/api/hawki/v1/ai-models', $headers)->assertOk()->json('data.*.id');
        self::assertNotContains((string) $this->restrictedModel->id, $ids);
        self::assertContains((string) $this->unrestrictedModel->id, $ids);

        app(RoleAssignmentService::class)->replace($this->actor, [$this->actorRole->id, $this->allowedRole->id]);
        $ids = $this->getJson('/api/hawki/v1/ai-models', $headers)->assertOk()->json('data.*.id');
        self::assertContains((string) $this->restrictedModel->id, $ids);
        self::assertContains((string) $this->unrestrictedModel->id, $ids);
    }

    public function testToolAttachedOnlyToRestrictedModelIsHiddenWithoutRole(): void
    {
        $tool = AiTool::create([
            'name' => 'restricted-tool-' . $this->actor->id,
            'type' => 'function',
            'description' => 'Restricted model tool',
            'capability' => 'web_search',
            'active' => true,
            'access_rule' => 'web_search',
        ]);
        $this->restrictedModel->tools()->attach($tool->id, ['type' => 'manual']);

        $this->actingAs($this->actor);
        $ids = $this->getJson('/api/hawki/v1/ai-tools', ['Accept' => 'application/vnd.api+json'])
            ->assertOk()->json('data.*.id');

        self::assertNotContains((string) $tool->id, $ids);
    }

    public function testFactoryReportsRoleDeniedModelSelection(): void
    {
        $this->actingAs($this->actor);

        try {
            app(ChatAgentFromLegacyRequestFactory::class)->createAgent([
                'payload' => ['model' => $this->restrictedModel->model_id, 'messages' => [['role' => 'system', 'content' => ['text' => 'Test']], ['role' => 'user', 'content' => ['text' => 'Test']]]],
            ]);
            self::fail('Expected model access to be denied.');
        } catch (ModelAccessException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame('MODEL_ACCESS_DENIED', $exception->errorCode);
        }
    }

    public function testDispatchAuthorizationReadsCurrentRoleAssignments(): void
    {
        app(RoleAssignmentService::class)->replace($this->actor, [$this->allowedRole->id]);
        $context = $this->contextFor($this->restrictedModel);
        app(ModelAuthorization::class)->authorize($context);

        $otherRole = Role::create([
            'name' => 'model-other-' . $this->actor->id,
            'display_name' => 'Model other',
            'guard_name' => 'web',
        ]);
        DB::table('ai_model_roles')->where('ai_model_id', $this->restrictedModel->id)->delete();
        DB::table('ai_model_roles')->insert([
            'ai_model_id' => $this->restrictedModel->id,
            'role_id' => $otherRole->id,
            'created_at' => now(),
        ]);

        $this->expectException(ModelAccessException::class);
        app(ModelAuthorization::class)->authorize($context);
    }

    public function testDisabledActorCannotUseRestrictedModel(): void
    {
        app(RoleAssignmentService::class)->replace($this->actor, [$this->allowedRole->id]);
        DB::table('users')->where('id', $this->actor->id)->update(['admin_disabled' => true]);

        $this->expectException(ModelAccessException::class);
        app(ModelAuthorization::class)->authorize($this->contextFor($this->restrictedModel));
    }

    public function testAdminAllowedRolesRoundTripChangesVersion(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin);
        $values = $this->adminModelValues('admin-round-trip-' . $admin->id);
        $id = $this->postAdminResource('/api/hawki/v1/admin-models', ['values' => $values])
            ->assertCreated()->json('data.id');
        $response = $this->get('/api/hawki/v1/admin-models?filter[search]=' . $values['model_id'])->assertOk();
        self::assertContains($this->allowedRole->id, array_column($response->json('meta.role_catalog'), 'id'));
        $row = $response->json('data.0');

        $values['allowed_roles'] = [$this->allowedRole->id];
        $updated = $this->patchAdminResource('/api/hawki/v1/admin-models/' . $id, [
            'version' => $row['meta']['version'],
            'values' => $values,
        ])->assertOk();

        self::assertNotSame($row['meta']['version'], $updated->json('data.meta.version'));
        self::assertSame([$this->allowedRole->id], $updated->json('data.attributes.allowed_roles'));
        self::assertSame([$this->allowedRole->id], $this->get('/api/hawki/v1/admin-models?filter[search]=' . $values['model_id'])
            ->assertOk()->json('data.0.attributes.allowed_roles'));
    }

    public function testSystemSlotsRejectRestrictedModelsInBothDirections(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin);
        DB::table('system_models')->where(['model_type' => 'summary', 'usage_type' => 'main'])->delete();

        $this->postAdminResource('/api/hawki/v1/admin-system-models', ['values' => [
            'model_type' => 'summary',
            'usage_type' => 'main',
            'model_id' => $this->restrictedModel->model_id,
        ]])->assertUnprocessable()->assertJsonPath('errors.0.source.pointer', '/model_id');

        $values = $this->adminModelValues('admin-system-' . $admin->id);
        $id = $this->postAdminResource('/api/hawki/v1/admin-models', ['values' => $values])
            ->assertCreated()->json('data.id');
        $this->postAdminResource('/api/hawki/v1/admin-system-models', ['values' => [
            'model_type' => 'summary',
            'usage_type' => 'main',
            'model_id' => $values['model_id'],
        ]])->assertCreated();
        $row = $this->get('/api/hawki/v1/admin-models?filter[search]=' . $values['model_id'])->assertOk()->json('data.0');
        $values['allowed_roles'] = [$this->allowedRole->id];

        $this->patchAdminResource('/api/hawki/v1/admin-models/' . $id, [
            'version' => $row['meta']['version'],
            'values' => $values,
        ])->assertUnprocessable()->assertJsonPath('errors.0.source.pointer', '/allowed_roles');
    }

    private function createModel(string $suffix): AiModel
    {
        $model = AiModel::create([
            'model_id' => 'model-access-' . $suffix . '-' . $this->actor->id,
            'label' => ucfirst($suffix),
            'provider_id' => $this->provider->id,
            'active' => true,
            'status' => OnlineStatus::ONLINE,
            'flags' => \App\Services\Ai\Models\Flags\Values\AiModelFlags::fromArray([]),
            'limits' => new \App\Services\Ai\Models\Limits\Values\NullAiModelLimits(),
            'pricing' => new \App\Services\Ai\Models\Pricing\Values\NullPricing(),
            'settings' => AiModelSettings::fromArray(['tool_calling' => true]),
            'native_capabilities' => NativeAiModelCapabilities::fromArray([]),
        ]);
        $model->usageRules()->create(['usage_type' => WellKnownUsageTypes::MAIN_APP]);

        return $model;
    }

    private function contextFor(AiModel $model): AgentRequestContext
    {
        return new AgentRequestContext(
            app(AiProviderProxyResolver::class)->resolve($this->provider),
            $model,
            new AiModelParameters(),
            actorId: $this->actor->id
        );
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'model-admin-' . $user->id,
            'display_name' => 'Model admin',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(Permission::values());
        app(RoleAssignmentService::class)->replace($user, [$role->id]);

        return $user;
    }

    private function adminModelValues(string $modelId): array
    {
        return [
            'label' => 'Admin model',
            'model_id' => $modelId,
            'provider_id' => $this->provider->id,
            'active' => true,
            'model_type' => 'chat',
            'input' => ['text'],
            'output' => ['text'],
            'tools' => [],
            'usage_rules' => ['main'],
            'allowed_roles' => [],
        ];
    }
}
