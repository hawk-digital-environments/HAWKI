<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Ai;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Support\ContextualScopeStatics;
use Tests\TestCase;

/**
 * The provider's models relation is unscoped on purpose (admin surfaces and
 * the status updater need the full catalog) — but exempting the relation's
 * own query must never disable AiModel's contextual scopes for the rest of
 * the process, the leak this test guards against.
 */
#[CoversNothing()]
class AiProviderModelsScopeLeakTest extends TestCase
{
    use RefreshDatabase;

    private \Closure $restoreScopeStatics;

    protected function setUp(): void
    {
        parent::setUp();

        // The assertions need AiModel's contextual scopes registered.
        $this->restoreScopeStatics = ContextualScopeStatics::rebind(AiModel::class);
    }

    protected function tearDown(): void
    {
        ($this->restoreScopeStatics)();

        parent::tearDown();
    }

    public function testTheModelsRelationDoesNotDisableAiModelScopes(): void
    {
        $providerId = DB::table('ai_providers')->insertGetId([
            'provider_id' => 'leak-test-provider',
            'name' => 'Leak Test Provider',
            'adapter_key' => 'openAi',
            'active' => true,
        ]);

        $this->seedModel($providerId, 'leak-test-offered', activeModel: true, withUsageRule: true);
        $this->seedModel($providerId, 'leak-test-retired', activeModel: false, withUsageRule: false);

        $provider = AiProvider::first();

        // Touching the relation like any serialization or status-check path.
        static::assertCount(2, $provider->models);

        // The scoped catalog still hides the retired model — before the fix,
        // the relation's scope disable leaked into this query.
        $offered = AiModel::query()->pluck('model_id');

        static::assertCount(1, $offered);
        static::assertSame(['leak-test-offered'], $offered->all());
    }

    private function seedModel(int $providerId, string $modelId, bool $activeModel, bool $withUsageRule): void
    {
        $modelPk = DB::table('ai_models')->insertGetId([
            'provider_id' => $providerId,
            'model_id' => $modelId,
            'label' => 'Leak Test Model ' . $modelId,
            'active' => $activeModel,
            'native_capabilities' => '[]',
            'limits' => '{}',
            'pricing' => '{}',
            'flags' => '[]',
            'settings' => '{}',
        ]);

        if ($withUsageRule) {
            DB::table('ai_model_usage_rules')->insert(['ai_model_id' => $modelPk, 'usage_type' => 'main']);
        }
    }
}
