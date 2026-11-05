<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ConfigFileSync\Syncers;

use App\Collections\AiModelCollection;
use App\Collections\SystemModelCollection;
use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\ConfigFileSync\Syncers\SystemModelSyncer;
use App\Services\Ai\Models\Identifiers\ModelFinder;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Providers\Repositories\AiProviderRepository;
use App\Services\Ai\SystemModels\SystemModelRepository;
use App\Services\Ai\SystemModels\Values\WellKnownSystemModelTypes;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use App\Utils\JobMetrics;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Tests\TestCase;

#[CoversClass(SystemModelSyncer::class)]
class SystemModelSyncerTest extends TestCase
{
    private AiProviderRepository&MockObject $providerRepository;
    private AiModelRepository&MockObject $modelRepository;
    private SystemModelRepository&MockObject $systemModelRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->providerRepository = $this->createMock(AiProviderRepository::class);
        $this->providerRepository->method('makeScopeOverrides')->willReturn(new ScopeOverrides());
        $this->modelRepository = $this->createMock(AiModelRepository::class);
        $this->modelRepository->method('makeScopeOverrides')->willReturn(new ScopeOverrides());
        $this->systemModelRepository = $this->createMock(SystemModelRepository::class);
        $this->systemModelRepository->method('findAllFiltered')->willReturn(new SystemModelCollection());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(array $systemModels): SystemModelSyncer
    {
        return new SystemModelSyncer(
            systemModels: $systemModels,
            extAppSystemModels: [],
            configRepository: new Repository([]),
            systemModelRepository: $this->systemModelRepository,
            modelRepository: $this->modelRepository,
            modelFinder: new ModelFinder($this->providerRepository, $this->modelRepository)
        );
    }

    private function makeModel(string $modelId, string $providerId): AiModel
    {
        $provider = new AiProvider();
        $provider->provider_id = $providerId;
        $model = new AiModel(['model_id' => $modelId]);
        $model->setRelation('provider', $provider);
        return $model;
    }

    // =========================================================================
    // sync
    // =========================================================================

    public function testItAssignsModelReferencedByQualifiedIdentifier(): void
    {
        $model = $this->makeModel('gemma-4-31b-it', 'google');
        $this->providerRepository->method('findOneByProviderId')->willReturn($model->provider);
        $this->modelRepository->method('findOneByProvider')->willReturn($model);

        $this->systemModelRepository->expects(static::once())->method('upsert')
            ->with(WellKnownSystemModelTypes::TITLE_GENERATION, WellKnownUsageTypes::MAIN_APP, $model);

        $metrics = new JobMetrics('test', new NullLogger());
        $this->makeSut(['title_generator' => 'google/gemma-4-31b-it'])->sync($metrics);

        static::assertFalse($metrics->hasErrors());
    }

    public function testItAssignsModelReferencedByUnambiguousPlainModelId(): void
    {
        $model = $this->makeModel('gpt-5-nano', 'openAi');
        $this->modelRepository->method('findAllByModelId')->willReturn(new AiModelCollection([$model]));

        $this->systemModelRepository->expects(static::once())->method('upsert')
            ->with(WellKnownSystemModelTypes::TITLE_GENERATION, WellKnownUsageTypes::MAIN_APP, $model);

        $metrics = new JobMetrics('test', new NullLogger());
        $this->makeSut(['title_generator' => 'gpt-5-nano'])->sync($metrics);

        static::assertFalse($metrics->hasErrors());
    }

    public function testItUsesTheFirstCreatedModelAndWarnsForAmbiguousPlainModelId(): void
    {
        $gwdgModel = $this->makeModel('gemma-4-31b-it', 'gwdg');
        $gwdgModel->id = 18;
        $googleModel = $this->makeModel('gemma-4-31b-it', 'google');
        $googleModel->id = 36;
        $this->modelRepository->method('findAllByModelId')
            ->willReturn(new AiModelCollection([$googleModel, $gwdgModel]));

        // The syncer resolves the ambiguity through the finder, which re-queries the first candidate
        $this->providerRepository->method('findOneByProviderId')->willReturn($gwdgModel->provider);
        $this->modelRepository->method('findOneByProvider')->willReturn($gwdgModel);

        $this->systemModelRepository->expects(static::once())->method('upsert')
            ->with(WellKnownSystemModelTypes::TITLE_GENERATION, WellKnownUsageTypes::MAIN_APP, $gwdgModel);

        $metrics = new JobMetrics('test', new NullLogger());
        $this->makeSut(['title_generator' => 'gemma-4-31b-it'])->sync($metrics);

        static::assertFalse($metrics->hasErrors());
        $warnings = array_filter($metrics->getLogs(), static fn(string $log) => str_starts_with($log, '[WARNING]'));
        static::assertCount(1, $warnings);
        static::assertStringContainsString("e.g. 'gwdg/gemma-4-31b-it'", reset($warnings));
    }
}
