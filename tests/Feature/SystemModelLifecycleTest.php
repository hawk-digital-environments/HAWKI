<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\Repositories\SystemModelRepository as AdminSystemModelRepository;
use App\Services\Ai\ConfigFileSync\Syncers\SystemModelSyncer;
use App\Services\Ai\ConfigFileSync\Syncers\SystemPromptSyncer;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Limits\Values\NullAiModelLimits;
use App\Services\Ai\Models\Pricing\Values\NullPricing;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\SystemModels\SystemModelRepository;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use App\Utils\JobMetrics;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class SystemModelLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function testConfigurationImportDoesNotRestoreAnExplicitlyClearedAdminPrompt(): void
    {
        $model = $this->createModel('clear-prompt');
        $actor = User::factory()->create();
        DB::table('system_models')->where([
            'model_type' => 'summary',
            'usage_type' => WellKnownUsageTypes::MAIN_APP,
        ])->delete();

        app(AdminSystemModelRepository::class)->save(null, [
            'model_type' => 'summary',
            'usage_type' => WellKnownUsageTypes::MAIN_APP,
            'model_id' => $model->model_id,
            'prompts' => ['en_US' => '', 'de_DE' => ''],
        ], $actor);

        self::assertSame(2, DB::table('system_prompts')->where([
            'prompt_type' => 'summary',
            'usage_type' => WellKnownUsageTypes::MAIN_APP,
        ])->count());

        app(SystemPromptSyncer::class)->sync($this->metrics());

        $prompts = DB::table('system_prompts')->where([
            'prompt_type' => 'summary',
            'usage_type' => WellKnownUsageTypes::MAIN_APP,
        ])->get(['prompt', 'admin_managed']);
        self::assertCount(2, $prompts);
        self::assertTrue($prompts->every(static fn ($prompt) => '' === $prompt->prompt && (bool) $prompt->admin_managed));
    }

    public function testConfigurationImportRejectsARestrictedModelForASystemSlot(): void
    {
        $model = $this->createModel('restricted-slot');
        $role = Role::create([
            'name' => 'restricted-system-slot-' . $model->id,
            'display_name' => 'Restricted system slot',
            'guard_name' => 'web',
        ]);
        $model->allowedRoles()->attach($role->id, ['created_at' => now()]);
        $metrics = $this->metrics();

        (new SystemModelSyncer(
            ['summarizer' => $model->model_id],
            [],
            app(ConfigRepository::class),
            app(SystemModelRepository::class),
            app(\App\Services\Ai\Models\Repositories\AiModelRepository::class),
            app(\App\Services\Admin\DeletedRecords::class),
        ))->sync($metrics);

        $this->assertDatabaseMissing('system_models', [
            'model_type' => 'summary',
            'usage_type' => WellKnownUsageTypes::MAIN_APP,
            'model_id' => $model->model_id,
        ]);
        self::assertTrue($metrics->hasErrors());
    }

    private function createModel(string $suffix): AiModel
    {
        $provider = AiProvider::create([
            'provider_id' => 'system-model-lifecycle-' . $suffix,
            'name' => 'System model lifecycle',
            'active' => true,
            'adapter_key' => 'openai',
            'api_url' => 'https://example.invalid',
            'api_key' => 'test-only',
        ]);
        $model = AiModel::create([
            'model_id' => 'system-model-lifecycle-' . $suffix,
            'label' => 'System model lifecycle',
            'provider_id' => $provider->id,
            'active' => true,
            'status' => OnlineStatus::ONLINE,
            'flags' => AiModelFlags::fromArray([]),
            'limits' => new NullAiModelLimits(),
            'pricing' => new NullPricing(),
            'settings' => AiModelSettings::fromArray(['tool_calling' => true]),
            'native_capabilities' => \App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities::fromArray([]),
        ]);
        $model->usageRules()->create(['usage_type' => WellKnownUsageTypes::MAIN_APP]);

        return $model;
    }

    private function metrics(): JobMetrics
    {
        return new JobMetrics('system-model-lifecycle-test', app(\Psr\Log\LoggerInterface::class));
    }
}
