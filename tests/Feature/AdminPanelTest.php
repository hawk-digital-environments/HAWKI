<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ai\AiModel;
use App\Models\User;
use App\Services\Admin\EmployeeTypeRoleSyncer;
use App\Services\Admin\Permission;
use App\Services\Admin\PermissionService;
use App\Services\Admin\Repositories\RoleRepository;
use App\Services\Admin\Repositories\UserRepository;
use App\Services\Admin\ResourceCatalog;
use App\Services\Admin\SystemSettings;
use App\Services\Admin\UsageStatistics;
use App\Services\Ai\ModelInformation\ModelInfoFetcher;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Io\Values\AiModelIoMethods;
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
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

    public function testPanelAccessDoesNotGrantSectionAccess(): void
    {
        $this->actingAs($this->grant(['admin.access']));

        foreach (ResourceCatalog::SECTIONS as $section => $permission) {
            $this->get('/api/hawki/v1/admin-' . $section)->assertForbidden();

            if (!\in_array($section, ['tools', 'settings', 'usage', 'health', 'environment'], true)) {
                $this->saveAdmin(['section' => $section, 'values' => ['name' => 'Forbidden']])->assertForbidden();
            }

            if (!\in_array($section, ['users', 'tools', 'usage', 'health', 'environment'], true)) {
                $this->deleteAdmin(['section' => $section, 'id' => '1'])->assertForbidden();
            }
        }
    }

    public function testSectionPermissionAlsoRequiresPanelAccess(): void
    {
        $this->actingAs($this->grant(['roles.manage']))->get('/api/hawki/v1/admin-roles')->assertForbidden();
    }

    public function testAdministratorCanReadAllConfigurationSections(): void
    {
        $this->actingAs($this->grant(Permission::values()));

        foreach (array_diff(array_keys(ResourceCatalog::SECTIONS), ['health']) as $section) {
            $this->get('/api/hawki/v1/admin-' . $section)->assertSuccessful()->assertHeader('Content-Type', 'application/vnd.api+json')->assertJsonStructure(['data', 'meta' => ['columns']])->assertJsonMissingPath('content');
        }
        $this->get('/api/hawki/v1/admin-settings')->assertOk()
            ->assertJsonPath('data.0.type', 'admin-settings')
            ->assertJsonStructure(['data' => [['attributes' => ['kind']]]])
            ->assertJsonMissingPath('data.0.attributes.type');
    }

    public function testAnnouncementsTargetRolesButNoLongerIndividualUsers(): void
    {
        $this->actingAs($this->grant(['admin.access', 'announcements.manage']));
        $fields = collect($this->get('/api/hawki/v1/admin-announcements')->assertSuccessful()->json('meta.fields'));
        self::assertNull($fields->firstWhere('key', 'target_users'));
        self::assertSame('multi', $fields->firstWhere('key', 'target_roles')['type']);
    }

    public function testAdministratorCanCreateAndResetALocalAccount(): void
    {
        $this->actingAs($this->grant(Permission::values()));
        $password = 'correct horse battery staple';
        $values = [
            'name' => 'Local User',
            'username' => 'local-user',
            'email' => 'local-user@example.test',
            'employeetype' => 'guest',
            'password' => $password,
            'password_confirmation' => $password,
            'admin_disabled' => false,
            'roles' => [],
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
                'roles' => [],
            ],
        ])->assertSuccessful();

        $user->refresh();
        self::assertSame('Renamed Local User', $user->name);
        self::assertTrue(Hash::check($replacement, $user->local_password));
        self::assertStringNotContainsString($replacement, DB::table('admin_audit_log')->orderByDesc('id')->value('changes'));
    }

    public function testLocalAccountCreationRequiresUserManagementPermission(): void
    {
        $this->actingAs($this->grant(['admin.access', 'users.view']));
        $this->get('/api/hawki/v1/admin-users')->assertSuccessful()
            ->assertJsonPath('meta.create', false);
        $this->saveAdmin([
            'section' => 'users',
            'values' => [
                'name' => 'Forbidden Local User',
                'username' => 'forbidden-local-user',
                'email' => 'forbidden@example.test',
                'employeetype' => 'guest',
                'password' => 'correct horse battery staple',
                'password_confirmation' => 'correct horse battery staple',
            ],
        ])->assertForbidden();
    }

    public function testLocalAccountCreationCannotEscalateThroughEmployeeType(): void
    {
        $this->actingAs($this->grant(['admin.access', 'users.view', 'users.manage']));
        $password = 'correct horse battery staple';
        $this->saveAdmin([
            'section' => 'users',
            'values' => [
                'name' => 'Escalated Local User',
                'username' => 'escalated-local-user',
                'email' => 'escalated@example.test',
                'employeetype' => 'admin',
                'password' => $password,
                'password_confirmation' => $password,
            ],
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['username' => 'escalated-local-user']);
    }

    public function testAdministratorCanCreateAnOpenAiLikeProvider(): void
    {
        $this->actingAs($this->grant(['admin.access', 'providers.manage']));
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $values = ['name' => 'JLU', 'provider_id' => 'admin-openai-like-test', 'adapter_key' => 'openai_like', 'active' => true];

        $id = $this->postAdminResource('/api/hawki/v1/admin-providers', ['values' => $values])
            ->assertCreated()->json('data.id');

        $this->assertDatabaseHas('ai_providers', ['id' => $id] + $values);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function testProviderSecretsDoNotRoundTripAndBlankKeepsTheKey(): void
    {
        $this->actingAs($this->grant(Permission::values()));
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

    public function testMappingChangesReplaceDerivedGrantsAndKeepManualGrants(): void
    {
        $user = $this->grant(['usage.view']);
        $user->forceFill(['employeetype' => 'staff'])->save();
        $adminRole = DB::table('roles')->where('slug', 'admin')->value('id');
        DB::table('employee_type_role_mappings')->insert(['employee_type' => 'staff', 'role_id' => $adminRole]);
        app(EmployeeTypeRoleSyncer::class)->sync($user);
        self::assertTrue(app(PermissionService::class)->has($user, 'admin.access'));
        $user->forceFill(['employeetype' => 'student'])->save();
        app(EmployeeTypeRoleSyncer::class)->sync($user);
        self::assertFalse(app(PermissionService::class)->has($user, 'admin.access'));
        self::assertTrue(app(PermissionService::class)->has($user, 'usage.view'));
    }

    public function testRoleManagerCannotGrantPermissionsTheyDoNotHold(): void
    {
        $this->actingAs($this->grant(['admin.access', 'roles.manage']));
        $this->saveAdmin(['section' => 'roles', 'values' => ['slug' => 'escalated', 'name' => 'Escalated', 'permissions' => ['settings.manage']]])->assertUnprocessable();
        $this->assertDatabaseMissing('roles', ['slug' => 'escalated']);
    }

    public function testUsagePerUserRequiresSeparatePermission(): void
    {
        $this->actingAs($this->grant(['admin.access', 'usage.view']));
        $this->get('/api/hawki/v1/admin-usage?filter[group_by]=user')->assertForbidden();
        $this->get('/api/hawki/v1/admin-usage?filter[user]=1')->assertForbidden();
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

        $this->actingAs($this->grant(Permission::values()))->saveAdmin(['section' => 'settings', 'id' => 'APP_KEY', 'values' => ['value' => 'forbidden']])->assertNotFound();
    }

    public function testBuiltInRolesKeepSlugButStayEditable(): void
    {
        $this->actingAs($this->grant(Permission::values()));
        $id = (int) DB::table('roles')->where('slug', 'user')->value('id');
        $version = static fn () => app(RoleRepository::class)->version((array) DB::table('roles')->find($id));
        $values = ['slug' => 'user', 'name' => 'Members', 'description' => 'Everyone', 'permissions' => ['usage.view']];
        $this->saveAdmin(['section' => 'roles', 'id' => (string) $id, 'version' => $version(), 'values' => $values])->assertSuccessful();
        self::assertSame('Members', DB::table('roles')->find($id)->name);
        self::assertSame(['usage.view'], DB::table('role_permissions')->where('role_id', $id)->pluck('permission')->all());
        $this->saveAdmin(['section' => 'roles', 'id' => (string) $id, 'version' => $version(), 'values' => ['slug' => 'members'] + $values])->assertUnprocessable();
        self::assertSame('user', DB::table('roles')->find($id)->slug);
        $this->deleteAdmin(['section' => 'roles', 'id' => (string) $id, 'version' => $version()])->assertUnprocessable();
    }

    public function testStaleEditsAreRejected(): void
    {
        $this->actingAs($this->grant(Permission::values()));
        $id = DB::table('roles')->insertGetId(['slug' => 'concurrency', 'name' => 'First']);
        $version = app(RoleRepository::class)->version((array) DB::table('roles')->find($id));
        DB::table('roles')->where('id', $id)->update(['name' => 'Second']);
        $this->saveAdmin(['section' => 'roles', 'id' => (string) $id, 'version' => $version, 'values' => ['slug' => 'concurrency', 'name' => 'Third', 'permissions' => []]])->assertStatus(412);
    }

    public function testResourceMutationDocumentsAndConditionalWrites(): void
    {
        $this->actingAs($this->grant(Permission::values()));
        $headers = ['Content-Type' => 'application/vnd.api+json'];
        $document = ['data' => ['type' => 'admin-roles', 'attributes' => [
            'slug' => 'jsonapi-write', 'name' => 'Original', 'permissions' => [],
        ]]];
        $this->postJson('/api/hawki/v1/admin-roles', $document)->assertStatus(415);
        $this->postJson('/api/hawki/v1/admin-roles', ['values' => ['name' => 'Old contract']], $headers)->assertUnprocessable();
        $created = $this->postJson('/api/hawki/v1/admin-roles', $document, $headers)->assertCreated()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertJsonPath('data.type', 'admin-roles')->assertJsonPath('data.attributes.name', 'Original');
        $id = $created->json('data.id');
        $etag = $created->headers->get('ETag');
        self::assertSame('"' . $created->json('data.meta.version') . '"', $etag);
        $url = '/api/hawki/v1/admin-roles/' . $id;
        $document['data']['id'] = $id;
        $document['data']['attributes']['name'] = 'Updated';

        $wrongType = $document;
        $wrongType['data']['type'] = 'admin-providers';
        $this->patchJson($url, $wrongType, $headers + ['If-Match' => $etag])->assertConflict();
        $wrongId = $document;
        $wrongId['data']['id'] = 'different';
        $this->patchJson($url, $wrongId, $headers + ['If-Match' => $etag])->assertConflict();
        $updated = $this->patchJson($url, $document, $headers + ['If-Match' => $etag])->assertOk()
            ->assertJsonPath('data.id', $id)->assertJsonPath('data.attributes.name', 'Updated');
        self::assertNotSame($etag, $updated->headers->get('ETag'));
        $this->patchJson($url, $document, $headers + ['If-Match' => $etag])->assertStatus(412);
        $this->deleteJson($url, [], $headers + ['If-Match' => $updated->headers->get('ETag')])->assertNoContent();
    }

    public function testTableFiltersSupportPaginationSortingAndSearch(): void
    {
        $this->actingAs($this->grant(Permission::values()));
        DB::table('roles')->insert([['slug' => 'table-a', 'name' => 'Table Alpha'], ['slug' => 'table-b', 'name' => 'Table Beta']]);
        $query = http_build_query(['page' => ['number' => 2, 'size' => 1], 'sort' => 'name', 'filter' => ['search' => 'Table ']]);
        $response = $this->get('/api/hawki/v1/admin-roles?' . $query)->assertSuccessful()
            ->assertJsonPath('meta.page.total', 2)->assertJsonPath('meta.page.currentPage', 2)
            ->assertJsonPath('meta.page.perPage', 1)->assertJsonPath('data.0.type', 'admin-roles')
            ->assertJsonPath('data.0.attributes.name', 'Table Beta')->assertJsonPath('links.next', null)
            ->assertJsonMissingPath('data.0.attributes.id')->assertJsonMissingPath('data.0.attributes._version');
        self::assertIsString($response->json('data.0.id'));
        self::assertIsString($response->json('data.0.meta.version'));
        $this->get($response->json('links.prev'))->assertOk()->assertJsonPath('data.0.attributes.name', 'Table Alpha');
        $this->get('/api/hawki/v1/admin-roles?' . http_build_query([
            'sort' => '-name', 'filter' => ['search' => 'Table '], 'page' => ['size' => 1],
        ]))->assertOk()->assertJsonPath('data.0.attributes.name', 'Table Beta');
    }

    public function testCollectionsValidateQueriesAndRepresentEmptyResults(): void
    {
        $this->actingAs($this->grant(Permission::values()));
        foreach (['page[number]=0', 'page[size]=101', 'sort=unknown', 'sort=name,-id'] as $query) {
            $this->get('/api/hawki/v1/admin-roles?' . $query)->assertUnprocessable();
        }
        $this->get('/api/hawki/v1/admin-roles?filter[search]=no-such-role-jsonapi')
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.page.total', 0)
            ->assertJsonPath('links.next', null)->assertJsonStructure(['meta' => ['fields']]);
    }

    public function testHealthCollectionCarriesReportMetadataWithoutPagination(): void
    {
        $this->actingAs($this->grant(['admin.access', 'health.view']));
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
        $this->actingAs($this->grant(Permission::values()));
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

    public function testPermissionOnlyChangesInvalidateTheVersion(): void
    {
        $user = $this->grant(Permission::values());
        $this->actingAs($user);
        $id = DB::table('roles')->insertGetId(['slug' => 'pivot-version', 'name' => 'Pivot version']);
        $version = app(RoleRepository::class)->version((array) DB::table('roles')->find($id));
        DB::table('role_permissions')->insert(['role_id' => $id, 'permission' => 'usage.view']);
        $this->saveAdmin(['section' => 'roles', 'id' => (string) $id, 'version' => $version, 'values' => ['slug' => 'pivot-version', 'name' => 'Pivot version', 'permissions' => []]])->assertStatus(412);
    }

    public function testAdministratorsCannotRemoveTheirOwnPanelAccess(): void
    {
        $user = $this->grant(Permission::values());
        $this->actingAs($user);
        $version = app(UserRepository::class)->version((array) DB::table('users')->find($user->id));
        $this->saveAdmin(['section' => 'users', 'id' => (string) $user->id, 'version' => $version, 'values' => ['roles' => []]])->assertUnprocessable();
        self::assertTrue(app(PermissionService::class)->has($user, Permission::ACCESS));
    }

    public function testDisabledAdministratorCannotReadThePanel(): void
    {
        $user = $this->grant(Permission::values());
        $user->forceFill(['admin_disabled' => true])->save();
        $this->actingAs($user)->get('/api/hawki/v1/admin-roles')->assertForbidden();
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
        $this->actingAs($this->grant(Permission::values()));
        $providerId = $this->saveAdmin(['section' => 'providers', 'values' => ['name' => 'Config test', 'provider_id' => 'config-test', 'adapter_key' => 'openai', 'active' => true]])->assertSuccessful()->json('data.id');
        $values = ['label' => 'Config test', 'model_id' => 'admin-config-test', 'provider_id' => (int) $providerId, 'active' => true, 'model_type' => 'chat', 'input' => ['text'], 'output' => ['text'], 'parameters' => ['temperature' => 0.5], 'tools' => [], 'usage_rules' => ['main']];
        $id = $this->saveAdmin(['section' => 'models', 'values' => $values])->assertSuccessful()->json('data.id');
        $row = $this->get('/api/hawki/v1/admin-models?filter[search]=admin-config-test')->assertSuccessful()->json('data.0');
        self::assertSame(['text'], $row['attributes']['input']);
        self::assertSame(['main'], $row['attributes']['usage_rules']);
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
        $this->assertDatabaseMissing('system_prompts', [
            'prompt_type' => 'summary',
            'usage_type' => 'main',
            'locale' => 'de_DE',
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
        $this->actingAs($this->grant(Permission::values()));
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

    public function testResourceRoutesRejectBodyDispatchAndRequireVersions(): void
    {
        $this->actingAs($this->grant(['admin.access', 'roles.manage']));
        $values = ['slug' => 'route-contract', 'name' => 'Route contract', 'permissions' => []];
        $this->postAdminResource('/api/hawki/v1/admin-roles', ['section' => 'providers', 'values' => $values])->assertUnprocessable();
        $id = $this->postAdminResource('/api/hawki/v1/admin-roles', ['values' => $values])->assertCreated()->json('data.id');
        $url = '/api/hawki/v1/admin-roles/' . $id;
        $row = $this->get('/api/hawki/v1/admin-roles?filter[search]=route-contract')->assertOk()->json('data.0');
        $this->patchAdminResource($url, ['values' => $values])->assertStatus(412);
        $this->deleteAdminResource($url)->assertStatus(412);
        $this->patchAdminResource($url, ['version' => $row['meta']['version'], 'values' => ['name' => 'Updated'] + $values])->assertOk();
        $this->deleteAdminResource($url, ['version' => $row['meta']['version']])->assertStatus(412);
        $version = app(RoleRepository::class)->version((array) DB::table('roles')->find($id));
        $this->deleteAdminResource($url, ['version' => $version])->assertNoContent();
        $this->assertDatabaseMissing('roles', ['id' => $id]);
        $this->assertDatabaseHas('admin_audit_log', ['resource_type' => 'roles', 'resource_id' => $id, 'action' => 'delete']);
    }

    public function testAdminBindingChecksPermissionsBeforeRecordExistence(): void
    {
        $url = '/api/hawki/v1/admin-roles/999999999';
        $this->actingAs($this->grant(['admin.access']));
        $this->patchAdminResource($url, ['values' => []])->assertForbidden();

        $this->actingAs($this->grant(['admin.access', 'roles.manage']));
        $this->patchAdminResource($url, ['values' => []])->assertNotFound();
        $this->deleteAdminResource($url)->assertNotFound();
    }

    public function testInspectReturnsProviderModelMetadataShapedLikeTheEditorFields(): void
    {
        $this->actingAs($this->grant(Permission::values()));
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

    public function testResourceUpdateRoutesRequireTheirOwnPermissions(): void
    {
        $this->actingAs($this->grant(['admin.access']));

        foreach (['providers', 'models', 'system-models', 'mcp', 'tools', 'users', 'roles', 'mappings', 'announcements', 'settings'] as $resource) {
            $this->patchAdminResource('/api/hawki/v1/admin-' . $resource . '/1', ['values' => ['name' => 'Forbidden']])->assertForbidden();
        }

        foreach (['providers/1/actions/test', 'providers/1/actions/discover', 'providers/1/actions/inspect', 'providers/actions/import', 'models/1/actions/refresh', 'mcp/1/actions/test', 'mcp/1/actions/discover', 'users/1/actions/revoke-tokens', 'health/actions/check-ai-status', 'health/1/actions/retry-job', 'health/actions/flush-jobs'] as $path) {
            $this->postJson('/api/hawki/v1/admin-' . $path)->assertForbidden();
        }

        $this->get('/api/hawki/v1/admin-users/1/actions/tokens')->assertForbidden();
    }

    public function testRemovedDispatchEndpointsAndUnsupportedOperationsAreUnavailable(): void
    {
        $this->actingAs($this->grant(Permission::values()));

        foreach (['save', 'remove', 'run'] as $action) {
            $this->postJson('/api/hawki/v1/admin-sections/actions/' . $action, ['section' => 'roles', 'values' => ['name' => 'Unexpected']])->assertNotFound();
        }

        $this->get('/api/hawki/v1/admin-sections/roles')->assertNotFound();

        foreach (['usage', 'health', 'environment', 'settings', 'tools'] as $resource) {
            $this->postJson('/api/hawki/v1/admin-' . $resource, ['values' => ['name' => 'Unexpected']])->assertStatus(405);
        }

        foreach (['users', 'tools'] as $resource) {
            $this->deleteAdminResource('/api/hawki/v1/admin-' . $resource . '/1')->assertStatus(405);
        }

        $this->patchAdminResource('/api/hawki/v1/admin-roles/not-an-id', ['values' => ['name' => 'Invalid']])->assertNotFound();
    }

    public function testUserTokenActionsUseTheUserInTheRoute(): void
    {
        $user = $this->grant(['users.view']);
        $token = $user->createToken('admin-route-test');
        $this->actingAs($this->grant(Permission::values()));
        $url = '/api/hawki/v1/admin-users/' . $user->id . '/actions/';
        $this->get($url . 'tokens')->assertOk()->assertJsonFragment(['name' => 'admin-route-test']);
        $this->postJson($url . 'revoke-tokens', ['id' => '1'])->assertUnprocessable();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->postJson($url . 'revoke-tokens')->assertOk()->assertJsonPath('revoked', 1);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function testMappingRepositoryResyncsUsersOnCreateUpdateAndDelete(): void
    {
        $user = $this->grant(['usage.view']);
        $user->forceFill(['employeetype' => 'route-mapping-staff'])->save();
        $role = DB::table('roles')->insertGetId(['slug' => 'route-mapped', 'name' => 'Mapped role']);
        DB::table('role_permissions')->insert(['role_id' => $role, 'permission' => 'health.view']);
        $this->actingAs($this->grant(Permission::values()));
        $values = ['employee_type' => 'route-mapping-staff', 'role_id' => $role];
        $id = $this->postAdminResource('/api/hawki/v1/admin-mappings', ['values' => $values])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('role_user', ['user_id' => $user->id, 'role_id' => $role, 'source' => 'employeetype']);
        $row = $this->get('/api/hawki/v1/admin-mappings?filter[search]=route-mapping-staff')->assertOk()->json('data.0');
        $this->patchAdminResource('/api/hawki/v1/admin-mappings/' . $id, ['version' => $row['meta']['version'], 'values' => ['employee_type' => 'route-mapping-renamed'] + $values])->assertOk();
        $this->assertDatabaseMissing('role_user', ['user_id' => $user->id, 'role_id' => $role]);
        self::assertTrue(app(PermissionService::class)->has($user, 'usage.view'));
        $row = $this->get('/api/hawki/v1/admin-mappings?filter[search]=route-mapping-renamed')->assertOk()->json('data.0');
        $this->deleteAdminResource('/api/hawki/v1/admin-mappings/' . $id, ['version' => $row['meta']['version']])->assertNoContent();
        $this->assertDatabaseMissing('employee_type_role_mappings', ['id' => $id]);
    }

    public function testAnnouncementRepositoryPreservesContentAndPublicationRules(): void
    {
        $this->actingAs($this->grant(Permission::values()));
        $values = ['title' => 'Route announcement', 'type' => 'news', 'is_published' => true, 'is_global' => true, 'is_forced' => false, 'target_roles' => [], 'content' => ['en_US' => 'Published content']];
        $id = $this->postAdminResource('/api/hawki/v1/admin-announcements', ['values' => $values])->assertCreated()->json('data.id');
        $row = $this->get('/api/hawki/v1/admin-announcements?filter[search]=Route%20announcement')->assertOk()->json('data.0');
        self::assertSame('Published content', $row['attributes']['content']['en_US']);
        $this->patchAdminResource('/api/hawki/v1/admin-announcements/' . $id, ['version' => $row['meta']['version'], 'values' => ['content' => ['en_US' => '']] + $values])->assertUnprocessable();
        $this->deleteAdminResource('/api/hawki/v1/admin-announcements/' . $id, ['version' => $row['meta']['version']])->assertNoContent();
        $this->assertDatabaseMissing('announcements', ['id' => $id]);
    }

    public function testMcpRepositoryValidatesTransportAndKeepsSecretsOnUpdate(): void
    {
        $this->actingAs($this->grant(Permission::values()));
        $values = ['server_label' => 'Route MCP', 'type' => 'http', 'url' => 'https://example.test/mcp', 'require_approval' => 'always', 'api_key' => 'mcp-route-secret'];
        $this->postAdminResource('/api/hawki/v1/admin-mcp', ['values' => ['url' => '/invalid'] + $values])->assertUnprocessable();
        $id = $this->postAdminResource('/api/hawki/v1/admin-mcp', ['values' => $values])->assertCreated()->json('data.id');
        $row = $this->get('/api/hawki/v1/admin-mcp?filter[search]=Route%20MCP')->assertOk()->assertDontSee('mcp-route-secret')->json('data.0');
        self::assertTrue($row['attributes']['api_key_set']);
        $this->patchAdminResource('/api/hawki/v1/admin-mcp/' . $id, ['version' => $row['meta']['version'], 'values' => ['api_key' => '', 'description' => 'Updated'] + $values])->assertOk();
        self::assertSame('mcp-route-secret', \App\Models\Ai\McpServer::findOrFail($id)->api_key);
        $row = $this->get('/api/hawki/v1/admin-mcp?filter[search]=Route%20MCP')->assertOk()->json('data.0');
        $this->deleteAdminResource('/api/hawki/v1/admin-mcp/' . $id, ['version' => $row['meta']['version']])->assertNoContent();
    }

    private function grant(array $permissions): User
    {
        $user = User::factory()->create();
        $role = DB::table('roles')->insertGetId(['slug' => 'test-' . $user->id, 'name' => 'Test']);

        foreach ($permissions as $permission) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission' => $permission]);
        }

        DB::table('role_user')->insert(['role_id' => $role, 'user_id' => $user->id, 'source' => 'manual']);

        return $user;
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
