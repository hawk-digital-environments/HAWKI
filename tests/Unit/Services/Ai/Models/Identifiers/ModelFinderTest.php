<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Models\Identifiers;

use App\Collections\AiModelCollection;
use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\AmbiguousModelIdException;
use App\Services\Ai\Exceptions\ModelIdNotAvailableException;
use App\Services\Ai\Models\Identifiers\ModelFinder;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Providers\Repositories\AiProviderRepository;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(ModelFinder::class)]
class ModelFinderTest extends TestCase
{
    private AiProviderRepository&MockObject $providerRepository;
    private AiModelRepository&MockObject $modelRepository;
    private ModelFinder $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->providerRepository = $this->createMock(AiProviderRepository::class);
        $this->providerRepository->method('makeScopeOverrides')->willReturn(new ScopeOverrides());
        $this->modelRepository = $this->createMock(AiModelRepository::class);

        $this->sut = new ModelFinder($this->providerRepository, $this->modelRepository);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeProvider(string $providerId): AiProvider
    {
        $provider = new AiProvider();
        $provider->id = crc32($providerId);
        $provider->provider_id = $providerId;
        return $provider;
    }

    private function makeModel(string $modelId, string $providerId): AiModel
    {
        $model = new AiModel(['model_id' => $modelId]);
        $model->setRelation('provider', $this->makeProvider($providerId));
        return $model;
    }

    /**
     * @param array<string, AiProvider> $providers
     */
    private function givenProviders(array $providers): void
    {
        $this->providerRepository->method('findOneByProviderId')
            ->willReturnCallback(fn(string $providerId) => $providers[$providerId] ?? null);
    }

    // =========================================================================
    // Qualified identifiers
    // =========================================================================

    public function testItFindsModelByProviderAndModelId(): void
    {
        $gwdg = $this->makeProvider('gwdg');
        $model = $this->makeModel('gemma-4-31b-it', 'gwdg');
        $this->givenProviders(['gwdg' => $gwdg]);

        $this->modelRepository->expects(static::once())->method('findOneByProvider')
            ->with($gwdg, 'gemma-4-31b-it')
            ->willReturn($model);
        $this->modelRepository->expects(static::never())->method('findAllByModelId');

        static::assertSame($model, $this->sut->find('gwdg/gemma-4-31b-it'));
    }

    public function testItPassesScopeOverridesToTheModelQuery(): void
    {
        $gwdg = $this->makeProvider('gwdg');
        $overrides = new ScopeOverrides();
        $this->givenProviders(['gwdg' => $gwdg]);

        $this->modelRepository->expects(static::once())->method('findOneByProvider')
            ->with($gwdg, 'gemma-4-31b-it', $overrides)
            ->willReturn($this->makeModel('gemma-4-31b-it', 'gwdg'));

        $this->sut->find('gwdg/gemma-4-31b-it', $overrides);
    }

    public function testItKeepsProviderScopesForRegularLookups(): void
    {
        // Regular users may not disable the provider scopes, doing so aborts the request with a 403
        $this->providerRepository->expects(static::never())->method('makeScopeOverrides');
        $this->providerRepository->expects(static::once())->method('findOneByProviderId')
            ->with('gwdg', null)
            ->willReturn($this->makeProvider('gwdg'));
        $this->modelRepository->method('findOneByProvider')->willReturn($this->makeModel('gemma-4-31b-it', 'gwdg'));

        $this->sut->find('gwdg/gemma-4-31b-it');
    }

    public function testItBypassesProviderScopesOnlyWhenModelScopesAreBypassed(): void
    {
        $providerOverrides = new ScopeOverrides();
        $providerRepository = $this->createMock(AiProviderRepository::class);
        $providerRepository->method('makeScopeOverrides')->willReturn($providerOverrides);
        $providerRepository->expects(static::once())->method('findOneByProviderId')
            ->with('gwdg', $providerOverrides)
            ->willReturn($this->makeProvider('gwdg'));
        $this->modelRepository->method('findOneByProvider')->willReturn($this->makeModel('gemma-4-31b-it', 'gwdg'));

        (new ModelFinder($providerRepository, $this->modelRepository))->find('gwdg/gemma-4-31b-it', new ScopeOverrides());
    }

    public function testItTreatsUnknownProviderPrefixAsPlainModelId(): void
    {
        // e.g. an OpenRouter model id like "google/gemma-3" sent without provider prefix
        $model = $this->makeModel('google/gemma-3', 'openRouter');
        $this->givenProviders([]);

        $this->modelRepository->expects(static::never())->method('findOneByProvider');
        $this->modelRepository->expects(static::once())->method('findAllByModelId')
            ->with('google/gemma-3')
            ->willReturn(new AiModelCollection([$model]));

        static::assertSame($model, $this->sut->find('google/gemma-3'));
    }

    public function testItRetriesAsPlainModelIdWhenProviderHasNoSuchModel(): void
    {
        $model = $this->makeModel('google/gemma-3', 'openRouter');
        $this->givenProviders(['google' => $this->makeProvider('google')]);

        $this->modelRepository->method('findOneByProvider')->willReturn(null);
        $this->modelRepository->expects(static::once())->method('findAllByModelId')
            ->with('google/gemma-3')
            ->willReturn(new AiModelCollection([$model]));

        static::assertSame($model, $this->sut->find('google/gemma-3'));
    }

    // =========================================================================
    // Plain model ids (backwards compatibility)
    // =========================================================================

    public function testItFindsUnambiguousPlainModelId(): void
    {
        $model = $this->makeModel('gpt-5-nano', 'openAi');

        $this->providerRepository->expects(static::never())->method('findOneByProviderId');
        $this->modelRepository->method('findAllByModelId')->willReturn(new AiModelCollection([$model]));

        static::assertSame($model, $this->sut->find('gpt-5-nano'));
    }

    public function testItThrowsForAmbiguousPlainModelId(): void
    {
        $this->modelRepository->method('findAllByModelId')->willReturn(new AiModelCollection([
            $this->makeModel('gemma-4-31b-it', 'gwdg'),
            $this->makeModel('gemma-4-31b-it', 'google'),
        ]));

        $this->expectException(AmbiguousModelIdException::class);
        $this->expectExceptionMessage('gwdg/gemma-4-31b-it, google/gemma-4-31b-it');

        $this->sut->find('gemma-4-31b-it');
    }

    public function testItReturnsNullForUnknownPlainModelId(): void
    {
        $this->modelRepository->method('findAllByModelId')->willReturn(new AiModelCollection());

        static::assertNull($this->sut->find('unknown'));
    }

    // =========================================================================
    // Numeric ids
    // =========================================================================

    public function testItLooksUpNumericIdsByPrimaryKey(): void
    {
        $model = $this->makeModel('gpt-5-nano', 'openAi');

        $this->modelRepository->expects(static::once())->method('findOne')->with('42')->willReturn($model);
        $this->modelRepository->expects(static::never())->method('findAllByModelId');

        static::assertSame($model, $this->sut->find('42'));
    }

    // =========================================================================
    // findOrFail
    // =========================================================================

    public function testItFindOrFailThrowsWhenNothingMatches(): void
    {
        $this->givenProviders([]);
        $this->modelRepository->method('findAllByModelId')->willReturn(new AiModelCollection());

        $this->expectException(ModelIdNotAvailableException::class);

        $this->sut->findOrFail('gwdg/unknown');
    }
}
