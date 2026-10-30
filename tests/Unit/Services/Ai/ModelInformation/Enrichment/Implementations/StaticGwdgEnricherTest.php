<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ModelInformation\Enrichment\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\StaticGwdgEnricher;
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\Models\Limits\Values\NullAiModelLimits;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Translation\LocaleService;
use App\Services\Translation\Value\Locale;
use App\Utils\JobMetrics;
use Illuminate\Support\Collection;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(StaticGwdgEnricher::class)]
class StaticGwdgEnricherTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        self::assertInstanceOf(StaticGwdgEnricher::class, $this->makeSut());
    }

    // =========================================================================
    // Token limits
    // =========================================================================

    public function testItEnrichesBothInputAndOutputTokenLimitsFromTheContextWindow(): void
    {
        $result = $this->makeSut()->enrichModelInfo(
            $this->makeModel('qwen3.5-122b-a10b'),
            $this->makeProvider(),
            new JobMetrics('test'),
        );

        $limits = $result->limits;
        self::assertInstanceOf(ChatAiModelLimits::class, $limits);
        self::assertSame(256000, $limits->getMaxInputTokens());
        self::assertSame(256000, $limits->getMaxOutputTokens());
    }

    public function testItReturnsModelUnchangedWhenModelIdIsNotInData(): void
    {
        $model = $this->makeModel('some-unknown-model');

        $result = $this->makeSut()->enrichModelInfo($model, $this->makeProvider(), new JobMetrics('test'));

        self::assertSame($model, $result);
        self::assertInstanceOf(NullAiModelLimits::class, $result->limits);
    }
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(): StaticGwdgEnricher
    {
        $localeService = $this->createMock(LocaleService::class);
        $localeService->method('getLocale')->willReturn(new Locale('en_US', 'en-US', 'English', 'EN'));

        $sut = new StaticGwdgEnricher();
        $sut->setService(LocaleService::class, $localeService);

        return $sut;
    }

    private function makeModel(string $modelId): AiModel
    {
        $model = new AiModel(['model_id' => $modelId]);

        // No migrated database here: a stand-in collection so the description relation never lazy-loads.
        $model->setRawAttributes([...$model->getAttributes(), 'description' => new Collection()]);

        return $model;
    }

    private function makeProvider(): AiProviderProxy
    {
        return new AiProviderProxy(
            provider: new AiProvider(),
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $this->createMock(Driver::class),
        );
    }
}
