<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ModelInformation\Enrichment\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\StaticGoogleEnricher;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Flags\Values\WellKnownModelFlags;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\Implementations\GeminiAdapter;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(StaticGoogleEnricher::class)]
class StaticGoogleEnricherTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeProvider(ProviderAdapterInterface $adapter): AiProviderProxy
    {
        return new AiProviderProxy(
            provider: new AiProvider(),
            adapter: $adapter,
            driver: $this->createMock(Driver::class)
        );
    }

    private function enrich(string $modelId, ProviderAdapterInterface $adapter, array $flags = []): AiModel
    {
        $model        = new AiModel(['model_id' => $modelId]);
        $model->flags = AiModelFlags::fromArray($flags);

        return (new StaticGoogleEnricher())->enrichModelInfo(
            $model,
            $this->makeProvider($adapter),
            new JobMetrics('test')
        );
    }

    // =========================================================================
    // enrichModelInfo
    // =========================================================================

    public function testItAttachesNoThinkingBudgetFlagToGemmaOnGemini(): void
    {
        $model = $this->enrich(
            'gemma-4-26b-a4b-it',
            $this->createMock(GeminiAdapter::class),
            [WellKnownModelFlags::FEATURE_REASONING]
        );

        static::assertTrue($model->flags->has(GeminiAdapter::FLAG_NO_THINKING_BUDGET));
        static::assertTrue($model->flags->has(WellKnownModelFlags::FEATURE_REASONING));
    }

    public function testItAttachesGroundingCapabilitiesToGeminiModels(): void
    {
        $model = $this->enrich('gemini-3.8-flash', $this->createMock(GeminiAdapter::class));

        static::assertTrue($model->native_capabilities->hasWebSearch());
        static::assertTrue($model->native_capabilities->hasWebFetch());
    }

    public function testItAttachesNoNativeCapabilitiesToGemma(): void
    {
        $model = $this->enrich(
            'gemma-4-31b-it',
            $this->createMock(GeminiAdapter::class),
            [WellKnownModelFlags::FEATURE_REASONING]
        );

        static::assertFalse($model->native_capabilities?->hasWebSearch() ?? false);
        static::assertFalse($model->native_capabilities?->hasWebFetch() ?? false);
    }

    public function testItLeavesUnknownModelsUntouched(): void
    {
        $model = $this->enrich('gemini-9-pro-preview', $this->createMock(GeminiAdapter::class));

        static::assertFalse($model->flags->has(GeminiAdapter::FLAG_NO_THINKING_BUDGET));
        static::assertSame([], $model->native_capabilities->toArray());
    }

    public function testItIgnoresModelsOfOtherProviders(): void
    {
        $model = $this->enrich('gemma-4-31b-it', $this->createMock(ProviderAdapterInterface::class));

        static::assertFalse($model->flags->has(GeminiAdapter::FLAG_NO_THINKING_BUDGET));
    }
}
