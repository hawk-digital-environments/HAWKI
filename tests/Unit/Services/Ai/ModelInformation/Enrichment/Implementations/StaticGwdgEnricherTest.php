<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ModelInformation\Enrichment\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\StaticGwdgEnricher;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Flags\Values\WellKnownModelFlags;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\Implementations\GwdgAdapter;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Translation\LocaleService;
use App\Services\Translation\Value\Locale;
use App\Utils\JobMetrics;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(StaticGwdgEnricher::class)]
class StaticGwdgEnricherTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function enrich(string $modelId, ProviderAdapterInterface $adapter): AiModel
    {
        $model        = new AiModel(['model_id' => $modelId]);
        $model->flags = AiModelFlags::fromArray([]);

        $localeService = $this->createMock(LocaleService::class);
        $localeService->method('getLocale')->willReturn(new Locale('en', 'en', 'English', 'EN'));

        return (new StaticGwdgEnricher())->setService(LocaleService::class, $localeService)->enrichModelInfo(
            $model,
            new AiProviderProxy(
                provider: new AiProvider(),
                adapter: $adapter,
                driver: $this->createMock(Driver::class)
            ),
            new JobMetrics('test')
        );
    }

    // =========================================================================
    // enrichModelInfo
    // =========================================================================

    public function testItEnrichesKnownModelsOfGwdgProviders(): void
    {
        $model = $this->enrich('gemma-4-31b-it', $this->createMock(GwdgAdapter::class));

        static::assertTrue($model->flags->has(WellKnownModelFlags::OPEN_WEIGHTS));
    }

    public function testItIgnoresTheSameModelIdOfOtherProviders(): void
    {
        $model = $this->enrich('gemma-4-31b-it', $this->createMock(ProviderAdapterInterface::class));

        static::assertSame([], $model->flags->toArray());
    }
}
