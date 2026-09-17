<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiTool;
use App\Models\User;
use App\Services\Admin\Repositories\UserRepository;
use App\Services\Admin\ResourceCatalog;
use App\Services\Admin\SystemSettings;
use App\Services\Admin\UsageStatistics;
use App\Services\Ai\ModelInformation\ModelInfoFetcher;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Io\Values\AiModelIoMethods;
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\StatusCheck\ModelStatusUpdater;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AdminPanelTest extends TestCase
{
    use DatabaseTransactions;
    use \Tests\Support\AdminJsonApiRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Accept' => 'application/vnd.api+json']);
    }

    public function testAdministratorCanReadAllConfigurationSections(): void
    {
        $this->actingAs($this->administrator());

        foreach (array_diff(ResourceCatalog::SECTIONS, ['health']) as $section) {
            $this->get('/api/hawki/v1/admin-' . $section)->assertSuccessful()->assertHeader('Content-Type', 'application/vnd.api+json')->assertJsonStructure(['data', 'meta' => ['columns']])->assertJsonMissingPath('content');
        }

        $this->get('/api/hawki/v1/admin-settings')->assertOk()
            ->assertJsonPath('data.0.type', 'admin-settings')
            ->assertJsonStructure(['data' => [['attributes' => ['kind']]]])
            ->assertJsonMissingPath('data.0.attributes.type');
    }

    public function testAdministratorCanCreateAndResetALocalAccount(): void
    {
        $this->actingAs($this->administrator());
        $password = 'correct horse battery staple';
        $values = [
            'name' => 'Local User',
            'username' => 'local-user',
            'email' => 'local-user@example.test',
            'employeetype' => 'guest',
            'password' => $password,
            'password_confirmation' => $password,
            'admin_disabled' => false,
        ];

        $id = $this->saveAdmin([
            'section' => 'users',
            'values' => $values,
        ])->assertSuccessful()->json('data.id');

        $user = User::withoutGlobalScopes()->findOrFail($id);
        self::assertTrue(Hash::check($password, $user->local_password));
        self::assertSame('', $user->publicKey);

        $row = $this->get('/api/hawki/v1/admin-users?filter[search]=local-user')
            ->assertSuccessful()
            ->assertDontSee($password)
            ->assertDontSee($user->local_password)
            ->assertJsonPath('meta.create', true)
            ->assertJsonPath('data.0.attributes.local_account', true)
            ->json('data.0');

        $replacement = 'another correct horse battery';
        $this->saveAdmin([
            'section' => 'users',
            'id' => $id,
            'version' => $row['meta']['version'],
            'values' => [
                'name' => 'Renamed Local User',
                'username' => 'local-user',
                'email' => 'local-user@example.test',
                'employeetype' => 'guest',
                'password' => $replacement,
                'password_confirmation' => $replacement,
                'admin_disabled' => false,
            ],
        ])->assertSuccessful();

        $user->refresh();
        self::assertSame('Renamed Local User', $user->name);
        self::assertTrue(Hash::check($replacement, $user->local_password));
        self::assertStringNotContainsString($replacement, DB::table('admin_audit_log')->orderByDesc('id')->value('changes'));
    }

    public function testAdministratorCanReactivateADisabledAdministrator(): void
    {
        $admin = $this->administrator();
        $admin->forceFill(['admin_disabled' => true])->save();
        $this->actingAs($this->administrator());
        $version = app(UserRepository::class)->version((array) DB::table('users')->find($admin->id));

        $this->saveAdmin(['section' => 'users', 'id' => (string) $admin->id, 'version' => $version, 'values' => ['admin_disabled' => false]])->assertOk();
        self::assertFalse((bool) $admin->refresh()->admin_disabled);
    }

    public function testCustomProviderAdaptersAreOfferedAndAccepted(): void
    {
        app(\App\Services\Ai\Providers\Adapters\ProviderAdapterRegistry::class)->declare('custom-test-adapter', \Tests\Unit\Services\Ai\Providers\Adapters\ProviderAdapterRegistryTestFixtures\StubProviderAdapter::class);
        $this->actingAs($this->administrator());
        $fields = collect($this->get('/api/hawki/v1/admin-providers')->assertSuccessful()->json('meta.fields'));
        self::assertContains('custom-test-adapter', array_column($fields->firstWhere('key', 'adapter_key')['options'], 'value'));
        $values = ['name' => 'Custom', 'provider_id' => 'admin-custom-adapter-test', 'adapter_key' => 'custom-test-adapter', 'active' => false];
        $id = $this->saveAdmin(['section' => 'providers', 'values' => $values])->assertCreated()->json('data.id');
        $row = $this->get('/api/hawki/v1/admin-providers?filter[search]=admin-custom-adapter-test')->assertOk()->json('data.0');
        $this->saveAdmin(['section' => 'providers', 'id' => $id, 'version' => $row['meta']['version'], 'values' => ['name' => 'Custom renamed'] + $values])->assertOk();
        $this->saveAdmin(['section' => 'providers', 'values' => ['provider_id' => 'admin-unknown-adapter-test', 'adapter_key' => 'not-installed'] + $values])->assertUnprocessable();
    }

    public function testDeletingAModelRemovesItsDescriptions(): void
    {
        $this->actingAs($this->administrator());
        $providerId = $this->saveAdmin(['section' => 'providers', 'values' => ['name' => 'Cascade test', 'provider_id' => 'admin-cascade-test', 'adapter_key' => 'openai', 'active' => true]])->assertSuccessful()->json('data.id');
        $values = ['label' => 'Cascade', 'model_id' => 'admin-cascade-test-model', 'provider_id' => (int) $providerId, 'active' => true, 'model_type' => 'chat', 'input' => ['text'], 'output' => ['text'], 'tools' => [], 'usage_rules' => ['main'], 'descriptions' => ['en_US' => 'Described', 'de_DE' => 'Beschrieben']];
        $id = $this->saveAdmin(['section' => 'models', 'values' => $values])->assertSuccessful()->json('data.id');
        self::assertSame(2, DB::table('ai_model_descriptions')->where('ai_model_id', $id)->count());
        $row = $this->get('/api/hawki/v1/admin-models?filter[search]=admin-cascade-test-model')->assertOk()->json('data.0');
        $this->deleteAdmin(['section' => 'models', 'id' => $id, 'version' => $row['meta']['version']])->assertNoContent();
        $this->assertDatabaseMissing('ai_model_descriptions', ['ai_model_id' => $id]);
    }

    public function testImportsDoNotRestoreRecordsDeletedInAdministration(): void
    {
        $this->actingAs($this->administrator());
        $deleted = app(\App\Services\Admin\DeletedRecords::class);
        $metrics = static fn () => new \App\Utils\JobMetrics('test', app(\Psr\Log\LoggerInterface::class));

        $slot = (array) DB::table('system_models')->where('admin_managed', false)->orderBy('id')->first();
        $model = (array) DB::table('ai_models')->where('admin_managed', false)->whereNotIn('model_id', DB::table('system_models')->select('model_id'))->orderBy('id')->first();

        if (!$slot || !$model) {
            self::markTestSkipped('Needs configuration synced from the deployment files.');
        }

        $slotRow = $this->get('/api/hawki/v1/admin-system-models?' . http_build_query(['filter' => ['where' => ['model_type' => $slot['model_type'], 'usage_type' => $slot['usage_type']]]]))->assertOk()->json('data.0');
        $this->deleteAdmin(['section' => 'system-models', 'id' => $slot['id'], 'version' => $slotRow['meta']['version']])->assertNoContent();
        self::assertTrue($deleted->isDeleted('system-models', $slot['usage_type'] . ':' . $slot['model_type']));
        app(\App\Services\Ai\ConfigFileSync\Syncers\SystemModelSyncer::class)->sync($metrics());
        $this->assertDatabaseMissing('system_models', ['model_type' => $slot['model_type'], 'usage_type' => $slot['usage_type']]);

        $this->saveAdmin(['section' => 'system-models', 'values' => ['model_type' => $slot['model_type'], 'usage_type' => $slot['usage_type'], 'model_id' => $slot['model_id']]])->assertCreated();
        self::assertFalse($deleted->isDeleted('system-models', $slot['usage_type'] . ':' . $slot['model_type']));

        // The provider syncer needs the full container to build model settings, so the model path
        // is covered by the deletion record it consults; the syncer skips recorded model ids.
        $modelRow = $this->get('/api/hawki/v1/admin-models?filter[search]=' . rawurlencode($model['model_id']))->assertOk()->json('data.0');
        $this->deleteAdmin(['section' => 'models', 'id' => $model['id'], 'version' => $modelRow['meta']['version']])->assertNoContent();
        self::assertTrue($deleted->isDeleted('models', $model['model_id']));
        $this->saveAdmin(['section' => 'models', 'values' => ['label' => 'Restored', 'model_id' => $model['model_id'], 'provider_id' => (int) $model['provider_id'], 'active' => false, 'model_type' => 'chat', 'input' => ['text'], 'output' => ['text'], 'tools' => [], 'usage_rules' => ['main']]])->assertCreated();
        self::assertFalse($deleted->isDeleted('models', $model['model_id']));
    }

    public function testAdministratorCanCreateAnOpenAiLikeProvider(): void
    {
        $this->actingAs($this->administrator());
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $values = ['name' => 'JLU', 'provider_id' => 'admin-openai-like-test', 'adapter_key' => 'openai_like', 'active' => true];

        $id = $this->postAdminResource('/api/hawki/v1/admin-providers', ['values' => $values])
            ->assertCreated()->json('data.id');

        $this->assertDatabaseHas('ai_providers', ['id' => $id] + $values);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function testProviderSecretsDoNotRoundTripAndBlankKeepsTheKey(): void
    {
        $this->actingAs($this->administrator());
        $values = ['name' => 'Test provider', 'provider_id' => 'admin-test', 'adapter_key' => 'openai', 'active' => false, 'api_key' => 'very-secret', 'additional_config' => ['password' => 'nested-secret']];
        $response = $this->saveAdmin(['section' => 'providers', 'values' => $values])->assertSuccessful();
        $id = $response->json('data.id');
        $row = $this->get('/api/hawki/v1/admin-providers?filter[search]=admin-test')->assertSuccessful()->assertDontSee('very-secret')->assertDontSee('nested-secret')->json('data.0');
        self::assertTrue($row['attributes']['api_key_set']);
        $values['api_key'] = '';
        unset($values['additional_config']);
        $this->saveAdmin(['section' => 'providers', 'id' => $id, 'version' => $row['meta']['version'], 'values' => $values])->assertSuccessful();
        self::assertSame('very-secret', \App\Models\Ai\AiProvider::withoutGlobalScopes()->findOrFail($id)->api_key);
        self::assertStringNotContainsString('very-secret', DB::table('admin_audit_log')->orderByDesc('id')->value('changes'));
    }

    public function testRetentionPersistsTotalsBeforeRemovingRawRowsAndIsIdempotent(): void
    {
        $user = User::factory()->create();
        $date = now()->subMonths(5)->startOfMonth();
        DB::table('usage_records')->insert(['user_id' => $user->id, 'model' => 'admin-retention-test', 'type' => 'private', 'prompt_tokens' => 13, 'completion_tokens' => 7, 'created_at' => $date, 'updated_at' => $date]);
        app(UsageStatistics::class)->summarize();
        app(UsageStatistics::class)->summarize();
        $this->assertDatabaseMissing('usage_records', ['model' => 'admin-retention-test']);
        $this->assertDatabaseHas('usage_daily_totals', ['model' => 'admin-retention-test', 'requests' => 1, 'prompt_tokens' => 13, 'completion_tokens' => 7]);
    }

    public function testEverySettingOverlayUsesItsDeclaredConfigPath(): void
    {
        $user = User::factory()->create();

        foreach (SystemSettings::DEFINITIONS as $key => [$path, $type]) {
            $value = match ($key) {
                'APP_NAME' => 'Test application', 'APP_ENV' => 'testing', 'APP_TIMEZONE' => 'Europe/Berlin',
                'APP_LOCALE' => 'de_DE', 'AUTHENTICATION_METHOD' => 'LDAP',
                default => match ($type) {
                    'boolean' => false, 'number' => 60, 'json' => ['image/png'], default => 'AI_MENTION_HANDLE' === $key ? 'helper' : 'https://example.com/accessibility',
                },
            };
            app(SystemSettings::class)->save($key, $value, $user->id);
            self::assertSame('AI_MENTION_HANDLE' === $key ? '@helper' : $value, config($path));
        }

        $this->actingAs($this->administrator())->saveAdmin(['section' => 'settings', 'id' => 'APP_KEY', 'values' => ['value' => 'forbidden']])->assertNotFound();
    }

    public function testHealthCollectionCarriesReportMetadataWithoutPagination(): void
    {
        $this->actingAs($this->administrator());
        $this->mock(\App\Services\Admin\HealthMonitor::class, static function ($mock): void {
            $mock->shouldReceive('read')->once()->andReturn([
                'rows' => [['id' => 'database', 'name' => 'database', 'status' => 'ok']],
                'status' => 'ok', 'queues' => ['default' => 0], 'failed_jobs' => [],
            ]);
        });
        $this->get('/api/hawki/v1/admin-health')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertJsonPath('data.0.type', 'admin-health')->assertJsonPath('data.0.id', 'database')
            ->assertJsonPath('meta.status', 'ok')->assertJsonPath('meta.queues.default', 0)
            ->assertJsonMissingPath('meta.page');
    }

    public function testTableFiltersNarrowRowsToAColumnValue(): void
    {
        $this->actingAs($this->administrator());
        $providers = [];

        foreach (['a', 'b'] as $suffix) {
            $providers[] = $this->saveAdmin(['section' => 'providers', 'values' => ['name' => 'Where ' . $suffix, 'provider_id' => 'where-' . $suffix, 'adapter_key' => 'openai', 'active' => true]])->assertSuccessful()->json('data.id');
        }

        foreach ($providers as $providerId) {
            $values = ['label' => 'Where test', 'model_id' => 'where-test-' . $providerId, 'provider_id' => (int) $providerId, 'active' => true, 'model_type' => 'chat', 'tools' => [], 'usage_rules' => ['main']];
            $this->saveAdmin(['section' => 'models', 'values' => $values])->assertSuccessful();
        }

        $query = http_build_query(['filter' => ['search' => 'where-test', 'where' => ['provider_id' => $providers[0]]]]);
        $this->get('/api/hawki/v1/admin-models?' . $query)->assertSuccessful()
            ->assertJsonPath('meta.page.total', 1)->assertJsonPath('data.0.attributes.model_id', 'where-test-' . $providers[0]);
        $this->get('/api/hawki/v1/admin-providers?' . http_build_query(['filter' => ['where' => ['api_key_set' => '1']]]))->assertUnprocessable();
    }

    public function testSettingResetRestoresTheDeploymentValueInTheSameWorker(): void
    {
        $user = User::factory()->create();
        $settings = app(SystemSettings::class);
        $default = config('tools.check_tool_status');
        $settings->save('CHECK_TOOL_STATUS', !$default, $user->id);
        $settings->reset('CHECK_TOOL_STATUS');
        self::assertSame($default, config('tools.check_tool_status'));
    }

    public function testModelConfigurationPersistsStructuredFieldsAndProtectsSystemAssignments(): void
    {
        $this->actingAs($this->administrator());
        $providerId = $this->saveAdmin(['section' => 'providers', 'values' => ['name' => 'Config test', 'provider_id' => 'config-test', 'adapter_key' => 'openai', 'active' => true]])->assertSuccessful()->json('data.id');
        $values = ['label' => 'Config test', 'model_id' => 'admin-config-test', 'provider_id' => (int) $providerId, 'active' => true, 'model_type' => 'chat', 'input' => ['text'], 'output' => ['text'], 'parameters' => ['temperature' => 0.5], 'tools' => [], 'usage_rules' => ['main']];
        $id = $this->saveAdmin(['section' => 'models', 'values' => $values])->assertSuccessful()->json('data.id');
        $row = $this->get('/api/hawki/v1/admin-models?filter[search]=admin-config-test')->assertSuccessful()->json('data.0');
        self::assertSame(['text'], $row['attributes']['input']);
        self::assertSame(['main'], $row['attributes']['usage_rules']);
        self::assertSame('unknown', $row['attributes']['status']);
        DB::table('system_models')->where(['model_type' => 'summary', 'usage_type' => 'main'])->delete();
        $slot = ['section' => 'system-models', 'values' => [
            'model_type' => 'summary',
            'usage_type' => 'main',
            'model_id' => 'admin-config-test',
            'prompts' => ['en_US' => 'Summarize this chat.', 'de_DE' => 'Fasse diesen Chat zusammen.'],
        ]];
        $slotId = $this->saveAdmin($slot)->assertSuccessful()->json('data.id');
        $this->assertDatabaseHas('system_prompts', [
            'prompt_type' => 'summary',
            'usage_type' => 'main',
            'locale' => 'de_DE',
            'prompt' => 'Fasse diesen Chat zusammen.',
            'admin_managed' => true,
        ]);
        $systemRow = $this->get('/api/hawki/v1/admin-system-models?' . http_build_query([
            'filter' => ['where' => ['model_type' => 'summary', 'usage_type' => 'main']],
        ]))->assertSuccessful()
            ->assertJsonPath('data.0.attributes.prompts.en_US', 'Summarize this chat.')
            ->json('data.0');
        $slot['id'] = $slotId;
        $slot['version'] = $systemRow['meta']['version'];
        $slot['values']['prompts'] = ['en_US' => 'Updated summary prompt.', 'de_DE' => ''];
        $this->saveAdmin($slot)->assertSuccessful();
        $this->assertDatabaseHas('system_prompts', [
            'prompt_type' => 'summary',
            'usage_type' => 'main',
            'locale' => 'en_US',
            'prompt' => 'Updated summary prompt.',
        ]);
        $this->assertDatabaseHas('system_prompts', [
            'prompt_type' => 'summary',
            'usage_type' => 'main',
            'locale' => 'de_DE',
            'prompt' => '',
            'admin_managed' => true,
        ]);
        unset($slot['id'], $slot['version']);
        $this->saveAdmin($slot)->assertUnprocessable();
        $values['active'] = false;
        $this->saveAdmin(['section' => 'models', 'id' => $id, 'version' => $row['meta']['version'], 'values' => $values])->assertUnprocessable();
        $this->deleteAdmin(['section' => 'models', 'id' => $id, 'version' => $row['meta']['version']])->assertUnprocessable();
        $systemRow = $this->get('/api/hawki/v1/admin-system-models?' . http_build_query([
            'filter' => ['where' => ['model_type' => 'summary', 'usage_type' => 'main']],
        ]))->assertSuccessful()->json('data.0');
        $this->deleteAdmin([
            'section' => 'system-models',
            'id' => $slotId,
            'version' => $systemRow['meta']['version'],
        ])->assertNoContent();
        $this->assertDatabaseMissing('system_prompts', ['prompt_type' => 'summary', 'usage_type' => 'main']);
    }

    public function testModelEditorPersistsLocalizedDescriptions(): void
    {
        $this->actingAs($this->administrator());
        $providerId = $this->saveAdmin([
            'section' => 'providers',
            'values' => [
                'name' => 'Description test',
                'provider_id' => 'description-test',
                'adapter_key' => 'openai',
                'active' => true,
            ],
        ])->assertSuccessful()->json('data.id');
        $values = [
            'label' => 'Description test',
            'model_id' => 'description-test-model',
            'provider_id' => (int) $providerId,
            'descriptions' => ['en_US' => 'English description', 'de_DE' => 'Deutsche Beschreibung'],
            'active' => false,
            'model_type' => 'chat',
            'tools' => [],
            'usage_rules' => ['main'],
        ];
        $id = $this->saveAdmin([
            'section' => 'models',
            'values' => $values,
        ])->assertSuccessful()->json('data.id');

        $row = $this->get('/api/hawki/v1/admin-models?filter[search]=description-test-model')
            ->assertSuccessful()
            ->assertJsonPath('data.0.attributes.descriptions.en_US', 'English description')
            ->assertJsonPath('data.0.attributes.descriptions.de_DE', 'Deutsche Beschreibung')
            ->json('data.0');
        $this->assertDatabaseHas('ai_model_descriptions', [
            'ai_model_id' => $id,
            'locale' => 'en_US',
            'description' => 'English description',
            'admin_managed' => true,
        ]);

        $values['descriptions'] = ['en_US' => 'Updated description', 'de_DE' => ''];
        $this->saveAdmin([
            'section' => 'models',
            'id' => $id,
            'version' => $row['meta']['version'],
            'values' => $values,
        ])->assertSuccessful();
        $this->assertDatabaseHas('ai_model_descriptions', [
            'ai_model_id' => $id,
            'locale' => 'en_US',
            'description' => 'Updated description',
        ]);
        $this->assertDatabaseMissing('ai_model_descriptions', ['ai_model_id' => $id, 'locale' => 'de_DE']);
    }

    public function testInspectReturnsProviderModelMetadataShapedLikeTheEditorFields(): void
    {
        $this->actingAs($this->administrator());
        $providerId = $this->saveAdmin(['section' => 'providers', 'values' => ['name' => 'Inspect test', 'provider_id' => 'inspect-test', 'adapter_key' => 'openai', 'active' => true]])->assertSuccessful()->json('data.id');
        $info = new AiModel(['model_id' => 'inspect-model', 'label' => 'Inspect Model', 'model_type' => 'chat', 'documentation_url' => 'https://example.com/docs']);
        $info->input = AiModelIoMethods::fromArray(['text', 'image']);
        $info->flags = AiModelFlags::fromArray(['feature-streaming']);
        $info->limits = ChatAiModelLimits::fromArray(['max_input_tokens' => 128000, 'max_output_tokens' => 4096]);
        $this->mock(ModelInfoFetcher::class, static function ($mock) use ($info): void {
            $mock->shouldReceive('fetchSingle')->once()->withArgs(static fn ($proxy, string $modelId) => 'inspect-model' === $modelId)->andReturn($info);
        });
        $url = '/api/hawki/v1/admin-providers/' . $providerId . '/actions/inspect';
        $this->postJson($url, [])->assertUnprocessable();
        $this->postJson($url, ['model_id' => 'inspect-model'])->assertOk()
            ->assertJsonPath('model.label', 'Inspect Model')
            ->assertJsonPath('model.model_type', 'chat')
            ->assertJsonPath('model.input', ['text', 'image'])
            ->assertJsonPath('model.flags', ['feature-streaming'])
            ->assertJsonPath('model.limits.max_input_tokens', 128000)
            ->assertJsonMissingPath('model.pricing')
            ->assertJsonMissingPath('model.deprecation_date');
        $this->assertDatabaseHas('admin_audit_log', ['resource_type' => 'providers', 'resource_id' => $providerId, 'action' => 'inspect']);
    }

    public function testModelStatusCheckRunsSynchronously(): void
    {
        $this->app->instance(ModelStatusUpdater::class, new readonly class extends ModelStatusUpdater {
            public function __construct()
            {
            }

            public function run(): JobMetrics
            {
                return new JobMetrics('Test Model Status Update');
            }
        });
        $this->actingAs($this->administrator());

        $this->postJson('/api/hawki/v1/admin-models/actions/check-status')->assertOk()
            ->assertJsonPath('checked', true);
        $this->assertDatabaseHas('admin_audit_log', ['resource_type' => 'models', 'action' => 'check-status']);
    }

    public function testUserTokenActionsUseTheUserInTheRoute(): void
    {
        $user = $this->administrator();
        $token = $user->createToken('admin-route-test');
        $this->actingAs($this->administrator());
        $url = '/api/hawki/v1/admin-users/' . $user->id . '/actions/';
        $this->get($url . 'tokens')->assertOk()->assertJsonFragment(['name' => 'admin-route-test']);
        $this->postJson($url . 'revoke-tokens', ['id' => '1'])->assertUnprocessable();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->postJson($url . 'revoke-tokens')->assertOk()->assertJsonPath('revoked', 1);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function testAnnouncementRepositoryPreservesContentAndPublicationRules(): void
    {
        $this->actingAs($this->administrator());
        $values = ['title' => 'Route announcement', 'type' => 'news', 'is_published' => true, 'is_global' => true, 'is_forced' => false, 'target_users' => [], 'content' => ['en_US' => 'Published content']];
        $id = $this->postAdminResource('/api/hawki/v1/admin-announcements', ['values' => $values])->assertCreated()->json('data.id');
        $row = $this->get('/api/hawki/v1/admin-announcements?filter[search]=Route%20announcement')->assertOk()->json('data.0');
        self::assertSame('Published content', $row['attributes']['content']['en_US']);
        $this->patchAdminResource('/api/hawki/v1/admin-announcements/' . $id, ['version' => $row['meta']['version'], 'values' => ['content' => ['en_US' => '']] + $values])->assertUnprocessable();
        $this->deleteAdminResource('/api/hawki/v1/admin-announcements/' . $id, ['version' => $row['meta']['version']])->assertNoContent();
        $this->assertDatabaseMissing('announcements', ['id' => $id]);
    }

    public function testMcpRepositoryValidatesTransportAndKeepsSecretsOnUpdate(): void
    {
        $this->actingAs($this->administrator());
        $values = ['server_label' => 'Route MCP', 'type' => 'http', 'url' => 'https://example.test/mcp', 'require_approval' => 'always', 'api_key' => 'mcp-route-secret'];
        $this->postAdminResource('/api/hawki/v1/admin-mcp', ['values' => ['url' => '/invalid'] + $values])->assertUnprocessable();
        $id = $this->postAdminResource('/api/hawki/v1/admin-mcp', ['values' => $values])->assertCreated()->json('data.id');
        $row = $this->get('/api/hawki/v1/admin-mcp?filter[search]=Route%20MCP')->assertOk()->assertDontSee('mcp-route-secret')->json('data.0');
        self::assertTrue($row['attributes']['api_key_set']);
        self::assertSame('http', $row['attributes']['kind']);
        $this->get('/api/hawki/v1/admin-mcp?filter[search]=Route%20MCP&filter[where][kind]=http')->assertOk()->assertJsonCount(1, 'data');
        $this->get('/api/hawki/v1/admin-mcp?filter[search]=Route%20MCP&filter[where][kind]=sse')->assertOk()->assertJsonCount(0, 'data');
        $this->patchAdminResource('/api/hawki/v1/admin-mcp/' . $id, ['version' => $row['meta']['version'], 'values' => ['api_key' => '', 'description' => 'Updated'] + $values])->assertOk();
        self::assertSame('mcp-route-secret', \App\Models\Ai\McpServer::findOrFail($id)->api_key);
        $row = $this->get('/api/hawki/v1/admin-mcp?filter[search]=Route%20MCP')->assertOk()->json('data.0');
        $this->deleteAdminResource('/api/hawki/v1/admin-mcp/' . $id, ['version' => $row['meta']['version']])->assertNoContent();
    }

    public function testMcpServersExposeTheirToolsAndToolServerIsReadOnly(): void
    {
        $this->actingAs($this->administrator());
        $label = 'Route tools MCP';
        $serverId = $this->postAdminResource('/api/hawki/v1/admin-mcp', ['values' => ['server_label' => $label, 'type' => 'http', 'url' => 'https://example.test/route-tools', 'require_approval' => 'never']])->assertCreated()->json('data.id');
        $mcpTool = AiTool::create(['type' => 'mcp', 'name' => 'Route MCP tool', 'mcp_server_id' => $serverId, 'mcp_name' => 'route_mcp_tool', 'mcp_config' => ['inputSchema' => ['type' => 'object']], 'description' => 'MCP tool', 'active' => true]);
        AiTool::create(['type' => 'function', 'name' => 'Route function tool', 'description' => 'Function tool', 'active' => true]);

        $server = $this->get('/api/hawki/v1/admin-mcp?filter[search]=Route%20tools%20MCP')->assertOk()->json('data.0');
        self::assertSame(1, $server['attributes']['tools_count']);

        $tools = $this->get('/api/hawki/v1/admin-tools?filter[where][mcp_server_id]=' . $serverId)->assertOk()->assertJsonCount(1, 'data');
        $tool = $tools->json('data.0');
        self::assertSame((string) $mcpTool->id, $tool['id']);
        self::assertSame((int) $serverId, $tool['attributes']['mcp_server_id']);
        $field = collect($tools->json('meta.fields'))->firstWhere('key', 'mcp_server_id');
        self::assertSame('select', $field['type']);
        self::assertContains(['value' => (int) $serverId, 'label' => $label], $field['options']);

        $this->patchAdminResource('/api/hawki/v1/admin-tools/' . $mcpTool->id, ['version' => $tool['meta']['version'], 'values' => ['description' => 'MCP tool', 'active' => true, 'mapped_capability' => null, 'models' => [], 'mcp_server_id' => null]])->assertOk();
        self::assertSame((int) $serverId, AiTool::findOrFail($mcpTool->id)->mcp_server_id);
    }

    private function administrator(): User
    {
        return User::factory()->create(['employeetype' => 'admin']);
    }

    public function testEmployeeTypeControlsEveryPanelResource(): void
    {
        $this->actingAs(User::factory()->create(['employeetype' => 'staff']));
        foreach (ResourceCatalog::SECTIONS as $section) {
            $this->get('/api/hawki/v1/admin-' . $section)->assertForbidden();
        }
        $this->postAdminResource('/api/hawki/v1/admin-providers', ['values' => ['name' => 'Denied']])->assertForbidden();
        $this->patchAdminResource('/api/hawki/v1/admin-providers/999999999', ['values' => ['name' => 'Denied']])->assertForbidden();
    }

    public function testAdministratorCannotDisableOrDemoteTheirOwnAccount(): void
    {
        $actor = $this->administrator();
        $actor->forceFill(['local_password' => Hash::make('a long test password')])->save();
        $repository = app(UserRepository::class);
        foreach ([['admin_disabled' => true], ['employeetype' => 'staff']] as $values) {
            try {
                $repository->save($actor->id, $values, $actor);
                self::fail('Self-removal must be rejected.');
            } catch (\Illuminate\Validation\ValidationException) {
                self::assertSame('admin', $actor->fresh()->employeetype);
                self::assertFalse($actor->fresh()->admin_disabled);
            }
        }
    }

    private function saveAdmin(array $data): \Illuminate\Testing\TestResponse
    {
        $url = '/api/hawki/v1/admin-' . $data['section'];
        $id = $data['id'] ?? null;
        unset($data['section'], $data['id']);

        return null === $id ? $this->postAdminResource($url, $data) : $this->patchAdminResource($url . '/' . $id, $data);
    }

    private function deleteAdmin(array $data): \Illuminate\Testing\TestResponse
    {
        $url = '/api/hawki/v1/admin-' . $data['section'] . '/' . $data['id'];
        unset($data['section'], $data['id']);

        return $this->deleteAdminResource($url, $data);
    }
}
